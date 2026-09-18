<?php

use App\Mail\CampaignStepMail;
use App\Models\Campaign;
use App\Models\CampaignEnrollment;
use App\Models\Contact;
use Illuminate\Support\Facades\Mail;

/**
 * @return array{0: Contact, 1: CampaignEnrollment}
 */
function mailRecipient(): array
{
    $contact = Contact::factory()->verified()->create();
    Campaign::factory()->active()->create()->contacts()->attach($contact);

    return [$contact, CampaignEnrollment::sole()];
}

test('renders the body as escaped text with line breaks and an unsubscribe link', function () {
    [$contact, $enrollment] = mailRecipient();

    $mail = new CampaignStepMail($contact, $enrollment, 1, 'Hello', "Line one\n<script>alert(1)</script>", 'abc@example.com');

    $mail->assertSeeInHtml('Line one<br />', false)
        ->assertDontSeeInHtml('<script>', false)
        ->assertSeeInHtml('/unsubscribe/'.$contact->id.'?signature=', false)
        ->assertSeeInText('Unsubscribe: http');
});

test('sets DealFlow\'s Message-ID and one-click unsubscribe headers', function () {
    config(['mail.default' => 'array']);
    [$contact, $enrollment] = mailRecipient();

    Mail::to($contact->email)->send(new CampaignStepMail($contact, $enrollment, 1, 'Hello', 'Body', 'abc123@outreach.example.com'));

    $headers = app('mailer')->getSymfonyTransport()->messages()->sole()->getOriginalMessage()->getHeaders();
    expect($headers->get('List-Unsubscribe')->getBodyAsString())->toStartWith('<http')->toContain('/unsubscribe/'.$contact->id)
        ->and($headers->get('List-Unsubscribe-Post')->getBodyAsString())->toBe('List-Unsubscribe=One-Click')
        ->and($headers->get('Message-ID')->getBodyAsString())->toBe('<abc123@outreach.example.com>');
});
