<?php

declare(strict_types=1);

// Notices of the notification centre (the bell): one line each, written when they are delivered
return [
    // Shown before the name of a muted thing in the profile
    'subjects' => [
        'table' => 'Table',
        'campaign' => 'Campaign',
    ],

    'sign_ups' => [
        'registration_confirmed' => 'Sign-up confirmed for «:table»',
        'registration_pending' => 'Sign-up received for «:table»',
        'registration_waiting_list' => 'You are on the waiting list of «:table»',
        'confirmed' => 'Your seat at «:table» is confirmed',
        'rejected' => 'Your sign-up for «:table» was not accepted',
        'cancelled' => 'You left «:table»',
        'promoted' => 'You have a seat at «:table»',
        'promoted_pending' => 'You have a seat at «:table», waiting for confirmation',
        'table_cancelled' => 'The table «:table» was cancelled',
        'organization_cancelled' => 'The organisers cancelled your sign-up for «:table»',
        'absence' => 'Absence noted for «:table»',
        'join_request_accepted' => 'You are now part of «:campaign»',
        'join_request_rejected' => 'Your request for «:campaign» was not accepted',
        'roster_added' => 'You were added to «:campaign»',
        'roster_removed' => 'You are no longer part of «:campaign»',
    ],

    'reminder' => 'Reminder: «:table», :date',

    'campaign_news' => [
        'invitation' => ':inviter invites you to the campaign «:campaign»',
        'invitation_expires' => 'Expires on :date',
        'session' => 'New session of «:campaign»: :date',
        'session_waiting' => 'New session of «:campaign»: :date, on the waiting list',
    ],

    'direction' => [
        'registered' => ':name signed up for «:table»',
        'registered_pending' => ':name asks for a seat at «:table»',
        'cancelled' => ':name left «:table»',
        'promoted' => ':name moves from the waiting list to «:table»',
        'promoted_pending' => ':name moves from the waiting list to «:table», waiting for confirmation',
        'join_request' => ':name asks to join «:campaign»',
        'player_left' => ':name left «:campaign»',
        'invitation_accepted' => ':name accepted your invitation to «:campaign»',
        'grouped' => '{0} :name: news in «:thing»|[1,*] :name and :count more: news in «:thing»',
        'someone' => 'Someone',
    ],

    'proposals' => [
        'table_approved' => 'Your table «:table» was approved',
        'table_rejected' => 'Your table «:table» was not approved',
        'campaign_approved' => 'Your campaign «:campaign» was approved',
        'campaign_rejected' => 'Your campaign «:campaign» was not approved',
    ],

    'moderation' => [
        'table' => 'Table waiting for review: «:table»',
        'campaign' => 'Campaign waiting for review: «:campaign»',
        'proposed_by' => 'Proposed by :name',
        'grouped' => '{1} :count proposal waiting for review|[2,*] :count proposals waiting for review',
    ],
];
