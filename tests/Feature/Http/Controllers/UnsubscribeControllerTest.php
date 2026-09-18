<?php

use App\Enums\ActivityType;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

test('opening the link shows a confirmation without unsubscribing', function () {
    $contact = Contact::factory()->verified()->create(['email' => 'jane.doe@acme.com']);

    $response = $this->get(URL::signedRoute('unsubscribe.show', $contact));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('public/unsubscribe')
        ->where('email', 'ja******@acme.com')
        ->where('unsubscribed', false)
        ->has('action'));
    expect($contact->refresh()->unsubscribed_at)->toBeNull();
});

test('rejects links without a valid signature', function () {
    $contact = Contact::factory()->create();

    $response = $this->get(route('unsubscribe.show', $contact));

    $response->assertForbidden();
});

test('one-click unsubscribe stops every campaign and logs it on the timeline', function () {
    $contact = Contact::factory()->verified()->create();
    Campaign::factory()->active()->create()->contacts()->attach($contact, ['next_send_at' => now()]);

    $response = $this->post(URL::signedRoute('unsubscribe.store', $contact), ['List-Unsubscribe' => 'One-Click']);

    $response->assertNoContent();
    expect($contact->refresh()->unsubscribed_at)->not->toBeNull();
    expect(CampaignEnrollment::sole())
        ->stop_reason->toBe('unsubscribed')
        ->next_send_at->toBeNull();
    expect($contact->activities()->sole())
        ->type->toBe(ActivityType::Unsubscribed)
        ->payload->toBe(['via' => 'one_click']);
});

test('unsubscribing twice keeps the first unsubscribe', function () {
    $contact = Contact::factory()->verified()->create();
    $url = URL::signedRoute('unsubscribe.store', $contact);

    $this->post($url);
    $this->post($url);

    expect($contact->activities()->count())->toBe(1);
});
