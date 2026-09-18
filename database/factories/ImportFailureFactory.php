<?php

namespace Database\Factories;

use App\Models\Import;
use App\Models\ImportFailure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportFailure>
 */
class ImportFailureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'import_id' => Import::factory(),
            'row_number' => fake()->numberBetween(2, 500),
            'errors' => ['email' => ['The email field must be a valid email address.']],
            'raw_row' => ['email' => 'not-an-email'],
        ];
    }
}
