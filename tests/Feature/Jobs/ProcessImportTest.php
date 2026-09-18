<?php

use App\Enums\ContactStatus;
use App\Enums\ImportStatus;
use App\Jobs\ImportContactsChunk;
use App\Jobs\ProcessImport;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * Store CSV content on the fake local disk and create an import that points at it.
 */
function importFromCsv(string $csv, string $sourceList = 'dealers.csv'): Import
{
    Storage::disk('local')->put('imports/upload.csv', $csv);

    return Import::factory()->create(['path' => 'imports/upload.csv', 'source_list' => $sourceList]);
}

test('imports every valid row, matches companies, and records failed rows', function () {
    Storage::fake('local');
    $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/contacts.csv')));

    ProcessImport::dispatch($import);

    expect($import->refresh())
        ->status->toBe(ImportStatus::Completed)
        ->row_count->toBe(6)
        ->processed_rows->toBe(6)
        ->failed_rows->toBe(1);

    $acme = Company::where('domain', 'acme.com')->sole();
    expect($acme->name)->toBe('Acme Motors');
    expect(Contact::where('email', 'jane.doe@acme.com')->sole())
        ->company_id->toBe($acme->id)
        ->phone->toBe('555-0100')
        ->title->toBe('General Manager')
        ->source_list->toBe('dealers.csv')
        ->status->toBe(ContactStatus::New);
    expect(Contact::where('email', 'john@acme.com')->sole()->company_id)->toBe($acme->id);
    expect(Contact::where('first_name', 'Pat')->sole()->company->name)->toBe('Northside Auto');
    expect(Contact::where('email', 'sam@gmail.com')->sole()->company)
        ->name->toBe('Rivera Trucks')
        ->domain->toBeNull();
    expect(Contact::count())->toBe(4);

    expect($import->failures()->sole())
        ->row_number->toBe(4)
        ->errors->toHaveKey('email')
        ->raw_row->toMatchArray(['Email Address' => 'not-an-email', 'Company' => 'Bad Co']);

    Storage::disk('local')->assertMissing('imports/upload.csv');
});

test('fills blanks on an existing contact without overwriting its details, stage, or source', function () {
    Storage::fake('local');
    $contact = Contact::factory()->withStatus(ContactStatus::Qualified)->create([
        'email' => 'jane@acme.com',
        'title' => 'Owner',
        'phone' => null,
        'source_list' => 'first-list.csv',
    ]);
    $import = importFromCsv("Email,Title,Phone\njane@acme.com,Intern,555-0100\n");

    ProcessImport::dispatch($import);

    expect($contact->refresh())
        ->title->toBe('Owner')
        ->phone->toBe('555-0100')
        ->status->toBe(ContactStatus::Qualified)
        ->source_list->toBe('first-list.csv');
    expect(Contact::count())->toBe(1);
});

test('splits a full name column into first and last names', function () {
    Storage::fake('local');
    $import = importFromCsv("Full Name,Email\nMaria de la Cruz,maria@example.com\n");

    ProcessImport::dispatch($import);

    expect(Contact::sole())
        ->first_name->toBe('Maria')
        ->last_name->toBe('de la Cruz');
});

test('fails the import when the file has no email or name column', function () {
    Storage::fake('local');
    Bus::fake();
    $import = importFromCsv("Company,Phone\nAcme,555-0100\n");

    (new ProcessImport($import))->handle();

    expect($import->refresh())
        ->status->toBe(ImportStatus::Failed)
        ->error->toBe('The file needs a header row with an email or name column.');
    Bus::assertNothingBatched();
    Storage::disk('local')->assertMissing('imports/upload.csv');
});

test('completes an import that has a header but no rows', function () {
    Storage::fake('local');
    $import = importFromCsv("Email,First Name\n");

    ProcessImport::dispatch($import);

    expect($import->refresh())
        ->status->toBe(ImportStatus::Completed)
        ->row_count->toBe(0);
});

test('splits large files into chunks of 500 rows', function () {
    Storage::fake('local');
    Bus::fake();
    $rows = collect(range(1, 501))->map(fn (int $number): string => "person{$number}@example.com")->implode("\n");
    $import = importFromCsv("Email\n{$rows}\n");

    (new ProcessImport($import))->handle();

    expect($import->refresh()->row_count)->toBe(501);
    Bus::assertBatched(fn (PendingBatch $batch): bool => $batch->hasJobs([
        fn (ImportContactsChunk $job): bool => count($job->rows) === 500 && $job->rows[0]['row_number'] === 2,
        fn (ImportContactsChunk $job): bool => count($job->rows) === 1 && $job->rows[0]['row_number'] === 502,
    ]));
});

test('does not create a company for a row without a name or email', function () {
    Storage::fake('local');
    $import = importFromCsv("Email,First Name,Company\n,,Lonely Motors\n");

    ProcessImport::dispatch($import);

    expect($import->refresh()->failed_rows)->toBe(1);
    $this->assertDatabaseEmpty('companies');
});
