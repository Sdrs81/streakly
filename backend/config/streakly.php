<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Habit Calendar Timezone
    |--------------------------------------------------------------------------
    |
    | The timezone that decides what "today" means for habit completions.
    | `app.timezone` stays UTC so timestamps are stored in UTC; only the
    | habit calendar uses this local day. The app supports a single timezone.
    |
    */

    'timezone' => env('STREAKLY_TIMEZONE', 'Europe/Istanbul'),

];
