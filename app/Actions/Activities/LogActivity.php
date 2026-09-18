<?php

namespace App\Actions\Activities;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Records an entry on a contact's timeline. This is the only way activities are written.
 */
class LogActivity
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Contact $contact, ActivityType $type, array $payload = [], ?User $user = null): Activity
    {
        return DB::transaction(function () use ($contact, $type, $payload, $user): Activity {
            $activity = $contact->activities()->create([
                'type' => $type,
                'payload' => $payload,
                'user_id' => $user?->id,
            ]);

            if ($type->countsAsContact()) {
                $contact->update(['last_contacted_at' => $activity->created_at]);
            }

            return $activity;
        });
    }
}
