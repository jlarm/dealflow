<?php

namespace Database\Factories;

use App\Enums\ContactStatus;
use App\Enums\EmailStatus;
use App\Models\Company;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'title' => fake()->jobTitle(),
            'linkedin_url' => 'https://www.linkedin.com/in/'.fake()->unique()->userName(),
            'source_list' => fake()->randomElement(['dealers-q1.csv', 'conference-leads.csv', 'apollo-export.csv']),
            'status' => ContactStatus::New,
            'score' => fake()->numberBetween(0, 100),
        ];
    }

    /**
     * Indicate that the contact is in the given pipeline stage.
     */
    public function withStatus(ContactStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    /**
     * Indicate that the contact has opted out of outreach email.
     */
    public function unsubscribed(): static
    {
        return $this->state(fn (array $attributes) => [
            'unsubscribed_at' => now(),
        ]);
    }

    /**
     * Indicate that the contact's email address has the given deliverability status.
     */
    public function withEmailStatus(EmailStatus $emailStatus): static
    {
        return $this->state(fn (array $attributes) => [
            'email_status' => $emailStatus,
        ]);
    }

    /**
     * Indicate that the contact has no email address.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }
}
