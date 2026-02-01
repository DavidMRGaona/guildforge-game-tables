<?php

return array (
  'creators' => 'admin',
  'membership_integration' => 
  array (
    'enabled' => true,
  ),
  'defaults' => 
  array (
    'members_early_access_days' => 0,
    'duration_minutes' => 240,
    'min_players' => 3,
    'max_players' => 5,
    'max_spectators' => 0,
    'auto_confirm' => true,
    'registration_type' => 'everyone',
  ),
  'notifications' => 
  array (
    'notify_on_registration' => true,
    'notify_on_cancellation' => true,
    'notify_waiting_list_promotion' => true,
  ),
  'limits' => 
  array (
    'max_players_limit' => 20,
    'max_spectators_limit' => 50,
    'max_duration_minutes' => 720,
  ),
  'frontend_creation' => 
  array (
    'enabled' => true,
    'allowed_content' => 'both',
    'access_level' => 'registered',
    'publication' => 
    array (
      'mode' => 'approval',
    ),
  ),
);
