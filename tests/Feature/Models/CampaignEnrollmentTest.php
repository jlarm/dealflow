<?php

use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;

test('only unfinished enrollments in active campaigns whose send time has passed are due', function () {
    $this->freezeSecond();
    $active = Campaign::factory()->active()->create();
    $paused = Campaign::factory()->paused()->create();
    $draft = Campaign::factory()->create();
    $dueNow = Contact::factory()->create();
    $overdue = Contact::factory()->create();
    $active->contacts()->attach($dueNow, ['next_send_at' => now()]);
    $active->contacts()->attach($overdue, ['next_send_at' => now()->subDay()]);
    $active->contacts()->attach(Contact::factory()->create(), ['next_send_at' => now()->addMinute()]);
    $active->contacts()->attach(Contact::factory()->create(), ['next_send_at' => null]);
    $active->contacts()->attach(Contact::factory()->create(), ['next_send_at' => now()->subDay(), 'completed_at' => now()]);
    $active->contacts()->attach(Contact::factory()->create(), ['next_send_at' => now()->subDay(), 'stopped_at' => now()]);
    $paused->contacts()->attach(Contact::factory()->create(), ['next_send_at' => now()->subDay()]);
    $draft->contacts()->attach(Contact::factory()->create(), ['next_send_at' => now()->subDay()]);

    $due = CampaignEnrollment::dueForSend()->orderBy('id')->pluck('contact_id');

    expect($due->all())->toBe([$dueNow->id, $overdue->id]);
});

test('enrollment progress is available through the contact relationship', function () {
    $this->freezeSecond();
    $campaign = Campaign::factory()->create();
    $contact = Contact::factory()->create();
    $contact->campaigns()->attach($campaign, ['sequence_step' => 2, 'next_send_at' => now()->addDays(3)]);

    $enrollment = $contact->campaigns()->sole()->enrollment;

    expect($enrollment)->toBeInstanceOf(CampaignEnrollment::class)
        ->sequence_step->toBe(2)
        ->next_send_at->toEqual(now()->addDays(3))
        ->enrolled_at->not->toBeNull();
});
