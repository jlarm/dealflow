<?php

namespace App\Jobs;

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
    private const array CONTACT_FIELDS = ['first_name', 'last_name', 'email', 'phone', 'title', 'linkedin_url', 'company_id'];

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

    public function handle(): void
    {
        DB::transaction(function (): void {
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

                $this->saveContact($row['data']);
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
     * Create the contact, or fill in the blanks on an existing contact with the same email.
     * Existing values, pipeline status, and source list are never overwritten.
     *
     * @param  array<string, int|string|null>  $data
     */
    private function saveContact(array $data): void
    {
        $attributes = array_filter(
            array_intersect_key($data, array_flip(self::CONTACT_FIELDS)),
            fn (int|string|null $value): bool => $value !== null,
        );

        if (! isset($attributes['email'])) {
            Contact::create([...$attributes, 'source_list' => $this->import->source_list]);

            return;
        }

        $contact = Contact::createOrFirst(
            ['email' => $attributes['email']],
            [...$attributes, 'source_list' => $this->import->source_list],
        );

        if ($contact->wasRecentlyCreated) {
            return;
        }

        $blanks = array_filter(
            $attributes,
            fn (int|string $value, string $field): bool => $contact->getAttribute($field) === null,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($blanks !== []) {
            $contact->update($blanks);
        }
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
            'linkedin_url' => ['nullable', 'url', 'max:255'],
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
