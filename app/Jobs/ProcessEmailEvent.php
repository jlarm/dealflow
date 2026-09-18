<?php

namespace App\Jobs;

use App\Actions\Activities\LogActivity;
use App\Actions\Contacts\UnsubscribeContact;
use App\Enums\ActivityType;
use App\Enums\EmailEventType;
use App\Enums\EmailStatus;
use App\Models\Activity;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Acts on a stored email event: logs first opens and clicks (adding a little
 * engagement score), and protects deliverability by stopping all outreach after a
 * bounce, a spam complaint (treated as an unsubscribe), or an unsubscribe.
 */
class ProcessEmailEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public EmailEvent $emailEvent) {}

    public function handle(LogActivity $logActivity, UnsubscribeContact $unsubscribeContact): void
    {
        $event = $this->emailEvent;

        if ($event->activity_id !== null) {
            return;
        }

        $contact = $event->contact;

        DB::transaction(function () use ($event, $contact, $logActivity, $unsubscribeContact): void {
            if ($event->event_type === EmailEventType::Complained) {
                $contact->update(['email_status' => EmailStatus::Complained]);
                $unsubscribeContact->handle($contact, ['via' => 'spam_complaint']);

                return;
            }

            if ($event->event_type === EmailEventType::Unsubscribed) {
                $unsubscribeContact->handle($contact, ['via' => 'mailgun']);

                return;
            }

            $activity = match ($event->event_type) {
                EmailEventType::Opened, EmailEventType::Clicked => $this->logEngagement($event, $contact, $logActivity),
                EmailEventType::Bounced => $this->handleBounce($event, $contact, $logActivity),
                default => null,
            };

            if ($activity !== null) {
                $event->update(['activity_id' => $activity->id]);
            }
        });
    }

    /**
     * Log only the first open or click of each email, and add a little engagement score.
     */
    private function logEngagement(EmailEvent $event, Contact $contact, LogActivity $logActivity): ?Activity
    {
        $isFirst = ! EmailEvent::query()
            ->whereBelongsTo($contact)
            ->where('event_type', $event->event_type)
            ->where('message_id', $event->message_id)
            ->where('id', '<', $event->id)
            ->exists();

        if (! $isFirst) {
            return null;
        }

        $points = $event->event_type === EmailEventType::Clicked ? 2 : 1;
        $contact->update(['score' => min(100, $contact->score + $points)]);

        return $logActivity->handle(
            $contact,
            $event->event_type === EmailEventType::Opened ? ActivityType::EmailOpened : ActivityType::EmailClicked,
            array_filter([
                'subject' => $this->subject($event),
                'url' => data_get($event->payload, 'url'),
                'message_id' => $event->message_id,
            ]),
        );
    }

    private function handleBounce(EmailEvent $event, Contact $contact, LogActivity $logActivity): Activity
    {
        $contact->update(['email_status' => EmailStatus::Bounced]);
        $this->stopEnrollments($contact, 'bounced');

        return $logActivity->handle($contact, ActivityType::EmailBounced, array_filter([
            'subject' => $this->subject($event),
            'body' => data_get($event->payload, 'delivery-status.description') ?: data_get($event->payload, 'delivery-status.message') ?: data_get($event->payload, 'reason'),
            'message_id' => $event->message_id,
        ]));
    }

    private function stopEnrollments(Contact $contact, string $reason): void
    {
        CampaignEnrollment::query()
            ->whereBelongsTo($contact)
            ->active()
            ->get()
            ->each(fn (CampaignEnrollment $enrollment) => $enrollment->stop($reason));
    }

    private function subject(EmailEvent $event): ?string
    {
        $subject = EmailEvent::findSent([$event->message_id])?->payload['subject'] ?? data_get($event->payload, 'message.headers.subject');

        return is_string($subject) ? $subject : null;
    }
}
