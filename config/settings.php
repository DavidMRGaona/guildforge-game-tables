<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Game tables settings: shipped defaults
|--------------------------------------------------------------------------
|
| Initial values of the panel page (Mesas de rol → Configuración). The
| values saved in the panel are merged over these ones and read through
| GameTableSettingsReader; this file is the module's only source of defaults.
|
*/

return [
    'membership_integration' => [
        // Members' early access to registration (days set per table)
        'enabled' => true,
    ],

    'defaults' => [
        'members_early_access_days' => 0,
        'duration_minutes' => 240,
        'min_players' => 3,
        'max_players' => 5,
        'max_spectators' => 0,
        'auto_confirm' => true,
        'registration_type' => 'everyone',
    ],

    'notifications' => [
        'notify_on_registration' => true,
        'notify_on_cancellation' => true,
        'notify_waiting_list_promotion' => true,
    ],

    'limits' => [
        'max_players_limit' => 20,
        'max_spectators_limit' => 50,
        'max_duration_minutes' => 720,
    ],

    'frontend_creation' => [
        'enabled' => true,
        // 'tables', 'campaigns' or 'both'
        'allowed_content' => 'both',
        // 'everyone', 'registered', 'role' or 'permission'
        'access_level' => 'registered',
        // Comma-separated role names (access level 'role')
        'allowed_roles' => '',
        // Permission key (access level 'permission')
        'required_permission' => null,
        // Early creation tiers: [['tier' => 1, 'type' => 'role', 'value' => 'socio', 'days_before' => 3], ...]
        'priority_tiers' => [],
        'publication' => [
            // 'auto', 'approval' or 'role_based'
            'mode' => 'approval',
            'auto_publish_roles' => [],
            'auto_publish_permissions' => [],
        ],
    ],
];
