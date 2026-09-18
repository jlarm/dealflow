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
