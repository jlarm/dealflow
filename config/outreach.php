<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Daily Sending Limit
    |--------------------------------------------------------------------------
    |
    | The most campaign emails sent per day, across all campaigns. Keep this
    | low while a new sending domain warms up, then raise it gradually.
    |
    */

    'daily_limit' => (int) env('OUTREACH_DAILY_LIMIT', 100),

    /*
    |--------------------------------------------------------------------------
    | Sending Window
    |--------------------------------------------------------------------------
    |
    | Campaign emails are only sent on weekdays between these hours, in the
    | given timezone, when prospects are most likely to read them.
    |
    */

    'timezone' => env('OUTREACH_TIMEZONE', 'America/Chicago'),

    'send_from_hour' => (int) env('OUTREACH_SEND_FROM_HOUR', 8),

    'send_until_hour' => (int) env('OUTREACH_SEND_UNTIL_HOUR', 17),

];
