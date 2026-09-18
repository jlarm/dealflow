<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\EmailEventType;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessEmailEvent;
use App\Models\Contact;
use App\Models\EmailEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Receives Mailgun delivery events (delivered, opened, clicked, failed, complained, unsubscribed).
 *
 * The raw event is stored straight away, de-duplicated by Mailgun's event id, and
 * processed on the queue so Mailgun gets a fast response.
 */
class MailgunEventController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $event = $request->input('event-data');

        if (! is_array($event) || ! is_string($event['id'] ?? null) || ! is_string($event['event'] ?? null)) {
            return response()->json(['status' => 'ignored']);
        }

        $type = EmailEventType::fromMailgun($event['event'], is_string($event['severity'] ?? null) ? $event['severity'] : null);
        $messageId = trim((string) data_get($event, 'message.headers.message-id'), '<> ');

        if ($type === null || $messageId === '') {
            return response()->json(['status' => 'ignored']);
        }

        $contact = $this->contact($event, $messageId);

        if ($contact === null) {
            return response()->json(['status' => 'ignored']);
        }

        $emailEvent = $contact->emailEvents()->createOrFirst(
            ['provider_event_id' => $event['id']],
            [
                'message_id' => $messageId,
                'event_type' => $type,
                'payload' => $event,
                'occurred_at' => Carbon::createFromTimestamp((float) ($event['timestamp'] ?? time())),
            ],
        );

        if ($emailEvent->wasRecentlyCreated) {
            ProcessEmailEvent::dispatch($emailEvent);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Find the contact from the variables DealFlow attached when sending, then the
     * email the event refers to, then the recipient's address.
     *
     * @param  array<string, mixed>  $event
     */
    private function contact(array $event, string $messageId): ?Contact
    {
        $contactId = data_get($event, 'user-variables.contact_id');

        if (is_numeric($contactId) && ($contact = Contact::find((int) $contactId)) !== null) {
            return $contact;
        }

        if (($sent = EmailEvent::findSent([$messageId])) !== null) {
            return $sent->contact;
        }

        $recipient = data_get($event, 'recipient');

        return is_string($recipient) ? Contact::firstWhere('email', Contact::normalizeEmail($recipient)) : null;
    }
}
