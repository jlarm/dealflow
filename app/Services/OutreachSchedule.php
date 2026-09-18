<?php

namespace App\Services;

use App\Models\EmailEvent;

/**
 * The outreach sending rules from config/outreach.php: a weekday business-hours
 * window in the outreach timezone, and a daily limit across all campaigns.
 */
class OutreachSchedule
{
    /**
     * Whether campaign emails may be sent right now.
     */
    public function isOpen(): bool
    {
        $now = now(config('outreach.timezone'));

        return $now->isWeekday()
            && $now->hour >= config('outreach.send_from_hour')
            && $now->hour < config('outreach.send_until_hour');
    }

    public function dailyLimit(): int
    {
        return (int) config('outreach.daily_limit');
    }

    /**
     * How many more campaign emails may be sent today.
     */
    public function remainingToday(): int
    {
        return max(0, $this->dailyLimit() - EmailEvent::sentToday());
    }
}
