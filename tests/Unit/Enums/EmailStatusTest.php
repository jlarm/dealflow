<?php

use App\Enums\EmailStatus;

test('works out an email status from contact list values', function (?string $status, ?string $catchAll, ?string $bounced, ?EmailStatus $expected) {
    expect(EmailStatus::fromListValues($status, $catchAll, $bounced))->toBe($expected);
})->with([
    'verified' => ['Verified', null, null, EmailStatus::Valid],
    'verified, not catch-all' => ['Verified', 'Not Catch-all', null, EmailStatus::Valid],
    'verified on a catch-all domain' => ['Verified', 'Catch-all', null, EmailStatus::Risky],
    'unverified' => ['Unverified', null, null, EmailStatus::Risky],
    'unrecognised value' => ['Likely to engage', null, null, EmailStatus::Risky],
    'unavailable' => ['Unavailable', null, null, null],
    'blank' => [null, null, null, null],
    'invalid' => ['Invalid', null, null, EmailStatus::Invalid],
    'bounced status' => ['Bounced', null, null, EmailStatus::Bounced],
    'bounced flag beats verified' => ['Verified', null, 'TRUE', EmailStatus::Bounced],
    'bounced flag false' => ['Verified', null, 'false', EmailStatus::Valid],
]);
