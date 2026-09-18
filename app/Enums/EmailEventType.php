<?php

namespace App\Enums;

/**
 * An email delivery event reported by the email provider's webhooks.
 */
enum EmailEventType: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Opened = 'opened';
    case Clicked = 'clicked';
    case Bounced = 'bounced';
    case Complained = 'complained';
    case Unsubscribed = 'unsubscribed';

    /**
     * Map a Mailgun event to a DealFlow event type. Events DealFlow does not act on,
     * like "accepted" or a temporary delivery failure that Mailgun will retry, map to null.
     */
    public static function fromMailgun(string $event, ?string $severity = null): ?self
    {
        return match ($event) {
            'delivered' => self::Delivered,
            'opened' => self::Opened,
            'clicked' => self::Clicked,
            'failed' => $severity === 'permanent' ? self::Bounced : null,
            'complained' => self::Complained,
            'unsubscribed' => self::Unsubscribed,
            default => null,
        };
    }

    /**
     * Get the human-readable label for the event type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Opened => 'Opened',
            self::Clicked => 'Clicked',
            self::Bounced => 'Bounced',
            self::Complained => 'Complained',
            self::Unsubscribed => 'Unsubscribed',
        };
    }
}
