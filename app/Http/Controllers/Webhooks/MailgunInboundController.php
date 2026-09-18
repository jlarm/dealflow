<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessInboundReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Receives replies that a Mailgun inbound Route forwards from the Reply-To address.
 */
class MailgunInboundController extends Controller
{
    /**
     * The longest reply text kept on the timeline.
     */
    private const int MAX_TEXT_LENGTH = 5000;

    public function __invoke(Request $request): JsonResponse
    {
        $headers = $this->headers($request);
        $from = $this->emailAddress((string) $request->input('from', $request->input('sender', '')));

        if ($from === null) {
            return response()->json(['status' => 'ignored']);
        }

        $referencedIds = [];
        preg_match_all('/<([^>]+)>/', $request->input('In-Reply-To', '').' '.$request->input('References', ''), $referencedIds);

        $subject = (string) $request->input('subject', '');
        $text = trim((string) ($request->input('stripped-text') ?: $request->input('body-plain', '')));

        ProcessInboundReply::dispatch([
            'from' => $from,
            'subject' => Str::limit($subject, 255, ''),
            'text' => Str::limit($text, self::MAX_TEXT_LENGTH),
            'reply_message_id' => trim((string) $request->input('Message-Id', ''), '<> ') ?: null,
            'referenced_message_ids' => array_values(array_unique($referencedIds[1])),
            'auto_reply' => $this->isAutoReply($headers, $subject),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Mailgun sends the original headers as a JSON list of [name, value] pairs.
     *
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        $pairs = json_decode((string) $request->input('message-headers', '[]'), true);
        $headers = [];

        foreach (is_array($pairs) ? $pairs : [] as $pair) {
            if (is_array($pair) && is_string($pair[0] ?? null) && is_string($pair[1] ?? null)) {
                $headers[Str::lower($pair[0])] = $pair[1];
            }
        }

        return $headers;
    }

    /**
     * Out-of-office and other automatic replies should not count as a real reply.
     *
     * @param  array<string, string>  $headers
     */
    private function isAutoReply(array $headers, string $subject): bool
    {
        $autoSubmitted = Str::lower($headers['auto-submitted'] ?? 'no');

        return $autoSubmitted !== 'no'
            || isset($headers['x-autoreply'])
            || isset($headers['x-autorespond'])
            || in_array(Str::lower($headers['precedence'] ?? ''), ['auto_reply', 'bulk', 'junk'], true)
            || (bool) preg_match('/^\s*(automatic reply|auto(matic)?[- ]?reply|out of (the )?office|ooo\b)/i', $subject);
    }

    /**
     * Pull the address out of a From value like "Jane Doe <jane@acme.com>".
     */
    private function emailAddress(string $from): ?string
    {
        $address = preg_match('/<([^>]+)>/', $from, $match) ? $match[1] : $from;
        $address = Str::lower(trim($address));

        return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }
}
