<?php

namespace App\Actions\Contacts;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Support\Facades\DB;

/**
 * Opts a contact out of outreach: marks them unsubscribed, stops every campaign
 * they are in, and records it on their timeline. Safe to call more than once.
 */
class UnsubscribeContact
{
    public function __construct(private LogActivity $logActivity) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Contact $contact, array $payload = []): void
    {
        if ($contact->unsubscribed_at !== null) {
            return;
        }

        DB::transaction(function () use ($contact, $payload): void {
            $contact->update(['unsubscribed_at' => now()]);

            CampaignEnrollment::query()
                ->whereBelongsTo($contact)
                ->active()
                ->get()
                ->each(fn (CampaignEnrollment $enrollment) => $enrollment->stop('unsubscribed'));

            $this->logActivity->handle($contact, ActivityType::Unsubscribed, $payload);
        });
    }
}
