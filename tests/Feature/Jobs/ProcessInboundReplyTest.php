<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\EmailEventType;
use App\Jobs\ProcessInboundReply;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\EmailEvent;

/**
 * @param  array<string, mixed>  $overrides
 * @return array{from: string, subject: string, text: string, reply_message_id: string|null, referenced_message_ids: list<string>, auto_reply: bool}
 */
function reply(array $overrides = []): array
{
    return [
        'from' => 'someone@example.com',
        'subject' => 'Re: Quick question',
        'text' => 'Sure, call me Tuesday.',
        'reply_message_id' => 'reply-1@example.com',
        'referenced_message_ids' => ['abc@outreach.example.com'],
        'auto_reply' => false,
        ...$overrides,
    ];
}

/**
 * A contact in an active campaign who was sent the email with id abc@outreach.example.com.
 */
function contactWhoWasEmailed(ContactStatus $status = ContactStatus::Contacted): Contact
{
    $contact = Contact::factory()->verified()->withStatus($status)->create();
    $campaign = Campaign::factory()->active()->create();
    $campaign->contacts()->attach($contact, ['next_send_at' => now()->addDay()]);
    EmailEvent::factory()->for($contact)->create([
        'event_type' => EmailEventType::Sent,
        'message_id' => 'abc@outreach.example.com',
        'provider_event_id' => null,
        'payload' => ['campaign_id' => $campaign->id],
    ]);

    return $contact;
}

test('a reply stops the contact\'s campaigns, moves them to Replied, and logs the reply', function () {
    $contact = contactWhoWasEmailed();

    ProcessInboundReply::dispatchSync(reply());

    expect(CampaignEnrollment::sole()->stop_reason)->toBe('replied');
    expect($contact->refresh()->status)->toBe(ContactStatus::Replied);
    expect($contact->activities()->where('type', ActivityType::EmailReplied)->sole()->payload)->toMatchArray([
        'subject' => 'Re: Quick question',
        'body' => 'Sure, call me Tuesday.',
        'reply_message_id' => 'reply-1@example.com',
    ]);
});

test('matches a reply to the contact by sender when it references no known email', function () {
    $contact = Contact::factory()->withStatus(ContactStatus::Contacted)->create(['email' => 'dana@lakesideford.com']);

    ProcessInboundReply::dispatchSync(reply(['from' => 'dana@lakesideford.com', 'referenced_message_ids' => []]));

    expect($contact->refresh()->status)->toBe(ContactStatus::Replied);
});

test('credits the reply to the contact who sent it, even when it quotes an email sent to someone else', function () {
    $emailed = contactWhoWasEmailed();
    $sender = Contact::factory()->withStatus(ContactStatus::Contacted)->create(['email' => 'colleague@lakesideford.com']);

    ProcessInboundReply::dispatchSync(reply(['from' => 'colleague@lakesideford.com']));

    expect($sender->refresh()->status)->toBe(ContactStatus::Replied);
    expect($emailed->refresh()->status)->toBe(ContactStatus::Contacted);
    expect(CampaignEnrollment::sole()->isActive())->toBeTrue();
});

test('credits a reply from someone who is not a contact to the contact who was emailed', function () {
    $emailed = contactWhoWasEmailed();

    ProcessInboundReply::dispatchSync(reply(['from' => 'assistant@lakesideford.com']));

    expect($emailed->refresh()->status)->toBe(ContactStatus::Replied);
});

test('does not move a contact who is further along the pipeline back to Replied', function () {
    $contact = contactWhoWasEmailed(ContactStatus::MeetingBooked);

    ProcessInboundReply::dispatchSync(reply());

    expect($contact->refresh()->status)->toBe(ContactStatus::MeetingBooked);
    expect(CampaignEnrollment::sole()->stop_reason)->toBe('replied');
});

test('an automatic reply is logged but does not stop the campaign or change the stage', function () {
    $contact = contactWhoWasEmailed();

    ProcessInboundReply::dispatchSync(reply(['auto_reply' => true]));

    expect(CampaignEnrollment::sole()->isActive())->toBeTrue();
    expect($contact->refresh()->status)->toBe(ContactStatus::Contacted);
    expect($contact->activities()->sole()->payload)->toMatchArray(['auto_reply' => true]);
});

test('the same reply delivered twice is recorded once', function () {
    $contact = contactWhoWasEmailed();

    ProcessInboundReply::dispatchSync(reply());
    ProcessInboundReply::dispatchSync(reply());

    expect($contact->activities()->where('type', ActivityType::EmailReplied)->count())->toBe(1);
});

test('ignores replies from people who are not contacts', function () {
    ProcessInboundReply::dispatchSync(reply(['from' => 'stranger@example.com', 'referenced_message_ids' => []]));

    $this->assertDatabaseEmpty('activities');
});
