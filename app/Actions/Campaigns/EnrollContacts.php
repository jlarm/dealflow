<?php

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Enrolls contacts in a campaign. Only contacts with a verified address who have
 * not unsubscribed are enrolled, and contacts already in the campaign are skipped.
 */
class EnrollContacts
{
    private const int CHUNK_SIZE = 500;

    /**
     * @param  Builder<Contact>  $contacts
     * @return int The number of contacts newly enrolled.
     */
    public function handle(Campaign $campaign, Builder $contacts): int
    {
        $firstSendAt = now()->addDays((int) ($campaign->steps()->value('delay_days') ?? 0));
        $enrolled = 0;

        $contacts->clone()
            ->contactable()
            ->select('contacts.id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $chunk) use ($campaign, $firstSendAt, &$enrolled): void {
                $enrolled += CampaignEnrollment::query()->insertOrIgnore(
                    $chunk->map(fn (Contact $contact): array => [
                        'campaign_id' => $campaign->id,
                        'contact_id' => $contact->id,
                        'sequence_step' => 0,
                        'enrolled_at' => now(),
                        'next_send_at' => $firstSendAt,
                    ])->all(),
                );
            }, 'contacts.id', 'id');

        return $enrolled;
    }
}
