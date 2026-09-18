<?php

use App\Enums\ActivityType;
use App\Enums\EmailEventType;
use App\Enums\EmailStatus;
use App\Jobs\ProcessEmailEvent;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\EmailEvent;

/**
 * A verified contact who is part-way through an active campaign.
 */
function enrolledContact(): Contact
{
    $contact = Contact::factory()->verified()->create(['score' => 10]);
    Campaign::factory()->active()->create()->contacts()->attach($contact, ['next_send_at' => now()->addDay()]);

    return $contact;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function emailEvent(Contact $contact, EmailEventType $type, array $attributes = []): EmailEvent
{
    return EmailEvent::factory()->for($contact)->create(['event_type' => $type, 'message_id' => 'abc@outreach.example.com', ...$attributes]);
}

test('a bounce marks the address bounced, stops every campaign, and logs it', function () {
    $contact = enrolledContact();
    $event = emailEvent($contact, EmailEventType::Bounced, ['payload' => ['delivery-status' => ['description' => 'Mailbox does not exist']]]);

    ProcessEmailEvent::dispatchSync($event);

    expect($contact->refresh()->email_status)->toBe(EmailStatus::Bounced);
    expect(CampaignEnrollment::sole())->stop_reason->toBe('bounced');
    expect($event->refresh()->activity)
        ->type->toBe(ActivityType::EmailBounced)
        ->payload->toMatchArray(['body' => 'Mailbox does not exist']);
});

test('a spam complaint unsubscribes the contact and marks the address', function () {
    $contact = enrolledContact();

    ProcessEmailEvent::dispatchSync(emailEvent($contact, EmailEventType::Complained));

    expect($contact->refresh())
        ->email_status->toBe(EmailStatus::Complained)
        ->unsubscribed_at->not->toBeNull();
    expect(CampaignEnrollment::sole()->stop_reason)->toBe('unsubscribed');
    expect($contact->activities()->sole()->payload)->toBe(['via' => 'spam_complaint']);
});

test('an unsubscribe reported by Mailgun unsubscribes the contact', function () {
    $contact = enrolledContact();

    ProcessEmailEvent::dispatchSync(emailEvent($contact, EmailEventType::Unsubscribed));

    expect($contact->refresh()->unsubscribed_at)->not->toBeNull();
});

test('logs only the first open of an email and adds a point of score', function () {
    $contact = enrolledContact();
    $first = emailEvent($contact, EmailEventType::Opened);
    $second = emailEvent($contact, EmailEventType::Opened);

    ProcessEmailEvent::dispatchSync($first);
    ProcessEmailEvent::dispatchSync($second);

    expect($contact->activities()->sole()->type)->toBe(ActivityType::EmailOpened);
    expect($contact->refresh()->score)->toBe(11);
    expect(CampaignEnrollment::sole()->isActive())->toBeTrue();
});

test('logs the first click with its link and adds two points of score', function () {
    $contact = enrolledContact();

    ProcessEmailEvent::dispatchSync(emailEvent($contact, EmailEventType::Clicked, ['payload' => ['url' => 'https://example.com/demo']]));

    expect($contact->activities()->sole())
        ->type->toBe(ActivityType::EmailClicked)
        ->payload->toMatchArray(['url' => 'https://example.com/demo']);
    expect($contact->refresh()->score)->toBe(12);
});
