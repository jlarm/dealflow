<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain' => fake()->unique()->domainName(),
            'industry' => fake()->randomElement(['Automotive', 'Software', 'Manufacturing', 'Healthcare', 'Logistics']),
            'size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+']),
            'enrichment_data' => null,
            'enriched_at' => null,
        ];
    }

    /**
     * Indicate that the company has been enriched by the provider.
     */
    public function enriched(): static
    {
        return $this->state(fn (array $attributes) => [
            'enrichment_data' => ['name' => $attributes['name'], 'employee_count' => fake()->numberBetween(5, 5000)],
            'enriched_at' => now(),
        ]);
    }
}
