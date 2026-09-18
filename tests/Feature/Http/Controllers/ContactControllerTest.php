<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('contacts.index'));

    $response->assertRedirect(route('login'));
});

describe('index', function () {
    test('lists contacts matching the search, status, and tag filters', function () {
        $tag = Tag::factory()->create();
        $match = Contact::factory()->withStatus(ContactStatus::Qualified)->hasAttached($tag)->create(['first_name' => 'Dana']);
        Contact::factory()->withStatus(ContactStatus::New)->hasAttached($tag)->create(['first_name' => 'Dana']);
        Contact::factory()->withStatus(ContactStatus::Qualified)->create(['first_name' => 'Dana']);
        Contact::factory()->withStatus(ContactStatus::Qualified)->hasAttached($tag)->create(['first_name' => 'Morgan']);

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.index', [
            'search' => 'dana',
            'status' => 'qualified',
            'tag' => $tag->id,
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('contacts/index')
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $match->id)
            ->where('filters.status', 'qualified'));
    });

    test('searches by company name', function () {
        $match = Contact::factory()->for(Company::factory()->state(['name' => 'Northside Motors']))->create();
        Contact::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.index', ['search' => 'northside']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $match->id));
    });

    test('filters by dealership state and seniority', function () {
        $texasOwner = Contact::factory()->for(Company::factory()->state(['state' => 'TX']))->create(['seniority' => 'owner']);
        Contact::factory()->for(Company::factory()->state(['state' => 'TX']))->create(['seniority' => 'manager']);
        Contact::factory()->for(Company::factory()->state(['state' => 'OK']))->create(['seniority' => 'owner']);

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.index', ['state' => 'TX', 'seniority' => 'owner']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('contacts.data.0.id', $texasOwner->id)
            ->where('contacts.data.0.company.state', 'TX')
            ->where('states', ['OK', 'TX'])
            ->where('seniorities', ['manager', 'owner']));
    });

    test('sorts by name in the requested direction', function () {
        Contact::factory()->create(['first_name' => 'Ann', 'last_name' => 'Young']);
        Contact::factory()->create(['first_name' => 'Ben', 'last_name' => 'Adams']);

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.index', ['sort' => 'name', 'direction' => 'asc']));

        $response->assertInertia(fn (Assert $page) => $page
            ->where('contacts.data.0.name', 'Ben Adams')
            ->where('contacts.data.1.name', 'Ann Young'));
    });

    test('falls back to the default sort when the sort column is not allowed', function () {
        Contact::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.index', ['sort' => 'email; drop table contacts']));

        $response->assertInertia(fn (Assert $page) => $page
            ->has('contacts.data', 1)
            ->where('filters.sort', 'created_at'));
    });
});

describe('store', function () {
    test('creates a contact with a normalized email and redirects to it', function () {
        $company = Company::factory()->create();

        $response = $this->actingAs(User::factory()->create())->post(route('contacts.store'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => '  Jane.Doe@Example.COM ',
            'company_id' => $company->id,
            'seniority' => 'owner',
            'departments' => 'Sales, Operations',
            'score' => 40,
        ]);

        $contact = Contact::sole();
        $response->assertRedirect(route('contacts.show', $contact))
            ->assertInertiaFlash('toast.type', 'success');
        expect($contact)
            ->email->toBe('jane.doe@example.com')
            ->company_id->toBe($company->id)
            ->seniority->toBe('owner')
            ->departments->toBe('Sales, Operations')
            ->status->toBe(ContactStatus::New);
    });

    test('requires a name or an email address', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('contacts.store'), [
            'phone' => '555-0100',
            'score' => 0,
        ]);

        $response->assertSessionHasErrors(['first_name' => 'Enter a name or an email address.']);
        $this->assertDatabaseEmpty('contacts');
    });

    test('rejects an email that already exists in a different case', function () {
        Contact::factory()->create(['email' => 'jane@example.com']);

        $response = $this->actingAs(User::factory()->create())->post(route('contacts.store'), [
            'email' => 'JANE@example.com',
            'score' => 0,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertDatabaseCount('contacts', 1);
    });
});

describe('show', function () {
    test('renders the contact and loads its timeline newest first after the page', function () {
        $contact = Contact::factory()->create();
        $older = Activity::factory()->for($contact)->create(['created_at' => now()->subDay()]);
        $newer = Activity::factory()->for($contact)->create(['type' => ActivityType::Call]);
        Activity::factory()->create();

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.show', $contact));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('contacts/show')
            ->where('contact.id', $contact->id)
            ->missing('activities')
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('activities.data', 2)
                ->where('activities.data.0.id', $newer->id)
                ->where('activities.data.1.id', $older->id)));
    });
});

describe('edit', function () {
    test('includes the current company so the form keeps it selected', function () {
        $contact = Contact::factory()->for(Company::factory())->create();

        $response = $this->actingAs(User::factory()->create())->get(route('contacts.edit', $contact));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('contacts/edit')
            ->where('contact.company_id', $contact->company_id));
    });
});

describe('update', function () {
    test('updates the contact details but not the pipeline status', function () {
        $contact = Contact::factory()->withStatus(ContactStatus::Contacted)->create();

        $response = $this->actingAs(User::factory()->create())->put(route('contacts.update', $contact), [
            'first_name' => 'Updated',
            'email' => $contact->email,
            'score' => 75,
            'status' => 'won',
        ]);

        $response->assertRedirect(route('contacts.show', $contact));
        expect($contact->refresh())
            ->first_name->toBe('Updated')
            ->score->toBe(75)
            ->status->toBe(ContactStatus::Contacted);
    });

    test('allows a contact to keep its own email address', function () {
        $contact = Contact::factory()->create(['email' => 'jane@example.com']);

        $response = $this->actingAs(User::factory()->create())->put(route('contacts.update', $contact), [
            'email' => 'jane@example.com',
            'score' => 10,
        ]);

        $response->assertSessionHasNoErrors();
    });
});

describe('destroy', function () {
    test('deletes the contact and redirects to the list', function () {
        $contact = Contact::factory()->create();

        $response = $this->actingAs(User::factory()->create())->delete(route('contacts.destroy', $contact));

        $response->assertRedirect(route('contacts.index'));
        $this->assertModelMissing($contact);
    });
});
