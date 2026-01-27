<?php

declare(strict_types=1);

return [
    'guest_confirmation' => [
        'subject' => 'Registration confirmation: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'Your registration as :role has been successfully recorded.',
        'details' => 'Session details:',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'cancel_intro' => 'If you need to cancel your registration, use the following link:',
        'cancel_button' => 'Cancel registration',
        'gdpr_notice' => 'Your data will be automatically deleted after the session date, in accordance with our privacy policy.',
        'role_player' => 'player',
        'role_spectator' => 'spectator',
    ],

    'registration' => [
        'subject' => 'New registration for table: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => ':name has registered as :role for the table :tableTitle.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
    ],

    'cancellation' => [
        'subject' => 'Cancellation for table: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => ':name has cancelled their registration for the table :tableTitle.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
    ],

    'waiting_list_promotion' => [
        'subject' => 'Spot available at table: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'A spot has opened up at the table :tableTitle and you have been promoted from the waiting list.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'outro' => 'Your spot has been automatically confirmed. See you at the session!',
    ],

    'waiting_list_promotion_gm' => [
        'subject' => 'Waiting list promotion for table: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => ':name has been promoted from the waiting list for the table :tableTitle.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
    ],
];
