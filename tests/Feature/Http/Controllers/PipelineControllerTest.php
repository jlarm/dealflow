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

test('loads the next page of a single column', function () {
    Contact::factory()->count(21)->withStatus(ContactStatus::New)->sequence(fn ($sequence) => ['score' => 100 - $sequence->index])->create();

    $response = $this->actingAs(User::factory()->create())->get(route('pipeline.index', ['new' => 2]));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('stage_new.data', 1)
        ->where('stage_new.data.0.score', 80)
        ->where('counts.new', 21));
});
