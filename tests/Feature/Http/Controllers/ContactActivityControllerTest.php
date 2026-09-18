<?php

use App\Enums\ActivityType;
use App\Models\Contact;
use App\Models\User;

test('logs a call and marks the contact as contacted', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $contact = Contact::factory()->create(['last_contacted_at' => null]);

    $response = $this->actingAs($user)->post(route('contacts.activities.store', $contact), [
        'type' => 'call',
        'body' => 'Spoke with the GM.',
    ]);

    $response->assertRedirect()->assertInertiaFlash('toast.message', 'Call logged.');
    expect($contact->activities()->sole())
        ->type->toBe(ActivityType::Call)
        ->payload->toBe(['body' => 'Spoke with the GM.'])
        ->user_id->toBe($user->id);
    expect($contact->refresh()->last_contacted_at)->toEqual(now());
});

test('logs a note without changing the last contacted date', function () {
    $contact = Contact::factory()->create(['last_contacted_at' => null]);

    $this->actingAs(User::factory()->create())->post(route('contacts.activities.store', $contact), [
        'type' => 'note',
        'body' => 'Prefers email.',
    ]);

    expect($contact->activities()->sole()->type)->toBe(ActivityType::Note);
    expect($contact->refresh()->last_contacted_at)->toBeNull();
});

test('rejects activity types that cannot be logged by hand', function () {
    $contact = Contact::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('contacts.activities.store', $contact), [
        'type' => 'email_replied',
        'body' => 'Fake reply',
    ]);

    $response->assertSessionHasErrors('type');
    $this->assertDatabaseEmpty('activities');
});

test('requires a body', function () {
    $contact = Contact::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('contacts.activities.store', $contact), [
        'type' => 'note',
        'body' => '',
    ]);

    $response->assertSessionHasErrors('body');
    $this->assertDatabaseEmpty('activities');
});
