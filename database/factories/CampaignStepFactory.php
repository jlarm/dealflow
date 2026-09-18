<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignStep>
 */
class CampaignStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'position' => 1,
            'subject' => fake()->sentence(6),
            'body' => fake()->paragraphs(3, true),
            'delay_days' => 0,
        ];
    }
}
