<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city().' Dealer Outreach',
            'status' => CampaignStatus::Draft,
        ];
    }

    /**
     * Indicate that the campaign is currently sending.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Active,
        ]);
    }

    /**
     * Indicate that the campaign has been paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CampaignStatus::Paused,
        ]);
    }
}
