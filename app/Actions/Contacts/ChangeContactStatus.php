<?php

namespace App\Actions\Contacts;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves a contact to a new pipeline stage and records the change on its timeline.
 * The contact goes to the top of the new stage on the board.
 */
class ChangeContactStatus
{
    public function __construct(private LogActivity $logActivity) {}

    public function handle(Contact $contact, ContactStatus $status, ?User $user = null): Contact
    {
        if ($contact->status === $status) {
            return $contact;
        }

        return DB::transaction(function () use ($contact, $status, $user): Contact {
            $previousStatus = $contact->status;

            $contact->update([
                'status' => $status,
                'pipeline_position' => Contact::topOfStage($status),
            ]);

            $this->logActivity->handle($contact, ActivityType::StatusChange, [
                'from' => $previousStatus->value,
                'to' => $status->value,
            ], $user);

            return $contact;
        });
    }
}
