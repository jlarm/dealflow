<?php

namespace App\Jobs;

use App\Enums\EmailStatus;
use App\Enums\UsState;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use App\Models\Tag;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Streams an uploaded CSV, maps its columns, resolves companies and tags, and
 * queues the rows as a batch of ImportContactsChunk jobs.
 *
 * Understands Apollo exports and researched dealer lists. Companies and tags
 * are resolved here, in a single job, so parallel chunk jobs never race to
 * create the same company or tag.
 *
 * @phpstan-type ImportRow array{row_number: int, raw: array<string, string|null>, data: array<string, string|null>, company_id: int|null, tag_ids: list<int>}
 */
class ProcessImport implements ShouldQueue
{
    use Queueable;

    /**
     * The number of rows handed to each chunk job.
     */
    public const int ROWS_PER_CHUNK = 500;

    /**
     * Re-running would queue the rows twice, so this job is not retried.
     */
    public int $tries = 1;

    /**
     * Kept below the database queue's 90 second retry_after.
     */
    public int $timeout = 80;

    /**
     * Recognised header names for each field, after normalization. When several
     * columns match a field, the first one with a value wins, in the order listed.
     *
     * @var array<string, list<string>>
     */
    public const array COLUMN_ALIASES = [
        'first_name' => ['first_name', 'firstname', 'first', 'given_name'],
        'last_name' => ['last_name', 'lastname', 'last', 'surname', 'family_name'],
        'name' => ['name', 'full_name', 'contact_name', 'contact'],
        'email' => ['email', 'email_address', 'e_mail', 'work_email', 'business_email', 'public_email'],
        'email_status' => ['email_status'],
        'email_catch_all' => ['primary_email_catch_all_status', 'email_catch_all_status', 'catch_all_status', 'catch_all'],
        'email_bounced' => ['email_bounced', 'bounced'],
        'phone' => ['work_direct_phone', 'direct_phone', 'mobile_phone', 'mobile', 'phone', 'phone_number', 'work_phone', 'corporate_phone', 'other_phone', 'home_phone'],
        'title' => ['title', 'job_title', 'position', 'role'],
        'seniority' => ['seniority'],
        'departments' => ['departments', 'department'],
        'linkedin_url' => ['person_linkedin_url', 'linkedin', 'linkedin_url', 'linkedin_profile'],
        'apollo_contact_id' => ['apollo_contact_id'],
        'company' => ['company', 'company_name', 'organization', 'organisation', 'account', 'account_name', 'dealership_group', 'dealership', 'dealer', 'dealer_name'],
        'domain' => ['website', 'domain', 'company_website', 'company_domain', 'url', 'web'],
        'industry' => ['industry'],
        'size' => ['employees', 'size', 'company_size', 'employee_count', 'number_of_employees', 'headcount'],
        'city' => ['company_city', 'city'],
        'state' => ['company_state', 'state'],
        'company_phone' => ['company_phone'],
        'apollo_account_id' => ['apollo_account_id'],
        'lists' => ['lists'],
        'source_type' => ['source_type'],
        'source_url' => ['source_url'],
        'research_date' => ['research_date'],
        'notes' => ['notes', 'note'],
    ];

    /**
     * Apollo company columns kept as raw enrichment data on the company.
     *
     * @var list<string>
     */
    public const array COMPANY_DETAIL_COLUMNS = [
        'keywords', 'technologies', 'annual_revenue', 'total_funding', 'latest_funding',
        'latest_funding_amount', 'last_raised_at', 'number_of_retail_locations', 'sic_codes',
        'naics_codes', 'company_linkedin_url', 'company_address', 'company_country',
        'parent_company_apollo_data', 'facebook_url', 'twitter_url',
    ];

    /**
     * Email providers whose domain says nothing about the contact's company.
     *
     * @var list<string>
     */
    private const array PERSONAL_EMAIL_DOMAINS = [
        'gmail.com', 'googlemail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com',
        'aol.com', 'icloud.com', 'me.com', 'msn.com', 'proton.me', 'protonmail.com', 'comcast.net',
    ];

