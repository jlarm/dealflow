<?php

namespace App\Jobs;

use App\Actions\Activities\LogActivity;
use App\Enums\ActivityType;
use App\Enums\CampaignStatus;
use App\Enums\EmailEventType;
use App\Mail\CampaignStepMail;
use App\Models\CampaignEnrollment;
use App\Models\EmailEvent;
use App\Services\OutreachSchedule;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Sends a contact the next email in their campaign sequence.
 *
 * The "sent" email event is recorded as soon as the provider accepts the email,
 * so a retry after a later failure finishes the bookkeeping without emailing
 * the contact twice.
 */
class SendCampaignStep implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [60, 300];

    /**
     * Kept below the database queue's 90 second retry_after.
     */
    public int $timeout = 60;

    /**
     * How long the job stays unique, so the scheduler cannot queue the same enrollment twice.
     */
    public int $uniqueFor = 3600;

    public function __construct(public CampaignEnrollment $enrollment) {}

    public function uniqueId(): string
    {
        return (string) $this->enrollment->id;
    }

    public function handle(LogActivity $logActivity, OutreachSchedule $schedule): void
    {
        $enrollment = $this->enrollment->fresh(['campaign', 'contact.company']);

        if ($enrollment === null || ! $enrollment->isActive() || $enrollment->campaign->status !== CampaignStatus::Active) {
            return;
        }

        $contact = $enrollment->contact;

        if (! $contact->isContactable()) {
            $enrollment->stop($contact->unsubscribed_at !== null ? 'unsubscribed' : 'email_not_verified');

            return;
        }

        $position = $enrollment->sequence_step + 1;
        $steps = $enrollment->campaign->steps()->whereIn('position', [$position, $position + 1])->get()->keyBy('position');
        $step = $steps->get($position);

        if ($step === null) {
            $enrollment->advanceTo($enrollment->sequence_step, null);

            return;
        }

        $sentEvent = $this->sentEvent($enrollment, $position);

        if ($sentEvent === null) {
            if ($schedule->remainingToday() === 0) {
                return;
            }

            $email = $step->personalizeFor($contact);
            $messageId = Str::uuid().'@'.Str::after((string) config('mail.from.address'), '@');

            $sentMessage = Mail::to($contact->email)->send(
                new CampaignStepMail($contact, $enrollment, $position, $email['subject'], $email['body'], $messageId),
            );

            $providerMessageId = trim((string) $sentMessage?->getMessageId(), '<>');

            $sentEvent = $contact->emailEvents()->create([
                'message_id' => $messageId,
                'event_type' => EmailEventType::Sent,
                'payload' => array_filter([
                    'enrollment_id' => $enrollment->id,
                    'campaign_id' => $enrollment->campaign_id,
                    'step' => $position,
                    'subject' => $email['subject'],
                    'provider_message_id' => $providerMessageId !== $messageId ? $providerMessageId : null,
                ], fn (mixed $value): bool => $value !== null && $value !== ''),
                'occurred_at' => now(),
            ]);
        }

        DB::transaction(function () use ($logActivity, $enrollment, $contact, $sentEvent, $position, $steps): void {
            if ($sentEvent->activity_id === null) {
                $activity = $logActivity->handle($contact, ActivityType::EmailSent, [
                    'campaign_id' => $enrollment->campaign_id,
                    'campaign' => $enrollment->campaign->name,
                    'step' => $position,
                    'subject' => $sentEvent->payload['subject'] ?? null,
                    'message_id' => $sentEvent->message_id,
                ]);

                $sentEvent->update(['activity_id' => $activity->id]);
            }

            $enrollment->advanceTo($position, $steps->get($position + 1));
        });
    }

    /**
     * Find the email already sent for this step, if an earlier attempt got that far.
     */
    private function sentEvent(CampaignEnrollment $enrollment, int $position): ?EmailEvent
    {
        return EmailEvent::query()
            ->whereBelongsTo($enrollment->contact)
            ->where('event_type', EmailEventType::Sent)
            ->where('payload->enrollment_id', $enrollment->id)
            ->where('payload->step', $position)
            ->first();
    }
}
