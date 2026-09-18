<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;

test('moves the contact to the new stage and logs the change on its timeline', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->withStatus(ContactStatus::Contacted)->create();

    $response = $this->actingAs($user)
        ->from(route('contacts.show', $contact))
        ->patch(route('contacts.status.update', $contact), ['status' => 'qualified']);

    $response->assertRedirect(route('contacts.show', $contact));
    expect($contact->refresh()->status)->toBe(ContactStatus::Qualified);
    expect($contact->activities()->sole())
        ->type->toBe(ActivityType::StatusChange)
        ->payload->toBe(['from' => 'contacted', 'to' => 'qualified'])
        ->user_id->toBe($user->id);
});

test('does not log an activity when the status is unchanged', function () {
    $contact = Contact::factory()->withStatus(ContactStatus::Qualified)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('contacts.status.update', $contact), ['status' => 'qualified']);

    $this->assertDatabaseEmpty('activities');
});

test('rejects an unknown status', function () {
    $contact = Contact::factory()->withStatus(ContactStatus::New)->create();

    $response = $this->actingAs(User::factory()->create())
        ->patch(route('contacts.status.update', $contact), ['status' => 'archived']);

    $response->assertSessionHasErrors('status');
    expect($contact->refresh()->status)->toBe(ContactStatus::New);
    $this->assertDatabaseEmpty('activities');
});

test('a contact moved to a new stage goes to the top of it on the board', function () {
    $existing = Contact::factory()->withStatus(ContactStatus::Qualified)->create();
    $contact = Contact::factory()->withStatus(ContactStatus::Contacted)->create();

    $this->actingAs(User::factory()->create())->patch(route('contacts.status.update', $contact), ['status' => 'qualified']);

    expect($contact->refresh()->pipeline_position)->toBeLessThan($existing->pipeline_position);
});
