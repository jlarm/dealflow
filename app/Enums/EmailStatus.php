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
     * Work out an address's status from a contact list's columns.
     *
     * Only an explicitly verified address on a domain that is not catch-all is valid.
     * Any status value that is not recognised is treated as risky rather than trusted.
     */
    public static function fromListValues(?string $status, ?string $catchAllStatus = null, ?string $bounced = null): ?self
    {
        if (self::isYes($bounced)) {
            return self::Bounced;
        }

        $status = self::normalizeListValue($status);

        return match (true) {
            in_array($status, ['', 'unavailable', 'none', 'na'], true) => null,
            str_contains($status, 'bounce') => self::Bounced,
            in_array($status, ['invalid', 'undeliverable'], true) => self::Invalid,
            in_array($status, ['verified', 'valid', 'deliverable'], true) => self::isCatchAll($catchAllStatus) ? self::Risky : self::Valid,
            default => self::Risky,
        };
    }

    /**
     * Whether a catch-all column says the domain accepts mail for any address.
     */
    private static function isCatchAll(?string $catchAllStatus): bool
    {
        return in_array(self::normalizeListValue($catchAllStatus), ['catchall', 'yes', 'true', 'acceptall'], true);
    }

    private static function isYes(?string $value): bool
    {
        return in_array(self::normalizeListValue($value), ['yes', 'true', '1', 'y'], true);
    }

    /**
     * Lowercase a list value and strip everything but letters and digits, e.g. "Catch-all" becomes "catchall".
     */
    private static function normalizeListValue(?string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $value));
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
