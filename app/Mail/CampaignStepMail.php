<?php

namespace App\Mail;

use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;

/**
 * One personalized email in a campaign sequence.
 *
 * Sent synchronously from the SendCampaignStep job, which needs the provider's
 * message id straight away to track delivery events.
 */
class CampaignStepMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Contact $contact,
        public CampaignEnrollment $enrollment,
        public int $step,
        public string $emailSubject,
        public string $emailBody,
        public string $messageId,
    ) {}

    /**
     * Set DealFlow's own Message-ID, so replies and delivery events can be matched
     * to this email whichever mail provider sends it.
     */
    public function headers(): Headers
    {
        return new Headers(messageId: $this->messageId);
    }

    public function envelope(): Envelope
    {
        $unsubscribeUrl = $this->unsubscribeUrl();

        return new Envelope(
            subject: $this->emailSubject,
            tags: ['campaign'],
            metadata: [
                'enrollment_id' => (string) $this->enrollment->id,
                'campaign_id' => (string) $this->enrollment->campaign_id,
                'contact_id' => (string) $this->contact->id,
                'step' => (string) $this->step,
            ],
            using: [
                function (Email $message) use ($unsubscribeUrl): void {
                    $message->getHeaders()->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
                    $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                },
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.campaign-step',
            text: 'mail.campaign-step-text',
            with: [
                'body' => $this->emailBody,
                'unsubscribeUrl' => $this->unsubscribeUrl(),
            ],
        );
    }

    /**
     * A permanent signed link that lets the contact opt out without logging in.
     */
    private function unsubscribeUrl(): string
    {
        return URL::signedRoute('unsubscribe.show', ['contact' => $this->contact]);
    }
}
