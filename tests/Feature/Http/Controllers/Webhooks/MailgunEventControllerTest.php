<?php

use App\Enums\EmailEventType;
use App\Jobs\ProcessEmailEvent;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.mailgun.webhook_signing_key' => 'test-signing-key']);
});

/**
 * @param  array<string, mixed>  $eventData
 */
function postMailgunEvent(array $eventData): TestResponse
{
    return test()->postJson(route('webhooks.mailgun.events'), [
        'signature' => mailgunSignature(),
        'event-data' => [
            'id' => 'event-'.uniqid(),
            'timestamp' => 1_789_000_000.5,
            'message' => ['headers' => ['message-id' => 'abc@outreach.example.com']],
            ...$eventData,
        ],
    ]);
}

test('stores the event for the contact in its variables and queues it for processing', function () {
    Queue::fake([ProcessEmailEvent::class]);
    $contact = Contact::factory()->create();

    $response = postMailgunEvent([
        'id' => 'event-1',
        'event' => 'failed',
        'severity' => 'permanent',
        'user-variables' => ['contact_id' => (string) $contact->id],
    ]);

    $response->assertOk();
    $event = EmailEvent::sole();
    expect($event)
        ->contact_id->toBe($contact->id)
        ->event_type->toBe(EmailEventType::Bounced)
        ->provider_event_id->toBe('event-1')
        ->message_id->toBe('abc@outreach.example.com')
        ->occurred_at->timestamp->toBe(1_789_000_000);
    Queue::assertPushed(ProcessEmailEvent::class, fn (ProcessEmailEvent $job): bool => $job->emailEvent->is($event));
});

test('finds the contact from the email the event refers to', function () {
    Queue::fake([ProcessEmailEvent::class]);
    $contact = Contact::factory()->create();
    EmailEvent::factory()->for($contact)->create(['event_type' => EmailEventType::Sent, 'message_id' => 'abc@outreach.example.com', 'provider_event_id' => null]);

    postMailgunEvent(['event' => 'opened']);

    expect(EmailEvent::where('event_type', EmailEventType::Opened)->sole()->contact_id)->toBe($contact->id);
});

test('ignores a duplicate delivery of the same event', function () {
    Queue::fake([ProcessEmailEvent::class]);
    $contact = Contact::factory()->create();
    $event = ['id' => 'event-1', 'event' => 'opened', 'user-variables' => ['contact_id' => (string) $contact->id]];

    postMailgunEvent($event);
    postMailgunEvent($event)->assertOk();

    expect(EmailEvent::count())->toBe(1);
    Queue::assertPushed(ProcessEmailEvent::class, 1);
});

test('ignores events DealFlow does not act on', function (array $event) {
    Queue::fake([ProcessEmailEvent::class]);
    $contact = Contact::factory()->create();

    postMailgunEvent([...$event, 'user-variables' => ['contact_id' => (string) $contact->id]])->assertOk();

    $this->assertDatabaseEmpty('email_events');
    Queue::assertNothingPushed();
})->with([
    'accepted' => [['event' => 'accepted']],
    'temporary failure' => [['event' => 'failed', 'severity' => 'temporary']],
]);

test('ignores events for unknown recipients', function () {
    Queue::fake([ProcessEmailEvent::class]);

    postMailgunEvent(['event' => 'delivered', 'recipient' => 'stranger@example.com'])->assertOk();

    $this->assertDatabaseEmpty('email_events');
});