    /**
     * Company ids already resolved in this run, keyed by "account:", "domain:", or "name:" lookups.
     *
     * @var array<string, int>
     */
    private array $companyIds = [];

    /**
     * Tag ids already resolved in this run, keyed by lowercase tag name.
     *
     * @var array<string, int>
     */
    private array $tagIds = [];

    public function __construct(public Import $import) {}

    public function handle(): void
    {
        try {
            $this->queueRows();
        } finally {
            Storage::disk('local')->delete($this->import->path);
        }
    }

    /**
     * Record why the import could not be processed.
     */
    public function failed(?Throwable $exception): void
    {
        $this->import->fail(__('The file could not be processed.'));
    }

    private function queueRows(): void
    {
        $rows = $this->readCsv();
        $header = $rows->first() ?? [];
        $normalizedHeader = array_map($this->normalizeHeader(...), $header);
        $columns = $this->mapColumns($normalizedHeader);

        if (! array_intersect(['email', 'first_name', 'last_name', 'name'], array_keys($columns))) {
            $this->import->fail(__('The file needs a header row with an email or name column.'));

            return;
        }

        $detailColumns = array_intersect($normalizedHeader, self::COMPANY_DETAIL_COLUMNS);

        $jobs = [];
        $rowCount = 0;

        $rows->skip(1)
            ->map(fn (array $values, int $index): array => [
                'row_number' => $index + 1,
                'raw' => $this->combine($header, $values),
                'data' => $this->normalize($columns, $values),
                'details' => $this->companyDetails($detailColumns, $values),
            ])
            ->reject(fn (array $row): bool => array_filter($row['data']) === [])
            ->chunk(self::ROWS_PER_CHUNK)
            ->each(function (LazyCollection $chunk) use (&$jobs, &$rowCount): void {
                $rows = array_values($chunk->map(fn (array $row): array => $this->resolveRelations($row))->all());
                $rowCount += count($rows);
                $jobs[] = new ImportContactsChunk($this->import, $rows);
            });

        $this->import->update(['row_count' => $rowCount]);

        if ($jobs === []) {
            $this->import->finish();

            return;
        }

        $importId = $this->import->id;

        Bus::batch($jobs)
            ->name("Import #{$importId}")
            ->allowFailures()
            ->finally(static function (Batch $batch) use ($importId): void {
                Import::find($importId)?->finish($batch->failedJobs);
            })
            ->dispatch();
    }

    /**
     * Stream the CSV one row at a time so large files are never loaded into memory.
     *
     * @return LazyCollection<int, list<string|null>>
     */
    private function readCsv(): LazyCollection
    {
        $path = $this->import->path;

        return LazyCollection::make(function () use ($path) {
            $stream = Storage::disk('local')->readStream($path);

            if ($stream === null) {
                return;
            }

            try {
                while (($values = fgetcsv($stream, escape: '')) !== false) {
                    if ($values === [null]) {
                        continue;
                    }

                    yield $values;
                }
            } finally {
                fclose($stream);
            }
        });
    }

    /**
     * Turn a header like "# Employees" or "Dealership / Group" into "employees" or "dealership_group".
     */
    private function normalizeHeader(?string $name): string
    {
        return Str::of((string) $name)
            ->replaceStart("\u{FEFF}", '')
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value();
    }

