<?php

namespace App\Enums;

/**
 * The pipeline stage of a contact, rendered as the columns of the kanban board.
 */
enum ContactStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Qualified = 'qualified';
    case MeetingBooked = 'meeting_booked';
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Contacted => 'Contacted',
            self::Replied => 'Replied',
            self::Qualified => 'Qualified',
            self::MeetingBooked => 'Meeting Booked',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * Get the color name the frontend uses for badges and kanban columns.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'slate',
            self::Contacted => 'blue',
            self::Replied => 'violet',
            self::Qualified => 'amber',
            self::MeetingBooked => 'cyan',
            self::Won => 'green',
            self::Lost => 'red',
        };
    }
}
