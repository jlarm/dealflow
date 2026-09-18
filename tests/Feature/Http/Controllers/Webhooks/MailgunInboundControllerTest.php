<?php

use App\Jobs\ProcessInboundReply;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.mailgun.webhook_signing_key' => 'test-signing-key']);
});

test('queues the reply with the ids of the emails it answers', function () {
    Queue::fake([ProcessInboundReply::class]);

    $this->post(route('webhooks.mailgun.inbound'), [
        ...mailgunSignature(),
        'from' => 'Dana Whitfield <Dana@LakesideFord.com>',
        'subject' => 'Re: Quick question',
        'stripped-text' => 'Sure, call me Tuesday.',
        'body-plain' => "Sure, call me Tuesday.\n\n> quoted original",
        'Message-Id' => '<reply-1@lakesideford.com>',
        'In-Reply-To' => '<abc@outreach.example.com>',
        'References' => '<older@outreach.example.com> <abc@outreach.example.com>',
        'message-headers' => json_encode([['From', 'Dana'], ['Subject', 'Re: Quick question']]),
    ])->assertOk();

    Queue::assertPushed(ProcessInboundReply::class, fn (ProcessInboundReply $job): bool => $job->reply === [
        'from' => 'dana@lakesideford.com',
        'subject' => 'Re: Quick question',
        'text' => 'Sure, call me Tuesday.',
        'reply_message_id' => 'reply-1@lakesideford.com',
        'referenced_message_ids' => ['abc@outreach.example.com', 'older@outreach.example.com'],
        'auto_reply' => false,
    ]);
});

test('flags automatic replies', function (array $fields) {
    Queue::fake([ProcessInboundReply::class]);

    $this->post(route('webhooks.mailgun.inbound'), [
        ...mailgunSignature(),
        'from' => 'dana@lakesideford.com',
        'subject' => 'Re: Quick question',
        ...$fields,
    ]);

    Queue::assertPushed(ProcessInboundReply::class, fn (ProcessInboundReply $job): bool => $job->reply['auto_reply'] === true);
})->with([
    'auto-submitted header' => [['message-headers' => json_encode([['Auto-Submitted', 'auto-replied']])]],
    'precedence header' => [['message-headers' => json_encode([['Precedence', 'auto_reply']])]],
    'out of office subject' => [['subject' => 'Out of Office: back Monday']],
    'automatic reply subject' => [['subject' => 'Automatic reply: Quick question']],
]);

test('ignores mail without a sender address', function () {
    Queue::fake([ProcessInboundReply::class]);

    $this->post(route('webhooks.mailgun.inbound'), [...mailgunSignature(), 'from' => 'nobody'])->assertOk();

    Queue::assertNothingPushed();
});
