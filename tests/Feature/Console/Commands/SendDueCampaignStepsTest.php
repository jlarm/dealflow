<?php

use App\Enums\EmailEventType;
use App\Jobs\SendCampaignStep;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Support\Facades\Queue;

/**
 * Enroll new contacts in an active campaign so their first email is due.
 */
function dueEnrollments(int $count): void
{
    $campaign = Campaign::factory()->active()->create();

    Contact::factory()->count($count)->verified()->create()
        ->each(fn (Contact $contact) => $campaign->contacts()->attach($contact, ['next_send_at' => now()->subHour()]));
}

test('queues the due emails during the sending window', function () {
    $this->travelTo(now('America/Chicago')->next('Tuesday')->setTime(10, 0));
    Queue::fake([SendCampaignStep::class]);
    dueEnrollments(2);

    $this->artisan('campaigns:send-due')->assertSuccessful();

    Queue::assertPushed(SendCampaignStep::class, 2);
});

test('queues nothing outside the sending window', function (string $day, int $hour) {
    $this->travelTo(now('America/Chicago')->next($day)->setTime($hour, 0));
    Queue::fake([SendCampaignStep::class]);
    dueEnrollments(1);

    $this->artisan('campaigns:send-due')->assertSuccessful();

    Queue::assertNothingPushed();
})->with([
    'weekend' => ['Saturday', 10],
    'before hours' => ['Tuesday', 7],
    'after hours' => ['Tuesday', 17],
]);

test('queues no more than what is left of the daily limit', function () {
    $this->travelTo(now('America/Chicago')->next('Tuesday')->setTime(10, 0));
    Queue::fake([SendCampaignStep::class]);
    config(['outreach.daily_limit' => 3]);
    EmailEvent::factory()->count(2)->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now()]);
    dueEnrollments(5);

    $this->artisan('campaigns:send-due')->assertSuccessful();

    Queue::assertPushed(SendCampaignStep::class, 1);
});
