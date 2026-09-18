<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'filename' => fake()->word().'.csv',
            'path' => 'imports/'.fake()->uuid().'.csv',
            'source_list' => fake()->word().'.csv',
            'status' => ImportStatus::Processing,
            'row_count' => 0,
        ];
    }

    /**
     * Indicate that the import has finished processing.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Completed,
        ]);
    }
}
