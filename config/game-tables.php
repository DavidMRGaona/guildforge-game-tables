<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Table Creation Settings
    |--------------------------------------------------------------------------
    */

    // Who can create tables: 'admin', 'members', 'permission'
    'creators' => env('GAMETABLES_CREATORS', 'admin'),

    /*
    |--------------------------------------------------------------------------
    | Membership Integration
    |--------------------------------------------------------------------------
    */

    'membership_integration' => [
        'enabled' => env('GAMETABLES_MEMBERSHIP_INTEGRATION', true),
        'fallback_role' => 'member',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'duration_minutes' => 240,
        'min_players' => 3,
        'max_players' => 5,
        'max_spectators' => 0,
        'language' => 'es',
        'registration_type' => 'everyone',
        'members_early_access_days' => 0,
        'auto_confirm' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'max_players_limit' => 20,
        'max_spectators_limit' => 50,
        'max_duration_minutes' => 720,
        'max_early_access_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'notify_on_registration' => true,
        'notify_on_cancellation' => true,
        'notify_waiting_list_promotion' => true,
        'reminder_hours_before' => [24, 2],
    ],

    /*
    |--------------------------------------------------------------------------
    | Feedback
    |--------------------------------------------------------------------------
    */

    'feedback' => [
        'enabled' => true,
        'allow_anonymous' => false,
        'days_to_submit' => 7,
    ],
];
