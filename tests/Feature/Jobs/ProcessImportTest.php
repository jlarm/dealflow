<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Enums\ImportStatus;
use App\Jobs\ImportContactsChunk;
use App\Jobs\ProcessImport;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Import;
use App\Models\Tag;
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

describe('Apollo exports', function () {
    test('imports contacts with Apollo phones, targeting fields, ids, and email statuses', function () {
        Storage::fake('local');
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')));

        ProcessImport::dispatch($import);

        expect($import->refresh())
            ->status->toBe(ImportStatus::Completed)
            ->processed_rows->toBe(4)
            ->failed_rows->toBe(0);
        expect(Contact::where('apollo_contact_id', 'contact-1')->sole())
            ->email->toBe('dana@lakesideford.com')
            ->email_status->toBe(EmailStatus::Valid)
            ->phone->toBe('555-0101')
            ->seniority->toBe('director')
            ->departments->toBe('Master Sales, Master Operations')
            ->linkedin_url->toBe('https://www.linkedin.com/in/dana-whitfield')
            ->status->toBe(ContactStatus::New)
            ->last_contacted_at->toBeNull();
        expect(Contact::where('email', 'lee@lakesideford.com')->sole()->email_status)->toBe(EmailStatus::Risky);
        expect(Contact::where('email', 'sam@summitchevy.com')->sole())
            ->email_status->toBe(EmailStatus::Bounced)
            ->phone->toBe('555-0300');
        expect(Contact::where('apollo_contact_id', 'contact-4')->sole())
            ->email->toBeNull()
            ->email_status->toBeNull();
    });

    test('matches companies by Apollo account and keeps Apollo company data', function () {
        Storage::fake('local');
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')));

        ProcessImport::dispatch($import);

        expect(Company::count())->toBe(2);
        $lakeside = Company::where('apollo_account_id', 'account-1')->sole();
        expect($lakeside)
            ->domain->toBe('lakesideford.com')
            ->city->toBe('Round Rock')
            ->state->toBe('TX')
            ->phone->toBe('+1 512-555-0100')
            ->size->toBe('85')
            ->enrichment_data->toMatchArray([
                'keywords' => 'new cars, used cars',
                'technologies' => 'CDK Global',
                'number_of_retail_locations' => '3',
            ]);
        expect($lakeside->contacts()->count())->toBe(2);
        expect(Contact::where('apollo_contact_id', 'contact-4')->sole()->company)
            ->apollo_account_id->toBe('account-2')
            ->state->toBe('OK');
    });

    test('turns Apollo lists into tags', function () {
        Storage::fake('local');
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')));

        ProcessImport::dispatch($import);

        expect(Tag::orderBy('name')->pluck('name')->all())->toBe(['Q4 Dealers', 'Texas']);
        expect(Contact::where('apollo_contact_id', 'contact-1')->sole()->tags->pluck('name')->sort()->values()->all())->toBe(['Q4 Dealers', 'Texas']);
        expect(Contact::where('apollo_contact_id', 'contact-2')->sole()->tags->pluck('name')->all())->toBe(['Texas']);
    });
});

describe('dealer lists', function () {
    test('imports dealer list columns and keeps same-named dealerships in different states apart', function () {
        Storage::fake('local');
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/dealer-list.csv')));

        ProcessImport::dispatch($import);

        expect($import->refresh()->processed_rows)->toBe(4);
        expect(Contact::where('email', 'maria@capitolhonda.com')->sole())
            ->first_name->toBe('Maria')
            ->last_name->toBe('Lopez')
            ->email_status->toBe(EmailStatus::Valid)
            ->company->domain->toBe('capitolhonda.com');
        expect(Company::where('name', 'Capitol Honda')->sole())
            ->city->toBe('Austin')
            ->state->toBe('TX');

        $mainStreet = Company::where('name', 'Main Street Motors')->orderBy('state')->get();
        expect($mainStreet->pluck('state')->all())->toBe(['OK', 'TX']);
        expect($mainStreet->firstWhere('state', 'TX')->contacts()->orderBy('first_name')->pluck('first_name')->all())->toBe(['Beth', 'Carl']);
        expect(Contact::where('first_name', 'Alan')->sole()->company->state)->toBe('OK');
    });

    test('records the research details on an Imported timeline entry', function () {
        Storage::fake('local');
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/dealer-list.csv')), 'Texas dealers');

        ProcessImport::dispatch($import);

        expect(Contact::where('email', 'maria@capitolhonda.com')->sole()->activities()->sole())
            ->type->toBe(ActivityType::Imported)
            ->user_id->toBe($import->user_id)
            ->payload->toBe([
                'import_id' => $import->id,
                'source_list' => 'Texas dealers',
                'email_status' => 'Verified',
                'source_type' => 'Dealer website',
                'source_url' => 'https://capitolhonda.com/staff',
                'research_date' => '2026-09-01',
                'notes' => 'Prefers morning calls',
            ]);
    });

    test('drops source URLs that are not http links', function () {
        Storage::fake('local');
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/dealer-list.csv')));

        ProcessImport::dispatch($import);

        expect(Contact::where('first_name', 'Alan')->sole()->activities()->sole()->payload)
            ->not->toHaveKey('source_url')
            ->toHaveKey('source_type', 'LinkedIn');
    });
});

describe('re-importing existing contacts', function () {
    test('a bounced status replaces a valid one, but other fields only fill blanks', function () {
        Storage::fake('local');
        $contact = Contact::factory()->withEmailStatus(EmailStatus::Valid)->create([
            'email' => 'sam@summitchevy.com',
            'title' => 'Dealer Principal',
            'seniority' => null,
        ]);
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')));

        ProcessImport::dispatch($import);

        expect($contact->refresh())
            ->email_status->toBe(EmailStatus::Bounced)
            ->title->toBe('Dealer Principal')
            ->seniority->toBe('owner')
            ->apollo_contact_id->toBe('contact-3');
    });

    test('a risky status does not downgrade a valid one', function () {
        Storage::fake('local');
        $contact = Contact::factory()->withEmailStatus(EmailStatus::Valid)->create(['email' => 'lee@lakesideford.com']);
        $import = importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')));

        ProcessImport::dispatch($import);

        expect($contact->refresh()->email_status)->toBe(EmailStatus::Valid);
    });

    test('matches a contact without an email by Apollo contact id and adds an Imported entry for each list', function () {
        Storage::fake('local');
        $contact = Contact::factory()->withoutEmail()->create(['apollo_contact_id' => 'contact-4']);
        $tag = Tag::factory()->create(['name' => 'Existing']);
        $contact->tags()->attach($tag);

        ProcessImport::dispatch(importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')), 'First list'));
        ProcessImport::dispatch(importFromCsv(file_get_contents(base_path('tests/Fixtures/imports/apollo-export.csv')), 'Second list'));

        expect(Contact::where('apollo_contact_id', 'contact-4')->count())->toBe(1);
        expect($contact->activities()->orderBy('id')->get()->pluck('payload.source_list')->all())->toBe(['First list', 'Second list']);
        expect($contact->tags()->pluck('name')->all())->toBe(['Existing']);
    });
});
