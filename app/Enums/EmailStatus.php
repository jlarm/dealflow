<?php

namespace App\Enums;

/**
 * The deliverability of a contact's email address, from verification or bounce feedback.
 */
enum EmailStatus: string
{
    case Valid = 'valid';
    case Risky = 'risky';
    case Invalid = 'invalid';
    case Bounced = 'bounced';
    case Complained = 'complained';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::Risky => 'Risky',
            self::Invalid => 'Invalid',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
        };
    }

    /**
     * Determine whether outreach email may be sent to an address with this status.
     */
    public function isSendable(): bool
    {
        return in_array($this, [self::Valid, self::Risky], true);
    }

    /**
     * Get the statuses that block outreach email.
     *
     * @return list<self>
     */
    public static function unsendable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => ! $status->isSendable()));
    }
}
