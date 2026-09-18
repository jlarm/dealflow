<?php

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Enums\EmailEventType;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailEvent;
use App\Models\Import;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('shows headline numbers for the last 7 and 30 days', function () {
    $this->freezeSecond();
    [$replied, $emailed] = Contact::factory()->count(2)->verified()->create();
    Contact::factory()->create(['email_status' => null]);
    EmailEvent::factory()->for($replied)->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now()->subDays(2)]);
    EmailEvent::factory()->for($emailed)->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now()->subDays(20)]);
    EmailEvent::factory()->for($emailed)->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now()->subDays(40)]);
    EmailEvent::factory()->for($emailed)->create(['event_type' => EmailEventType::Bounced, 'occurred_at' => now()->subDays(20)]);
    Activity::factory()->for($replied)->create(['type' => ActivityType::EmailReplied, 'payload' => ['body' => 'Yes']]);
    Activity::factory()->for($emailed)->create(['type' => ActivityType::EmailReplied, 'payload' => ['body' => 'Out of office', 'auto_reply' => true]]);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('stats', [
            'contacts' => 3,
            'contactable' => 2,
            'sent_7_days' => 1,
            'sent_30_days' => 2,
            'contacted_30_days' => 2,
            'replies_30_days' => 1,
            'bounces_30_days' => 1,
        ]));
});

test('counts campaign emails per day in the outreach timezone', function () {
    config(['outreach.timezone' => 'America/Chicago']);
    $this->travelTo(now('America/Chicago')->setTime(12, 0));
    EmailEvent::factory()->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now('America/Chicago')->setTime(23, 30)->subDay()]);
    EmailEvent::factory()->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now('America/Chicago')->setTime(8, 0)]);

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('sendsByDay', 14)
        ->where('sendsByDay.12', ['date' => now('America/Chicago')->subDay()->toDateString(), 'count' => 1])
        ->where('sendsByDay.13', ['date' => now('America/Chicago')->toDateString(), 'count' => 1]));
});

test('shows how many contacts are in each pipeline stage', function () {
    Contact::factory()->count(2)->withStatus(ContactStatus::Qualified)->create();

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->has('pipeline', 7)
        ->where('pipeline.3.value', 'qualified')
        ->where('pipeline.3.count', 2)
        ->where('pipeline.0.count', 0));
});

test('loads running campaign results, real replies, and recent imports after the page', function () {
    $campaign = Campaign::factory()->active()->create();
    Campaign::factory()->create();
    [$replied, $bounced, $waiting] = Contact::factory()->count(3)->create();
    $campaign->contacts()->attach($replied, ['sequence_step' => 2, 'stopped_at' => now(), 'stop_reason' => 'replied']);
    $campaign->contacts()->attach($bounced, ['sequence_step' => 1, 'stopped_at' => now(), 'stop_reason' => 'bounced']);
    $campaign->contacts()->attach($waiting, ['sequence_step' => 0]);
    $reply = Activity::factory()->for($replied)->create(['type' => ActivityType::EmailReplied, 'payload' => ['body' => 'Call me Tuesday']]);
    Activity::factory()->for($bounced)->create(['type' => ActivityType::EmailReplied, 'payload' => ['auto_reply' => true]]);
    Import::factory()->completed()->create();

    $response = $this->actingAs(User::factory()->create())->get(route('dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->missing('campaigns')
        ->loadDeferredProps('activity', fn (Assert $reload) => $reload
            ->has('campaigns', 1)
            ->where('campaigns.0', [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => 'active',
                'status_label' => 'Active',
                'enrolled' => 3,
                'contacted' => 2,
                'sent' => 3,
                'replied' => 1,
                'bounced' => 1,
            ])
            ->has('recentReplies', 1)
            ->where('recentReplies.0.id', $reply->id)
            ->where('recentReplies.0.body', 'Call me Tuesday')
            ->has('recentImports', 1)));
});
