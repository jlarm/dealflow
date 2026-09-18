<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;

/**
 * Create contacts in a stage at the given board positions.
 *
 * @return list<Contact>
 */
function cardsIn(ContactStatus $status, float ...$positions): array
{
    return array_map(
        fn (float $position): Contact => Contact::factory()->withStatus($status)->create(['pipeline_position' => $position]),
        $positions,
    );
}

/**
 * @return list<int>
 */
function boardOrder(ContactStatus $status): array
{
    return Contact::query()->withStatus($status)->orderBy('pipeline_position')->orderBy('id')->pluck('id')->all();
}

test('drops a contact between two cards in another stage and logs the stage change', function () {
    $user = User::factory()->create();
    [$top, $bottom] = cardsIn(ContactStatus::Qualified, 1024, 2048);
    $moving = Contact::factory()->withStatus(ContactStatus::Contacted)->create();

    $response = $this->actingAs($user)
        ->from(route('pipeline.index'))
        ->patch(route('pipeline.contacts.update', $moving), ['status' => 'qualified', 'above_id' => $top->id, 'below_id' => $bottom->id]);

    $response->assertRedirect(route('pipeline.index'));
    expect(boardOrder(ContactStatus::Qualified))->toBe([$top->id, $moving->id, $bottom->id]);
    expect($moving->activities()->sole())
        ->type->toBe(ActivityType::StatusChange)
        ->user_id->toBe($user->id);
});

test('drops a contact at the top of a stage', function () {
    [$first] = cardsIn(ContactStatus::Qualified, 1024, 2048);
    $moving = Contact::factory()->withStatus(ContactStatus::New)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('pipeline.contacts.update', $moving), ['status' => 'qualified', 'below_id' => $first->id]);

    expect(boardOrder(ContactStatus::Qualified)[0])->toBe($moving->id);
});

test('dropping below the last loaded card keeps it above cards that have not loaded yet', function () {
    [$lastLoaded, $notLoaded] = cardsIn(ContactStatus::Qualified, 1024, 2048);
    $moving = Contact::factory()->withStatus(ContactStatus::New)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('pipeline.contacts.update', $moving), ['status' => 'qualified', 'above_id' => $lastLoaded->id]);

    expect(boardOrder(ContactStatus::Qualified))->toBe([$lastLoaded->id, $moving->id, $notLoaded->id]);
});

test('reorders a contact within its stage without logging a stage change', function () {
    [$first, $second, $third] = cardsIn(ContactStatus::Qualified, 1024, 2048, 3072);

    $this->actingAs(User::factory()->create())
        ->patch(route('pipeline.contacts.update', $third), ['status' => 'qualified', 'above_id' => $first->id, 'below_id' => $second->id]);

    expect(boardOrder(ContactStatus::Qualified))->toBe([$first->id, $third->id, $second->id]);
    $this->assertDatabaseEmpty('activities');
});

test('renumbers the stage when repeated drops leave no room between two cards', function () {
    [$first, $second] = cardsIn(ContactStatus::Qualified, 1024, 1024.0001);
    $moving = Contact::factory()->withStatus(ContactStatus::New)->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('pipeline.contacts.update', $moving), ['status' => 'qualified', 'above_id' => $first->id, 'below_id' => $second->id]);

    expect(boardOrder(ContactStatus::Qualified))->toBe([$first->id, $moving->id, $second->id]);
    expect($second->refresh()->pipeline_position - $first->refresh()->pipeline_position)->toBeGreaterThan(1);
});

test('rejects an unknown stage', function () {
    $contact = Contact::factory()->withStatus(ContactStatus::New)->create();

    $response = $this->actingAs(User::factory()->create())
        ->patch(route('pipeline.contacts.update', $contact), ['status' => 'archived']);

    $response->assertSessionHasErrors('status');
    expect($contact->refresh()->status)->toBe(ContactStatus::New);
});
