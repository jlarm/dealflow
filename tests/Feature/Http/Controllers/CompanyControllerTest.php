<?php

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    test('lists companies matching the search with their contact counts', function () {
        $match = Company::factory()->has(Contact::factory()->count(2))->create(['name' => 'Lakeside Auto Group']);
        Company::factory()->create(['name' => 'Summit Trucks']);

        $response = $this->actingAs(User::factory()->create())->get(route('companies.index', ['search' => 'lakeside']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('companies/index')
            ->has('companies.data', 1)
            ->where('companies.data.0.id', $match->id)
            ->where('companies.data.0.contacts_count', 2));
    });
});

describe('store', function () {
    test('creates a company with the website reduced to its domain', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('companies.store'), [
            'name' => 'Acme Motors',
            'domain' => 'https://www.Acme-Motors.com/about',
            'city' => 'Austin',
            'state' => 'texas',
            'phone' => '512-555-0100',
        ]);

        $company = Company::sole();
        $response->assertRedirect(route('companies.show', $company));
        expect($company)
            ->domain->toBe('acme-motors.com')
            ->city->toBe('Austin')
            ->state->toBe('TX')
            ->phone->toBe('512-555-0100');
    });

    test('rejects a domain already used by another company once normalized', function () {
        Company::factory()->create(['domain' => 'acme.com']);

        $response = $this->actingAs(User::factory()->create())->post(route('companies.store'), [
            'name' => 'Acme Again',
            'domain' => 'www.acme.com',
        ]);

        $response->assertSessionHasErrors('domain');
        $this->assertDatabaseCount('companies', 1);
    });

    test('rejects a value that is not a domain', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('companies.store'), [
            'name' => 'Acme',
            'domain' => 'not a domain',
        ]);

        $response->assertSessionHasErrors(['domain' => 'Enter a domain like acme.com.']);
        $this->assertDatabaseEmpty('companies');
    });
});

describe('show', function () {
    test('shows the imported company details that have values', function () {
        $company = Company::factory()->create(['enrichment_data' => [
            'number_of_retail_locations' => '3',
            'keywords' => 'new cars, used cars',
            'technologies' => '',
            'sic_codes' => '5511',
        ]]);

        $response = $this->actingAs(User::factory()->create())->get(route('companies.show', $company));

        $response->assertInertia(fn (Assert $page) => $page->where('details', [
            ['label' => 'Retail locations', 'value' => '3'],
            ['label' => 'Keywords', 'value' => 'new cars, used cars'],
        ])->etc());
    });

    test('renders the company with only its own contacts', function () {
        $company = Company::factory()->create();
        $contact = Contact::factory()->for($company)->create();
        Contact::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('companies.show', $company));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('companies/show')
            ->where('company.id', $company->id)
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $contact->id));
    });
});

describe('update', function () {
    test('updates the company details', function () {
        $company = Company::factory()->create(['domain' => 'old.com']);

        $response = $this->actingAs(User::factory()->create())->put(route('companies.update', $company), [
            'name' => 'Renamed',
            'domain' => 'old.com',
        ]);

        $response->assertRedirect(route('companies.show', $company));
        expect($company->refresh()->name)->toBe('Renamed');
    });
});

describe('destroy', function () {
    test('deletes the company and redirects to the list', function () {
        $company = Company::factory()->create();

        $response = $this->actingAs(User::factory()->create())->delete(route('companies.destroy', $company));

        $response->assertRedirect(route('companies.index'));
        $this->assertModelMissing($company);
    });
});
