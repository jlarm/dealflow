<?php

namespace Database\Factories;

use App\Enums\EmailEventType;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EmailEvent>
 */
class EmailEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'activity_id' => null,
            'message_id' => '<'.Str::uuid().'@mg.example.com>',
            'provider_event_id' => Str::random(22),
            'event_type' => EmailEventType::Delivered,
            'payload' => [],
            'occurred_at' => now(),
        ];
    }
}
