<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Models\Activity;
use App\Models\Campaign;
use App\Models\CampaignStep;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class CrmSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed demo companies, contacts, tags, and campaigns for local development.
     */
    public function run(): void
    {
        $companies = Company::factory()->count(50)->create();

        $tags = collect(['Franchise Dealer', 'Independent Dealer', 'Decision Maker', 'Follow Up', 'Hot Lead'])
            ->map(fn (string $name): Tag => Tag::factory()->create(['name' => $name]));

        $contacts = Contact::factory()
            ->count(500)
            ->recycle($companies)
            ->state(fn (): array => [
                'status' => fake()->randomElement(ContactStatus::cases()),
            ])
            ->create();

        $contacts->each(function (Contact $contact) use ($tags): void {
            $contact->tags()->attach($tags->random(fake()->numberBetween(0, 2))->pluck('id'));
        });

        Activity::factory()
            ->count(300)
            ->recycle($contacts)
            ->state(new Sequence(
                ['type' => ActivityType::Note, 'payload' => ['body' => 'Left a voicemail, will try again next week.']],
                ['type' => ActivityType::Call, 'payload' => ['body' => 'Spoke with the GM, interested in a demo.']],
            ))
            ->create();

        $campaign = Campaign::factory()->active()->create(['name' => 'Q4 Dealer Outreach']);

        CampaignStep::factory()
            ->count(3)
            ->for($campaign)
            ->sequence(
                ['position' => 1, 'delay_days' => 0],
                ['position' => 2, 'delay_days' => 3],
                ['position' => 3, 'delay_days' => 7],
            )
            ->create();

        $campaign->contacts()->attach(
            $contacts->where('status', ContactStatus::New)->take(40)->pluck('id'),
            ['enrolled_at' => now(), 'next_send_at' => now()],
        );

        Campaign::factory()->create(['name' => 'Conference Follow-up']);
    }
}