    /**
     * Map each field to the positions of its matching columns, in alias priority order.
     *
     * @param  list<string>  $normalizedHeader
     * @return array<string, list<int>>
     */
    private function mapColumns(array $normalizedHeader): array
    {
        $columns = [];

        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                foreach (array_keys($normalizedHeader, $alias, true) as $position) {
                    $columns[$field][] = $position;
                }
            }
        }

        return $columns;
    }

    /**
     * Pair the original header names with a row's values, for showing failed rows.
     *
     * @param  list<string|null>  $header
     * @param  list<string|null>  $values
     * @return array<string, string|null>
     */
    private function combine(array $header, array $values): array
    {
        $combined = [];

        foreach ($header as $position => $name) {
            $combined[(string) $name] = $values[$position] ?? null;
        }

        return $combined;
    }

    /**
     * Pick each field's value, then normalize names, email, email status, domain, and URLs.
     *
     * @param  array<string, list<int>>  $columns
     * @param  list<string|null>  $values
     * @return array<string, string|null>
     */
    private function normalize(array $columns, array $values): array
    {
        $data = $this->pickValues($columns, $values);

        foreach (['company', 'industry', 'size', 'city', 'state', 'seniority'] as $field) {
            if ($data[$field] !== null) {
                $data[$field] = Str::limit($data[$field], 255, '');
            }
        }

        if ($data['state'] !== null) {
            $data['state'] = UsState::normalize($data['state']);
        }

        if ($data['name'] !== null && $data['first_name'] === null && $data['last_name'] === null) {
            [$data['first_name'], $data['last_name']] = array_pad(explode(' ', $data['name'], 2), 2, null);
        }

        if ($data['email'] !== null) {
            $data['email'] = Contact::normalizeEmail($data['email']);
        }

        $data['list_email_status'] = $data['email_status'];
        $data['email_status'] = EmailStatus::fromListValues(
            $data['email'] === null ? null : $data['email_status'],
            $data['email_catch_all'],
            $data['email_bounced'],
        )?->value;

        $domain = $data['domain'] !== null
            ? Company::normalizeDomain($data['domain'])
            : $this->companyDomainFromEmail($data['email'], $data['company']);

        $data['domain'] = $domain !== null && strlen($domain) <= 255 && preg_match(Company::DOMAIN_PATTERN, $domain)
            ? $domain
            : null;

        $data['source_url'] = $this->httpUrl($data['source_url']);

        unset($data['name'], $data['email_catch_all'], $data['email_bounced']);

        return $data;
    }

    /**
     * Take each field's value from the first of its columns that is not blank.
     *
     * @param  array<string, list<int>>  $columns
     * @param  list<string|null>  $values
     * @return array<string, string|null>
     */
    private function pickValues(array $columns, array $values): array
    {
        $data = [];

        foreach (array_keys(self::COLUMN_ALIASES) as $field) {
            $data[$field] = null;

            foreach ($columns[$field] ?? [] as $position) {
                $value = trim((string) ($values[$position] ?? ''));

                if ($value !== '') {
                    $data[$field] = $value;

                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Keep a URL only if it is an http or https link, so it is safe to show as a link.
     */
    private function httpUrl(?string $url): ?string
    {
        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }

    /**
     * Use the email's domain as the company domain when it is a work address.
     */
    private function companyDomainFromEmail(?string $email, ?string $company): ?string
    {
        if ($email === null || $company === null || ! str_contains($email, '@')) {
            return null;
        }

        $domain = Str::after($email, '@');

        return in_array($domain, self::PERSONAL_EMAIL_DOMAINS, true) ? null : $domain;
    }

    /**
     * Collect the Apollo company columns that have a value.
     *
     * @param  array<int, string>  $detailColumns
     * @param  list<string|null>  $values
     * @return array<string, string>
     */
    private function companyDetails(array $detailColumns, array $values): array
    {
        $details = [];

        foreach ($detailColumns as $position => $column) {
            $value = trim((string) ($values[$position] ?? ''));

            if ($value !== '') {
                $details[$column] = $value;
            }
        }

        return $details;
    }

    /**
     * Attach the row's company and tag ids. Rows that do not name a contact will
     * fail validation, so no company or tag is created for them.
     *
     * @param  array{row_number: int, raw: array<string, string|null>, data: array<string, string|null>, details: array<string, string>}  $row
     * @return ImportRow
     */
    private function resolveRelations(array $row): array
    {
        $data = $row['data'];
        $identifiesContact = $data['email'] !== null || $data['first_name'] !== null || $data['last_name'] !== null;

        return [
            'row_number' => $row['row_number'],
            'raw' => $row['raw'],
            'data' => $data,
            'company_id' => $identifiesContact ? $this->companyId($data, $row['details']) : null,
            'tag_ids' => $identifiesContact ? $this->tagIds($data['lists']) : [],
        ];
    }

    /**
     * Find or create the row's company, matching by Apollo account, then domain, then name and state.
     * Existing companies only get their blank fields filled in.
     *
     * @param  array<string, string|null>  $data
     * @param  array<string, string>  $details
     */
    private function companyId(array $data, array $details): ?int
    {
        $accountId = $data['apollo_account_id'];
        $domain = $data['domain'];
        $name = $data['company'];

        if ($accountId === null && $domain === null && $name === null) {
            return null;
        }

        $keys = array_values(array_filter([
            $accountId !== null ? "account:{$accountId}" : null,
            $domain !== null ? "domain:{$domain}" : null,
            $domain === null && $name !== null ? 'name:'.Str::lower($name).'|'.Str::lower((string) $data['state']) : null,
        ]));

        foreach ($keys as $key) {
            if (isset($this->companyIds[$key])) {
                return $this->companyIds[$key];
            }
        }

        $attributes = array_filter([
            'industry' => $data['industry'],
            'size' => $data['size'],
            'city' => $data['city'],
            'state' => $data['state'],
            'phone' => $data['company_phone'] !== null ? Str::limit($data['company_phone'], 50, '') : null,
            'enrichment_data' => $details !== [] ? $details : null,
            'enriched_at' => $details !== [] ? now() : null,
        ], fn (mixed $value): bool => $value !== null);

        $company = $this->existingCompany($accountId, $domain, $name, $data['state']);

        if ($company === null) {
            $company = Company::createOrFirst(
                match (true) {
                    $domain !== null => ['domain' => $domain],
                    $accountId !== null => ['apollo_account_id' => $accountId],
                    default => ['name' => $name],
                },
                [...$attributes, 'name' => $name ?? $domain ?? $accountId, 'domain' => $domain, 'apollo_account_id' => $accountId],
            );
        } else {
            $company->fill(array_filter(
                $attributes,
                fn (mixed $value, string $field): bool => $company->getAttribute($field) === null,
                ARRAY_FILTER_USE_BOTH,
            ))->save();
        }

        foreach ($keys as $key) {
            $this->companyIds[$key] = $company->id;
        }

        return $company->id;
    }

    /**
     * Find an existing company by Apollo account, then domain, then name within the
     * same state, so same-named dealerships in different states stay separate.
     */
    private function existingCompany(?string $accountId, ?string $domain, ?string $name, ?string $state): ?Company
    {
        if ($accountId !== null && ($company = Company::firstWhere('apollo_account_id', $accountId)) !== null) {
            return $company;
        }

        if ($domain !== null) {
            return Company::firstWhere('domain', $domain);
        }

        if ($name === null) {
            return null;
        }

        return Company::query()
            ->whereNull('domain')
            ->where('name', $name)
            ->when(
                $state !== null,
                fn ($query) => $query->where('state', $state),
                fn ($query) => $query->whereNull('state'),
            )
            ->orderBy('id')
            ->first();
    }

    /**
     * Find or create a tag for each list in Apollo's comma-separated "Lists" column.
     *
     * @return list<int>
     */
    private function tagIds(?string $lists): array
    {
        if ($lists === null) {
            return [];
        }

        $ids = [];

        foreach (preg_split('/[,;]/', $lists) ?: [] as $name) {
            $name = Str::limit(trim($name), 50, '');

            if ($name === '') {
                continue;
            }

            $ids[] = $this->tagIds[Str::lower($name)] ??= Tag::createOrFirst(['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }
}
