<?php

use App\Enums\ActivityType;
use App\Enums\EmailEventType;
use App\Enums\EmailStatus;
use App\Jobs\SendCampaignStep;
use App\Mail\CampaignStepMail;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\CampaignStep;
use App\Models\Company;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Support\Facades\Mail;

/**
 * Enroll a contact in a campaign and return the enrollment.
 *
 * @param  array<string, mixed>  $attributes
 */
function enroll(Campaign $campaign, Contact $contact, array $attributes = []): CampaignEnrollment
{
    $campaign->contacts()->attach($contact, ['next_send_at' => now(), ...$attributes]);

    return CampaignEnrollment::query()->whereBelongsTo($campaign)->whereBelongsTo($contact)->sole();
}

test('sends the next email personalized for the contact and schedules the one after', function () {
    $this->freezeSecond();
    Mail::fake();
    $campaign = Campaign::factory()->active()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1, 'subject' => 'Hi {{first_name}}', 'body' => 'Quick question for {{company}}.']);
    CampaignStep::factory()->for($campaign)->create(['position' => 2, 'delay_days' => 3]);
    $contact = Contact::factory()->verified()->for(Company::factory()->state(['name' => 'Lakeside Ford']))->create(['first_name' => 'Dana']);
    $enrollment = enroll($campaign, $contact);

    SendCampaignStep::dispatchSync($enrollment);

    Mail::assertSent(CampaignStepMail::class, fn (CampaignStepMail $mail): bool => $mail->hasTo($contact->email)
        && $mail->emailSubject === 'Hi Dana'
        && $mail->emailBody === 'Quick question for Lakeside Ford.'
        && $mail->hasMetadata('enrollment_id', (string) $enrollment->id)
        && $mail->messageId === EmailEvent::sole()->message_id);
    expect($enrollment->refresh())
        ->sequence_step->toBe(1)
        ->next_send_at->toEqual(now()->addDays(3))
        ->completed_at->toBeNull();
    $sent = EmailEvent::sole();
    expect($sent->message_id)->toEndWith('@'.str(config('mail.from.address'))->after('@'));
    expect($sent)
        ->event_type->toBe(EmailEventType::Sent)
        ->payload->toMatchArray(['enrollment_id' => $enrollment->id, 'step' => 1, 'subject' => 'Hi Dana']);
    expect($sent->activity)
        ->type->toBe(ActivityType::EmailSent)
        ->contact_id->toBe($contact->id);
    expect($contact->refresh()->last_contacted_at)->toEqual(now());
});

test('finishes the sequence after the last email', function () {
    Mail::fake();
    $campaign = Campaign::factory()->active()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1]);
    $enrollment = enroll($campaign, Contact::factory()->verified()->create());

    SendCampaignStep::dispatchSync($enrollment);

    expect($enrollment->refresh())
        ->completed_at->not->toBeNull()
        ->next_send_at->toBeNull();
});

test('stops instead of sending when the contact no longer has a verified email', function () {
    Mail::fake();
    $campaign = Campaign::factory()->active()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1]);
    $enrollment = enroll($campaign, Contact::factory()->withEmailStatus(EmailStatus::Bounced)->create());

    SendCampaignStep::dispatchSync($enrollment);

    Mail::assertNothingSent();
    expect($enrollment->refresh())
        ->stop_reason->toBe('email_not_verified')
        ->stopped_at->not->toBeNull();
});

test('does not send while the campaign is paused', function () {
    Mail::fake();
    $campaign = Campaign::factory()->paused()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1]);
    $enrollment = enroll($campaign, Contact::factory()->verified()->create());

    SendCampaignStep::dispatchSync($enrollment);

    Mail::assertNothingSent();
    expect($enrollment->refresh()->sequence_step)->toBe(0);
});

test('a retry after the email went out finishes the bookkeeping without emailing again', function () {
    Mail::fake();
    $campaign = Campaign::factory()->active()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1]);
    $contact = Contact::factory()->verified()->create();
    $enrollment = enroll($campaign, $contact);
    $alreadySent = EmailEvent::factory()->for($contact)->create([
        'event_type' => EmailEventType::Sent,
        'provider_event_id' => null,
        'payload' => ['enrollment_id' => $enrollment->id, 'campaign_id' => $campaign->id, 'step' => 1, 'subject' => 'Hello'],
    ]);

    SendCampaignStep::dispatchSync($enrollment);

    Mail::assertNothingSent();
    expect($alreadySent->refresh()->activity_id)->not->toBeNull();
    expect($enrollment->refresh()->sequence_step)->toBe(1);
    expect(EmailEvent::count())->toBe(1);
});

test('holds the email when the daily sending limit has been reached', function () {
    Mail::fake();
    config(['outreach.daily_limit' => 1]);
    EmailEvent::factory()->create(['event_type' => EmailEventType::Sent, 'occurred_at' => now()]);
    $campaign = Campaign::factory()->active()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1]);
    $enrollment = enroll($campaign, Contact::factory()->verified()->create());

    SendCampaignStep::dispatchSync($enrollment);

    Mail::assertNothingSent();
    expect($enrollment->refresh()->sequence_step)->toBe(0);
});
