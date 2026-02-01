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

    /*
    |--------------------------------------------------------------------------
    | Frontend Creation Settings
    |--------------------------------------------------------------------------
    |
    | Controls whether users can create game tables and campaigns from the
    | public frontend (not just admin panel).
    |
    */

    'frontend_creation' => [
        // Master switch for frontend creation feature
        'enabled' => env('GAMETABLES_FRONTEND_CREATION_ENABLED', false),

        // What content types can be created: 'tables', 'campaigns', 'both'
        'allowed_content' => env('GAMETABLES_FRONTEND_ALLOWED_CONTENT', 'tables'),

        // Access control: 'everyone', 'registered', 'role', 'permission'
        'access_level' => env('GAMETABLES_FRONTEND_ACCESS_LEVEL', 'registered'),

        // Required roles (when access_level is 'role')
        'allowed_roles' => [],

        // Required permission (when access_level is 'permission')
        'required_permission' => null,

        // Priority tiers for early access (lower tier = higher priority)
        // Each tier can create tables X days before event start
        // Example: tier 1 with 7 days means they can create 7 days before event
        'priority_tiers' => [
            // ['tier' => 1, 'type' => 'permission', 'value' => 'gametables:priority_create', 'days_before' => 7],
            // ['tier' => 2, 'type' => 'role', 'value' => 'socio', 'days_before' => 3],
        ],

        // Publication workflow settings
        'publication' => [
            // Mode: 'auto' (immediate publish), 'approval' (requires review), 'role_based' (depends on user role)
            'mode' => env('GAMETABLES_FRONTEND_PUBLICATION_MODE', 'approval'),

            // Roles that get auto-publish (when mode is 'role_based')
            'auto_publish_roles' => [],

            // Permissions that get auto-publish (when mode is 'role_based')
            'auto_publish_permissions' => [],
        ],
    ],
];
