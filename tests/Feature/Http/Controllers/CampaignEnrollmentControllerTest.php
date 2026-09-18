<?php

use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\User;

test('enrolls the verified contacts matching the filters and schedules their first email', function () {
    $this->freezeSecond();
    $campaign = Campaign::factory()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1, 'delay_days' => 2]);
    $match = Contact::factory()->verified()->withStatus(ContactStatus::Qualified)->create();
    Contact::factory()->withEmailStatus(EmailStatus::Risky)->withStatus(ContactStatus::Qualified)->create();
    Contact::factory()->verified()->unsubscribed()->withStatus(ContactStatus::Qualified)->create();
    Contact::factory()->verified()->withStatus(ContactStatus::New)->create();

    $response = $this->actingAs(User::factory()->create())
        ->from(route('contacts.index'))
        ->post(route('campaigns.enrollments.store', $campaign), ['status' => 'qualified']);

    $response->assertRedirect(route('contacts.index'))
        ->assertInertiaFlash('toast.message', "1 verified contact added to {$campaign->name}.");
    expect(CampaignEnrollment::sole())
        ->contact_id->toBe($match->id)
        ->sequence_step->toBe(0)
        ->next_send_at->toEqual(now()->addDays(2));
});

test('skips contacts already in the campaign', function () {
    $campaign = Campaign::factory()->create();
    $contact = Contact::factory()->verified()->create();
    $campaign->contacts()->attach($contact, ['sequence_step' => 2]);

    $response = $this->actingAs(User::factory()->create())->post(route('campaigns.enrollments.store', $campaign));

    $response->assertInertiaFlash('toast.message', "0 verified contacts added to {$campaign->name}.");
    expect(CampaignEnrollment::sole()->sequence_step)->toBe(2);
});

test('will not add contacts to a completed campaign', function () {
    $campaign = Campaign::factory()->create(['status' => 'completed']);
    Contact::factory()->verified()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('campaigns.enrollments.store', $campaign));

    $response->assertSessionHasErrors('campaign');
    $this->assertDatabaseEmpty('campaign_contact');
});

test('removes a contact from a campaign', function () {
    $campaign = Campaign::factory()->create();
    $campaign->contacts()->attach(Contact::factory()->create());
    $enrollment = CampaignEnrollment::sole();

    $this->actingAs(User::factory()->create())->delete(route('campaigns.enrollments.destroy', [$campaign, $enrollment]));

    $this->assertDatabaseEmpty('campaign_contact');
});
