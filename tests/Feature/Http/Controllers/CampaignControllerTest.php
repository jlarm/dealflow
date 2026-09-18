<?php

use App\Enums\CampaignStatus;
use App\Enums\EmailEventType;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Contact;
use App\Models\EmailEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('campaigns.index'));

    $response->assertRedirect(route('login'));
});

describe('store', function () {
    test('creates a draft campaign and opens it', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('campaigns.store'), ['name' => 'Q4 dealers']);

        $campaign = Campaign::sole();
        $response->assertRedirect(route('campaigns.show', $campaign));
        expect($campaign)
            ->name->toBe('Q4 dealers')
            ->status->toBe(CampaignStatus::Draft);
    });

    test('requires a name', function () {
        $response = $this->actingAs(User::factory()->create())->post(route('campaigns.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseEmpty('campaigns');
    });
});

describe('show', function () {
    test('shows the emails in order with enrollment and sending stats', function () {
        $campaign = Campaign::factory()->active()->create();
        CampaignStep::factory()->for($campaign)->create(['position' => 2]);
        CampaignStep::factory()->for($campaign)->create(['position' => 1]);
        [$active, $finished, $stopped] = Contact::factory()->count(3)->verified()->create();
        $campaign->contacts()->attach($active);
        $campaign->contacts()->attach($finished, ['completed_at' => now()]);
        $campaign->contacts()->attach($stopped, ['stopped_at' => now(), 'stop_reason' => 'unsubscribed']);
        EmailEvent::factory()->for($active)->create(['event_type' => EmailEventType::Sent, 'payload' => ['campaign_id' => $campaign->id]]);
        EmailEvent::factory()->for($active)->create(['event_type' => EmailEventType::Sent, 'payload' => ['campaign_id' => $campaign->id + 1]]);

        $response = $this->actingAs(User::factory()->create())->get(route('campaigns.show', $campaign));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('campaigns/show')
            ->where('steps.0.position', 1)
            ->where('steps.1.position', 2)
            ->has('enrollments.data', 3)
            ->where('stats', ['enrolled' => 3, 'active' => 1, 'completed' => 1, 'stopped' => 1, 'sent' => 1]));
    });
});

test('renames a campaign', function () {
    $campaign = Campaign::factory()->create(['name' => 'Old']);

    $this->actingAs(User::factory()->create())->put(route('campaigns.update', $campaign), ['name' => 'New']);

    expect($campaign->refresh()->name)->toBe('New');
});

test('deletes a campaign with its emails and enrollments', function () {
    $campaign = Campaign::factory()->create();
    CampaignStep::factory()->for($campaign)->create();
    $campaign->contacts()->attach(Contact::factory()->create());

    $response = $this->actingAs(User::factory()->create())->delete(route('campaigns.destroy', $campaign));

    $response->assertRedirect(route('campaigns.index'));
    $this->assertModelMissing($campaign);
    $this->assertDatabaseEmpty('campaign_steps');
    $this->assertDatabaseEmpty('campaign_contact');
});
