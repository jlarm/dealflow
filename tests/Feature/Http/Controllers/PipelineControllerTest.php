<?php

use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('pipeline.index'));

    $response->assertRedirect(route('login'));
});

test('groups contacts into stage columns with the highest scores first', function () {
    $low = Contact::factory()->withStatus(ContactStatus::Qualified)->create(['score' => 10]);
    $high = Contact::factory()->withStatus(ContactStatus::Qualified)->create(['score' => 90]);
    $won = Contact::factory()->withStatus(ContactStatus::Won)->create();

    $response = $this->actingAs(User::factory()->create())->get(route('pipeline.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('pipeline/index')
        ->has('statuses', count(ContactStatus::cases()))
        ->where('stage_qualified.data.0.id', $high->id)
        ->where('stage_qualified.data.1.id', $low->id)
        ->where('stage_won.data.0.id', $won->id)
        ->has('stage_new.data', 0)
        ->where('counts', [
            'new' => 0,
            'contacted' => 0,
            'replied' => 0,
            'qualified' => 2,
            'meeting_booked' => 0,
            'won' => 1,
            'lost' => 0,
        ]));
});

test('loads the next page of a single column from its cursor', function () {
    $user = User::factory()->create();
    Contact::factory()->count(21)->withStatus(ContactStatus::New)->sequence(fn ($sequence) => ['score' => 100 - $sequence->index])->create();
    $nextCursor = $this->actingAs($user)->get(route('pipeline.index'))->inertiaProps('stage_new.meta.next_cursor');

    $response = $this->actingAs($user)->get(route('pipeline.index', ['new' => $nextCursor]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('stage_new.data', 1)
        ->where('stage_new.data.0.score', 80)
        ->where('stage_new.meta.next_cursor', null)
        ->where('counts.new', 21));
});

test('does not skip a contact when a loaded card moves out of the column', function () {
    $user = User::factory()->create();
    $contacts = Contact::factory()->count(21)->withStatus(ContactStatus::New)->sequence(fn ($sequence) => ['score' => 100 - $sequence->index])->create();
    $nextCursor = $this->actingAs($user)->get(route('pipeline.index'))->inertiaProps('stage_new.meta.next_cursor');
    $contacts->first()->update(['status' => ContactStatus::Won]);

    $response = $this->actingAs($user)->get(route('pipeline.index', ['new' => $nextCursor]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('stage_new.data', 1)
        ->where('stage_new.data.0.id', $contacts->last()->id));
});
