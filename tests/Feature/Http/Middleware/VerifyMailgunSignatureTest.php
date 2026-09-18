<?php

beforeEach(function () {
    config(['services.mailgun.webhook_signing_key' => 'test-signing-key']);
});

test('accepts a correctly signed webhook', function () {
    $response = $this->postJson(route('webhooks.mailgun.events'), ['signature' => mailgunSignature(), 'event-data' => []]);

    $response->assertOk();
});

test('rejects webhooks with a wrong, stale, missing, or reused signature', function (Closure $signature) {
    $response = $this->postJson(route('webhooks.mailgun.events'), ['signature' => $signature(), 'event-data' => []]);

    $response->assertForbidden();
})->with([
    'wrong signature' => [fn () => [...mailgunSignature(), 'signature' => str_repeat('0', 64)]],
    'stale timestamp' => [fn () => mailgunSignature(timestamp: time() - 3600)],
    'missing signature' => [fn () => []],
]);

test('rejects a replayed token', function () {
    $signature = mailgunSignature();
    $this->postJson(route('webhooks.mailgun.events'), ['signature' => $signature, 'event-data' => []])->assertOk();

    $response = $this->postJson(route('webhooks.mailgun.events'), ['signature' => $signature, 'event-data' => []]);

    $response->assertForbidden();
});

test('rejects every webhook when no signing key is configured', function () {
    $signature = mailgunSignature();
    config(['services.mailgun.webhook_signing_key' => null]);

    $response = $this->postJson(route('webhooks.mailgun.events'), ['signature' => $signature, 'event-data' => []]);

    $response->assertForbidden();
});

test('accepts the form-encoded signature fields inbound routes send', function () {
    $response = $this->post(route('webhooks.mailgun.inbound'), [...mailgunSignature(), 'from' => 'not an email']);

    $response->assertOk();
});
