<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Streams an uploaded CSV, maps its columns, resolves companies, and queues
 * the rows as a batch of ImportContactsChunk jobs.
 *
 * Companies are resolved here, in a single job, so parallel chunk jobs never
 * race to create the same company.
 *
 * @phpstan-type ImportRow array{row_number: int, raw: array<string, string|null>, data: array<string, int|string|null>}
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
     * Recognised header names for each contact field, after normalization.
     *
     * @var array<string, list<string>>
     */
    public const array COLUMN_ALIASES = [
        'first_name' => ['first_name', 'firstname', 'first', 'given_name'],
        'last_name' => ['last_name', 'lastname', 'last', 'surname', 'family_name'],
        'name' => ['name', 'full_name', 'contact_name', 'contact'],
        'email' => ['email', 'email_address', 'e_mail', 'work_email', 'business_email'],
        'phone' => ['phone', 'phone_number', 'mobile', 'mobile_phone', 'work_phone', 'direct_phone'],
        'title' => ['title', 'job_title', 'position', 'role'],
        'company' => ['company', 'company_name', 'organization', 'organisation', 'account', 'account_name', 'dealership', 'dealer', 'dealer_name'],
        'domain' => ['domain', 'website', 'company_website', 'company_domain', 'url', 'web'],
        'linkedin_url' => ['linkedin', 'linkedin_url', 'person_linkedin_url', 'linkedin_profile'],
        'industry' => ['industry'],
        'size' => ['size', 'company_size', 'employees', 'employee_count', 'number_of_employees', 'headcount'],
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
        $columns = $this->mapColumns($header);

        if (! array_intersect(['email', 'first_name', 'last_name', 'name'], array_keys($columns))) {
            $this->import->fail(__('The file needs a header row with an email or name column.'));

            return;
        }

        $jobs = [];
        $rowCount = 0;

        $rows->skip(1)
            ->map(fn (array $values, int $index): array => [
                'row_number' => $index + 1,
                'raw' => $this->combine($header, $values),
                'data' => $this->normalize($columns, $values),
            ])
            ->reject(fn (array $row): bool => array_filter($row['data']) === [])
            ->chunk(self::ROWS_PER_CHUNK)
            ->each(function (LazyCollection $chunk) use (&$jobs, &$rowCount): void {
                $rows = $this->resolveCompanies(array_values($chunk->all()));
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
     * Map each recognised header to its column position.
     *
     * @param  list<string|null>  $header
     * @return array<string, int>
     */
    private function mapColumns(array $header): array
    {
        $columns = [];

        foreach ($header as $position => $name) {
            $normalized = Str::of((string) $name)
                ->replaceStart("\u{FEFF}", '')
                ->lower()
                ->replaceMatches('/[^a-z0-9]+/', '_')
                ->trim('_')
                ->value();

            foreach (self::COLUMN_ALIASES as $field => $aliases) {
                if (! isset($columns[$field]) && in_array($normalized, $aliases, true)) {
                    $columns[$field] = $position;
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
     * Trim values, turn blanks into nulls, and normalize email, domain, and name fields.
     *
     * @param  array<string, int>  $columns
     * @param  list<string|null>  $values
     * @return array<string, int|string|null>
     */
    private function normalize(array $columns, array $values): array
    {
        $data = [];

        foreach (array_keys(self::COLUMN_ALIASES) as $field) {
            $value = isset($columns[$field]) ? trim((string) ($values[$columns[$field]] ?? '')) : '';
            $data[$field] = $value === '' ? null : $value;
        }

        foreach (['company', 'industry', 'size'] as $companyField) {
            if ($data[$companyField] !== null) {
                $data[$companyField] = Str::limit($data[$companyField], 255, '');
            }
        }

        if ($data['name'] !== null && $data['first_name'] === null && $data['last_name'] === null) {
            [$data['first_name'], $data['last_name']] = array_pad(explode(' ', $data['name'], 2), 2, null);
        }

        unset($data['name']);

        if ($data['email'] !== null) {
            $data['email'] = Contact::normalizeEmail($data['email']);
        }

        $domain = $data['domain'] !== null
            ? Company::normalizeDomain($data['domain'])
            : $this->companyDomainFromEmail($data['email'], $data['company']);

        $data['domain'] = $domain !== null && strlen($domain) <= 255 && preg_match(Company::DOMAIN_PATTERN, $domain)
            ? $domain
            : null;

        return $data;
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
     * Find or create each row's company, matching by domain first and then by name.
     *
     * @param  list<ImportRow>  $rows
     * @return list<ImportRow>
     */
    private function resolveCompanies(array $rows): array
    {
        $importable = array_filter($rows, fn (array $row): bool => $this->identifiesContact($row['data']));

        $byDomain = $this->companiesByDomain($importable);
        $byName = $this->companiesByName(array_filter($importable, fn (array $row): bool => $row['data']['domain'] === null));

        foreach ($rows as $index => $row) {
            $domain = $row['data']['domain'];
            $company = $row['data']['company'];

            $rows[$index]['data']['company_id'] = match (true) {
                $domain !== null => $byDomain[(string) $domain] ?? null,
                $company !== null => $byName[Str::lower((string) $company)] ?? null,
                default => null,
            };
        }

        return $rows;
    }

    /**
     * Whether a row names a contact at all. Rows that do not will fail validation,
     * so no company is created for them.
     *
     * @param  array<string, int|string|null>  $data
     */
    private function identifiesContact(array $data): bool
    {
        return $data['email'] !== null || $data['first_name'] !== null || $data['last_name'] !== null;
    }

    /**
     * @param  array<int, ImportRow>  $rows
     * @return array<string, int>
     */
    private function companiesByDomain(array $rows): array
    {
        $rowsByDomain = collect($rows)->filter(fn (array $row): bool => $row['data']['domain'] !== null)
            ->keyBy(fn (array $row): string => (string) $row['data']['domain']);

        if ($rowsByDomain->isEmpty()) {
            return [];
        }

        $companies = Company::query()
            ->whereIn('domain', $rowsByDomain->keys())
            ->pluck('id', 'domain')
            ->all();

        foreach ($rowsByDomain as $domain => $row) {
            $companies[$domain] ??= Company::createOrFirst(['domain' => $domain], [
                'name' => $row['data']['company'] ?? $domain,
                'industry' => $row['data']['industry'],
                'size' => $row['data']['size'],
            ])->id;
        }

        return $companies;
    }

    /**
     * @param  array<int, ImportRow>  $rows
     * @return array<string, int>
     */
    private function companiesByName(array $rows): array
    {
        $rowsByName = collect($rows)->filter(fn (array $row): bool => $row['data']['company'] !== null)
            ->keyBy(fn (array $row): string => Str::lower((string) $row['data']['company']));

        if ($rowsByName->isEmpty()) {
            return [];
        }

        $companies = Company::query()
            ->whereIn('name', $rowsByName->map(fn (array $row): string => (string) $row['data']['company'])->values())
            ->orderBy('id')
            ->get(['id', 'name'])
            ->unique(fn (Company $company): string => Str::lower($company->name))
            ->mapWithKeys(fn (Company $company): array => [Str::lower($company->name) => $company->id])
            ->all();

        foreach ($rowsByName as $name => $row) {
            $companies[$name] ??= Company::create([
                'name' => $row['data']['company'],
                'industry' => $row['data']['industry'],
                'size' => $row['data']['size'],
            ])->id;
        }

        return $companies;
    }
}
