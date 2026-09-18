<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;

test('replaces the contact tags with the given tags', function () {
    [$kept, $removed, $added] = Tag::factory()->count(3)->create();
    $contact = Contact::factory()->hasAttached([$kept, $removed])->create();

    $response = $this->actingAs(User::factory()->create())
        ->put(route('contacts.tags.update', $contact), ['tag_ids' => [$kept->id, $added->id]]);

    $response->assertRedirect();
    expect($contact->tags()->orderBy('tags.id')->pluck('tags.id')->all())->toBe([$kept->id, $added->id]);
});

test('removes every tag when given an empty list', function () {
    $contact = Contact::factory()->hasAttached(Tag::factory()->count(2))->create();

    $this->actingAs(User::factory()->create())
        ->putJson(route('contacts.tags.update', $contact), ['tag_ids' => []]);

    $this->assertDatabaseEmpty('contact_tag');
});

test('rejects tags that do not exist', function () {
    $tag = Tag::factory()->create();
    $contact = Contact::factory()->hasAttached($tag)->create();

    $response = $this->actingAs(User::factory()->create())
        ->put(route('contacts.tags.update', $contact), ['tag_ids' => [$tag->id + 1]]);

    $response->assertSessionHasErrors('tag_ids.0');
    expect($contact->tags()->pluck('tags.id')->all())->toBe([$tag->id]);
});
