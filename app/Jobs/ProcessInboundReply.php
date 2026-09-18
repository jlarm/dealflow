<?php

namespace App\Jobs;

use App\Actions\Activities\LogActivity;
use App\Actions\Contacts\ChangeContactStatus;
use App\Enums\ActivityType;
use App\Enums\ContactStatus;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Records a reply on the contact's timeline. A real reply (not an auto-reply)
 * stops every campaign the contact is in and moves New or Contacted contacts to Replied.
 *
 * The sender's address identifies the contact. The email being replied to is only
 * used when the sender is not a contact, e.g. an assistant replying on their behalf.
 */
class ProcessInboundReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * Stages a reply moves forward. Contacts further along the pipeline keep their stage.
     *
     * @var list<ContactStatus>
     */
    private const array STAGES_BEFORE_REPLY = [ContactStatus::New, ContactStatus::Contacted];

    /**
     * @param  array{from: string, subject: string, text: string, reply_message_id: string|null, referenced_message_ids: list<string>, auto_reply: bool}  $reply
     */
    public function __construct(public array $reply) {}

    public function handle(LogActivity $logActivity, ChangeContactStatus $changeContactStatus): void
    {
        $original = EmailEvent::findSent($this->reply['referenced_message_ids']);
        $contact = Contact::firstWhere('email', $this->reply['from']) ?? $original?->contact;

        if ($contact === null || $this->alreadyRecorded($contact)) {
            return;
        }

        if ($original !== null && $original->contact_id !== $contact->id) {
            $original = null;
        }

        DB::transaction(function () use ($contact, $original, $logActivity, $changeContactStatus): void {
            $logActivity->handle($contact, ActivityType::EmailReplied, array_filter([
                'subject' => $this->reply['subject'],
                'body' => $this->reply['text'],
                'from' => $this->reply['from'],
                'campaign_id' => $original->payload['campaign_id'] ?? null,
                'reply_message_id' => $this->reply['reply_message_id'],
                'auto_reply' => $this->reply['auto_reply'] ?: null,
            ], fn (mixed $value): bool => $value !== null && $value !== ''));

            if ($this->reply['auto_reply']) {
                return;
            }

            CampaignEnrollment::query()
                ->whereBelongsTo($contact)
                ->active()
                ->get()
                ->each(fn (CampaignEnrollment $enrollment) => $enrollment->stop('replied'));

            if (in_array($contact->status, self::STAGES_BEFORE_REPLY, true)) {
                $changeContactStatus->handle($contact, ContactStatus::Replied);
            }
        });
    }

    /**
     * Mailgun can deliver the same inbound message more than once.
     */
    private function alreadyRecorded(Contact $contact): bool
    {
        return $this->reply['reply_message_id'] !== null && $contact->activities()
            ->where('type', ActivityType::EmailReplied)
            ->where('payload->reply_message_id', $this->reply['reply_message_id'])
            ->exists();
    }
}
