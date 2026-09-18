<?php

namespace App\Jobs;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\EmailStatus;
use App\Models\Contact;
use App\Models\Import;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Validates and saves one chunk of imported rows.
 *
 * Everything runs in one transaction, so a retried chunk never double-counts
 * rows, duplicates failures, or creates the same email-less contact twice.
 *
 * @phpstan-import-type ImportRow from ProcessImport
 */
class ImportContactsChunk implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;

    /**
     * Kept below the database queue's 90 second retry_after.
     */
    public int $timeout = 60;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * The contact attributes an import may set.
     *
     * @var list<string>
     */
    private const array CONTACT_FIELDS = [
        'first_name', 'last_name', 'email', 'email_status', 'phone', 'title',
        'seniority', 'departments', 'linkedin_url', 'apollo_contact_id',
    ];

    /**
     * The research details recorded on the contact's "Imported" timeline entry.
     *
     * @var list<string>
     */
    private const array RESEARCH_FIELDS = ['source_type', 'source_url', 'research_date', 'notes'];

    /**
     * @param  list<ImportRow>  $rows
     */
    public function __construct(public Import $import, public array $rows) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(LogActivity $logActivity): void
    {
        DB::transaction(function () use ($logActivity): void {
            $failures = [];

            foreach ($this->rows as $row) {
                $validator = Validator::make($row['data'], $this->rules(), $this->messages());

                if ($validator->fails()) {
                    $failures[] = [
                        'import_id' => $this->import->id,
                        'row_number' => $row['row_number'],
                        'errors' => json_encode($validator->errors()->toArray()),
                        'raw_row' => json_encode($row['raw']),
                        'created_at' => now(),
                    ];

                    continue;
                }

                $contact = $this->saveContact($row);

                if ($row['tag_ids'] !== []) {
                    $contact->tags()->syncWithoutDetaching($row['tag_ids']);
                }

                $logActivity->handle($contact, ActivityType::Imported, $this->importedPayload($row), $this->import->user);
            }

            if ($failures !== []) {
                $this->import->failures()->insert($failures);
            }

            Import::query()->whereKey($this->import->id)->incrementEach([
                'processed_rows' => count($this->rows),
                'failed_rows' => count($failures),
            ]);
        });
    }

    /**
     * Create the contact, or fill in the blanks on an existing one matched by email or Apollo contact id.
     * Existing values, pipeline stage, and source list are never overwritten, except that a
     * bounced or invalid email status always replaces a better one, to protect deliverability.
     *
     * @param  ImportRow  $row
     */
    private function saveContact(array $row): Contact
    {
        $attributes = array_filter(
            [...array_intersect_key($row['data'], array_flip(self::CONTACT_FIELDS)), 'company_id' => $row['company_id']],
            fn (int|string|null $value): bool => $value !== null,
        );

        $contact = $this->existingContact($attributes);

        if ($contact === null) {
            return $this->createContact($attributes);
        }

        $updates = array_filter(
            $attributes,
            fn (int|string $value, string $field): bool => $contact->getAttribute($field) === null
                && ($field !== 'apollo_contact_id' || ! Contact::where('apollo_contact_id', $value)->exists()),
            ARRAY_FILTER_USE_BOTH,
        );

        $status = EmailStatus::tryFrom((string) ($attributes['email_status'] ?? ''));

        if ($status !== null && ! $status->isSendable()) {
            $updates['email_status'] = $status;
        }

        if ($updates !== []) {
            $contact->update($updates);
        }

        return $contact;
    }

    /**
     * @param  array<string, int|string>  $attributes
     */
    private function existingContact(array $attributes): ?Contact
    {
        if (isset($attributes['email'])) {
            return Contact::firstWhere('email', $attributes['email']);
        }

        if (isset($attributes['apollo_contact_id'])) {
            return Contact::firstWhere('apollo_contact_id', $attributes['apollo_contact_id']);
        }

        return null;
    }

    /**
     * Create a contact. createOrFirst uses the unique email index, so two chunks importing
     * the same email at once end up with one contact.
     *
     * @param  array<string, int|string>  $attributes
     */
    private function createContact(array $attributes): Contact
    {
        if (isset($attributes['apollo_contact_id']) && Contact::where('apollo_contact_id', $attributes['apollo_contact_id'])->exists()) {
            unset($attributes['apollo_contact_id']);
        }

        $attributes['source_list'] = $this->import->source_list;

        return isset($attributes['email'])
            ? Contact::createOrFirst(['email' => $attributes['email']], $attributes)
            : Contact::create($attributes);
    }

    /**
     * @param  ImportRow  $row
     * @return array<string, int|string>
     */
    private function importedPayload(array $row): array
    {
        return array_filter([
            'import_id' => $this->import->id,
            'source_list' => $this->import->source_list,
            'email_status' => $row['data']['list_email_status'] ?? null,
            ...array_intersect_key($row['data'], array_flip(self::RESEARCH_FIELDS)),
        ], fn (int|string|null $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:255', 'required_without_all:last_name,email'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'title' => ['nullable', 'string', 'max:255'],
            'seniority' => ['nullable', 'string', 'max:255'],
            'departments' => ['nullable', 'string', 'max:2000'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'apollo_contact_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'first_name.required_without_all' => 'The row needs a name or an email address.',
        ];
    }
}
