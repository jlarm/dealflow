<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('lists tags alphabetically with their contact counts', function () {
    Tag::factory()->create(['name' => 'Zeta']);
    Tag::factory()->has(Contact::factory()->count(2))->create(['name' => 'Alpha']);

    $response = $this->actingAs(User::factory()->create())->get(route('tags.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('tags/index')
        ->where('tags.0.name', 'Alpha')
        ->where('tags.0.contacts_count', 2)
        ->where('tags.1.name', 'Zeta'));
});

test('creates a tag', function () {
    $response = $this->actingAs(User::factory()->create())
        ->from(route('tags.index'))
        ->post(route('tags.store'), ['name' => 'Hot Lead']);

    $response->assertRedirect(route('tags.index'));
    $this->assertDatabaseHas('tags', ['name' => 'Hot Lead']);
});

test('rejects a duplicate tag name', function () {
    Tag::factory()->create(['name' => 'Hot Lead']);

    $response = $this->actingAs(User::factory()->create())->post(route('tags.store'), ['name' => 'Hot Lead']);

    $response->assertSessionHasErrors('name');
    $this->assertDatabaseCount('tags', 1);
});

test('renames a tag', function () {
    $tag = Tag::factory()->create(['name' => 'Old']);

    $this->actingAs(User::factory()->create())->put(route('tags.update', $tag), ['name' => 'New']);

    expect($tag->refresh()->name)->toBe('New');
});

test('deleting a tag removes it from contacts but keeps the contacts', function () {
    $tag = Tag::factory()->create();
    $contact = Contact::factory()->hasAttached($tag)->create();

    $this->actingAs(User::factory()->create())->delete(route('tags.destroy', $tag));

    $this->assertModelMissing($tag);
    $this->assertModelExists($contact);
    $this->assertDatabaseEmpty('contact_tag');
});
