<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts only webhooks signed by Mailgun with the configured signing key.
 *
 * Event webhooks send the signature as JSON under "signature"; inbound Routes send
 * timestamp, token, and signature as form fields. Stale timestamps and reused
 * tokens are rejected to stop replays. With no key configured, every request is rejected.
 */
class VerifyMailgunSignature
{
    /**
     * How old a signed request may be, in seconds.
     */
    private const int MAX_AGE = 15 * 60;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $signingKey = (string) config('services.mailgun.webhook_signing_key');

        if ($signingKey === '') {
            Log::warning('Rejected a Mailgun webhook because MAILGUN_WEBHOOK_SIGNING_KEY is not set.');

            abort(403);
        }

        $signed = $request->input('signature');
        $fields = is_array($signed)
            ? [$signed['timestamp'] ?? null, $signed['token'] ?? null, $signed['signature'] ?? null]
            : [$request->input('timestamp'), $request->input('token'), $signed];

        [$timestamp, $token, $signature] = array_map(
            fn (mixed $value): string => is_string($value) || is_int($value) ? (string) $value : '',
            $fields,
        );

        $isValid = $timestamp !== ''
            && $token !== ''
            && abs(time() - (int) $timestamp) <= self::MAX_AGE
            && hash_equals(hash_hmac('sha256', $timestamp.$token, $signingKey), $signature)
            && Cache::add("mailgun-webhook-token:{$token}", true, self::MAX_AGE);

        abort_unless($isValid, 403);

        return $next($request);
    }
}
