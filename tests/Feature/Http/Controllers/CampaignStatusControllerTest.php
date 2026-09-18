<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\User;

test('activates a campaign that has emails', function () {
    $campaign = Campaign::factory()->create();
    CampaignStep::factory()->for($campaign)->create();

    $this->actingAs(User::factory()->create())->patch(route('campaigns.status.update', $campaign), ['status' => 'active']);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Active);
});

test('will not activate a campaign without emails', function () {
    $campaign = Campaign::factory()->create();

    $response = $this->actingAs(User::factory()->create())->patch(route('campaigns.status.update', $campaign), ['status' => 'active']);

    $response->assertSessionHasErrors(['status' => 'Add at least one email before activating the campaign.']);
    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft);
});

test('pauses an active campaign', function () {
    $campaign = Campaign::factory()->active()->create();

    $this->actingAs(User::factory()->create())->patch(route('campaigns.status.update', $campaign), ['status' => 'paused']);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Paused);
});
