<?php

use App\Models\Campaign;
use App\Models\CampaignStep;

test('campaign steps are returned in sending order', function () {
    $campaign = Campaign::factory()->create();
    CampaignStep::factory()
        ->for($campaign)
        ->sequence(['position' => 3], ['position' => 1], ['position' => 2])
        ->count(3)
        ->create();

    $positions = $campaign->steps->pluck('position');

    expect($positions->all())->toBe([1, 2, 3]);
});
