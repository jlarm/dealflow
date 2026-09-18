<?php

namespace App\Enums;

/**
 * The kind of entry recorded on a contact's activity timeline.
 */
enum ActivityType: string
{
    case EmailSent = 'email_sent';
    case EmailOpened = 'email_opened';
    case EmailClicked = 'email_clicked';
    case EmailReplied = 'email_replied';
    case EmailBounced = 'email_bounced';
    case Unsubscribed = 'unsubscribed';
    case Note = 'note';
    case Call = 'call';
    case StatusChange = 'status_change';

    /**
     * Get the human-readable label for the activity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::EmailSent => 'Email Sent',
            self::EmailOpened => 'Email Opened',
            self::EmailClicked => 'Email Clicked',
            self::EmailReplied => 'Email Replied',
            self::EmailBounced => 'Email Bounced',
            self::Unsubscribed => 'Unsubscribed',
            self::Note => 'Note',
            self::Call => 'Call',
            self::StatusChange => 'Status Change',
        };
    }

    /**
     * Determine whether this activity is a touchpoint that updates the contact's last contacted date.
     */
    public function countsAsContact(): bool
    {
        return in_array($this, [self::EmailSent, self::Call], true);
    }

    /**
     * Get the activity types a user may log by hand from the contact page.
     *
     * @return list<self>
     */
    public static function loggable(): array
    {
        return [self::Note, self::Call];
    }
}
