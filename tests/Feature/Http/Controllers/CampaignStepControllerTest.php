<?php

use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\User;

test('adds an email to the end of the sequence', function () {
    $campaign = Campaign::factory()->create();
    CampaignStep::factory()->for($campaign)->create(['position' => 1]);

    $this->actingAs(User::factory()->create())->post(route('campaigns.steps.store', $campaign), [
        'subject' => 'Following up',
        'body' => 'Hi {{first_name}}',
        'delay_days' => 3,
    ]);

    expect($campaign->steps()->pluck('subject', 'position')->all())->toHaveKey(2, 'Following up');
});

test('validates the email', function () {
    $campaign = Campaign::factory()->create();

    $response = $this->actingAs(User::factory()->create())->post(route('campaigns.steps.store', $campaign), [
        'subject' => '',
        'body' => '',
        'delay_days' => -1,
    ]);

    $response->assertSessionHasErrors(['subject', 'body', 'delay_days']);
    $this->assertDatabaseEmpty('campaign_steps');
});

test('edits an email', function () {
    $step = CampaignStep::factory()->create(['subject' => 'Old']);

    $this->actingAs(User::factory()->create())->put(route('campaigns.steps.update', [$step->campaign, $step]), [
        'subject' => 'New',
        'body' => $step->body,
        'delay_days' => 2,
    ]);

    expect($step->refresh())
        ->subject->toBe('New')
        ->delay_days->toBe(2);
});

test('removing an email from a draft renumbers the emails after it', function () {
    $campaign = Campaign::factory()->create();
    [$first, $second, $third] = CampaignStep::factory()->count(3)->for($campaign)->sequence(['position' => 1], ['position' => 2], ['position' => 3])->create();

    $this->actingAs(User::factory()->create())->delete(route('campaigns.steps.destroy', [$campaign, $second]));

    $this->assertModelMissing($second);
    expect($campaign->steps()->pluck('id')->all())->toBe([$first->id, $third->id])
        ->and($third->refresh()->position)->toBe(2);
});

test('will not remove an email once the campaign has started', function () {
    $campaign = Campaign::factory()->active()->create();
    $step = CampaignStep::factory()->for($campaign)->create();

    $response = $this->actingAs(User::factory()->create())->delete(route('campaigns.steps.destroy', [$campaign, $step]));

    $response->assertSessionHasErrors('step');
    $this->assertModelExists($step);
});

test('returns 404 for an email that belongs to another campaign', function () {
    $campaign = Campaign::factory()->create();
    $otherStep = CampaignStep::factory()->create();

    $response = $this->actingAs(User::factory()->create())->delete(route('campaigns.steps.destroy', [$campaign, $otherStep]));

    $response->assertNotFound();
    $this->assertModelExists($otherStep);
});
