<?php

use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailEvent;
use App\Models\Tag;

test('new contacts start in the new stage with a zero score before being refreshed', function () {
    $contact = Contact::create(['email' => 'jane@example.com']);

    expect($contact->status)->toBe(ContactStatus::New)
        ->and($contact->score)->toBe(0);
});

test('the status scope returns only contacts in the given stage', function () {
    $qualified = Contact::factory()->withStatus(ContactStatus::Qualified)->create();
    Contact::factory()->withStatus(ContactStatus::Won)->create();

    $contacts = Contact::withStatus(ContactStatus::Qualified)->pluck('id');

    expect($contacts->all())->toBe([$qualified->id]);
});

test('only verified, subscribed contacts are contactable', function () {
    $verified = Contact::factory()->withEmailStatus(EmailStatus::Valid)->create();
    Contact::factory()->create(['email_status' => null]);
    Contact::factory()->withEmailStatus(EmailStatus::Risky)->create();
    Contact::factory()->withEmailStatus(EmailStatus::Invalid)->create();
    Contact::factory()->withEmailStatus(EmailStatus::Bounced)->create();
    Contact::factory()->withEmailStatus(EmailStatus::Complained)->create();
    Contact::factory()->withEmailStatus(EmailStatus::Valid)->unsubscribed()->create();
    Contact::factory()->withEmailStatus(EmailStatus::Valid)->withoutEmail()->create();

    $contactable = Contact::contactable()->pluck('id');

    expect($contactable->all())->toBe([$verified->id]);
});

test('a contact is contactable only with a verified address and no unsubscribe', function () {
    expect(Contact::factory()->withEmailStatus(EmailStatus::Valid)->make()->isContactable())->toBeTrue()
        ->and(Contact::factory()->withEmailStatus(EmailStatus::Risky)->make()->isContactable())->toBeFalse()
        ->and(Contact::factory()->withEmailStatus(EmailStatus::Valid)->unsubscribed()->make()->isContactable())->toBeFalse();
});

test('deleting a company keeps its contacts without a company', function () {
    $contact = Contact::factory()->for(Company::factory())->create();

    $contact->company->delete();

    expect($contact->fresh()->company_id)->toBeNull();
});

test('deleting a contact removes its timeline, email events, tags, and enrollments', function () {
    $contact = Contact::factory()->create();
    $tag = Tag::factory()->create();
    $campaign = Campaign::factory()->create();
    Activity::factory()->for($contact)->create();
    EmailEvent::factory()->for($contact)->create();
    $contact->tags()->attach($tag);
    $contact->campaigns()->attach($campaign);

    $contact->delete();

    $this->assertDatabaseEmpty('activities');
    $this->assertDatabaseEmpty('email_events');
    $this->assertDatabaseEmpty('contact_tag');
    $this->assertDatabaseEmpty('campaign_contact');
    $this->assertModelExists($tag);
    $this->assertModelExists($campaign);
});

test('new contacts join the bottom of their stage on the board', function () {
    $existing = Contact::factory()->withStatus(ContactStatus::New)->create();

    $new = Contact::factory()->withStatus(ContactStatus::New)->create();

    expect($new->pipeline_position)->toBeGreaterThan($existing->pipeline_position);
});
