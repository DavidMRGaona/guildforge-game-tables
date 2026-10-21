<?php

declare(strict_types=1);

return [
    'guest_confirmation' => [
        'greeting' => 'Hello :name,',
        'intro' => 'Your registration as :role has been successfully recorded.',
        'details' => 'Session details:',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
        'cancel_intro' => 'If you need to cancel your registration before the table starts, use the following link:',
        'cancel_button' => 'Cancel registration',
        'gdpr_notice' => 'Your data is processed according to our privacy policy.',
        'role_player' => 'player',
        'role_spectator' => 'spectator',
    ],

    'guest_email_confirmation' => [
        'subject' => 'Confirm your registration: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'We have received your registration for the table “:tableTitle”. Confirm it with the button below.',
        'deadline' => 'If you do not confirm it before :deadline, the place will be released.',
        'unguaranteed' => 'Your place is not guaranteed until you confirm it.',
        'button' => 'Confirm registration',
        'ignore' => 'If you did not register, ignore this email: the registration will not go ahead.',
    ],

    'guest_registration_expired' => [
        'subject' => 'Registration expired: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'You did not confirm your registration for the table “:tableTitle” in time and the place has been released.',
        'retry' => 'If there are still places left, you can register again.',
        'button' => 'View table',
    ],

    'registration_status' => [
        'subject_confirmed' => 'Registration confirmed: :tableTitle',
        'subject_pending' => 'Registration received: :tableTitle',
        'subject_waiting_list' => 'On the waiting list: :tableTitle',
        'confirmed' => 'Your spot is confirmed.',
        'pending' => 'Your registration is pending approval by the organizer. We will email you once it has been reviewed.',
        'waiting_list' => 'The table is full. You are number :position on the waiting list and we will let you know if a spot opens up.',
    ],

    'user_confirmation' => [
        'greeting' => 'Hello :name,',
        'intro' => 'Your registration as :role has been successfully recorded.',
        'details' => 'Session details:',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
        'outro' => 'See you at the session!',
    ],

    'registration' => [
        'subject' => 'New registration for table: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => ':name has registered as :role for the table :tableTitle.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
    ],

    'cancellation' => [
        'subject' => 'Cancellation for table: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => ':name has cancelled their registration for the table :tableTitle.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
    ],

    'cancellation_confirmation' => [
        'subject' => 'Cancellation confirmed: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'Your registration has been successfully cancelled.',
        'details' => 'Table details:',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
        'outro' => 'We hope to see you at future sessions.',
        'guest_gdpr_notice' => 'Your data is processed according to our privacy policy.',
    ],

    'organization_cancellation' => [
        'subject' => 'The organizers have cancelled your registration for :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'The organizers have cancelled your registration for the table :tableTitle.',
        'details' => 'Table details:',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
        'outro' => 'If you think this is a mistake, please contact the organizers.',
        'guest_gdpr_notice' => 'Your data is processed according to our privacy policy.',
    ],

    'waiting_list_promotion' => [
        'subject' => 'Spot available at table: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'A spot has opened up at the table :tableTitle and you have been promoted from the waiting list.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
        'outro' => 'Your spot has been automatically confirmed. See you at the session!',
        'outro_pending' => 'Your registration is pending until the game master confirms it. We will let you know by email when it is reviewed.',
    ],

    'waiting_list_promotion_gm' => [
        'subject' => 'Waiting list promotion for table: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => ':name has been promoted from the waiting list for the table :tableTitle.',
        'pending' => 'The table has manual confirmation: the registration is pending until you confirm or reject it in the panel.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'view_table' => 'View table',
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation Emails
    |--------------------------------------------------------------------------
    */
    'moderation_submitted' => [
        'subject' => 'New table pending moderation: :tableTitle',
        'greeting' => 'Hello,',
        'intro' => 'A new table has been submitted for review.',
        'table_title' => 'Table',
        'created_by' => 'Created by',
        'review_table' => 'Review table',
        'outro' => 'Please review the table and approve or reject it.',
    ],

    'campaign_moderation_submitted' => [
        'subject' => 'New campaign pending moderation: :campaignTitle',
        'greeting' => 'Hello,',
        'intro' => 'A new campaign has been submitted for review.',
        'campaign_title' => 'Campaign',
        'created_by' => 'Created by',
        'review_campaign' => 'Review campaign',
        'outro' => 'Please review the campaign and approve or reject it.',
    ],

    'moderation_approved' => [
        'subject' => 'Your table has been approved: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'Your table :tableTitle has been approved and is now published.',
        'table_title' => 'Table',
        'notes_label' => 'Moderator notes',
        'view_table' => 'View table',
        'outro' => 'Thank you for contributing to the community!',
    ],

    'moderation_rejected' => [
        'subject' => 'Your table was not approved: :tableTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'We regret to inform you that your table :tableTitle was not approved.',
        'table_title' => 'Table',
        'reason_label' => 'Reason',
        'edit_table' => 'Edit table',
        'outro' => 'You can modify your table and submit it again for review.',
    ],

    'campaign_moderation_approved' => [
        'subject' => 'Your campaign has been approved: :campaignTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'Your campaign :campaignTitle has been approved and is now published.',
        'notes_label' => 'Moderator notes',
        'view_campaign' => 'View campaign',
        'outro' => 'Thank you for contributing to the community!',
    ],

    'campaign_moderation_rejected' => [
        'subject' => 'Your campaign has not been approved: :campaignTitle',
        'greeting' => 'Hello :name,',
        'intro' => 'We are sorry to let you know that your campaign :campaignTitle has not been approved.',
        'reason_label' => 'Reason',
        'edit_campaign' => 'Edit campaign',
        'outro' => 'You can edit your campaign and send it for review again.',
    ],

    'table_reminder' => [
        'subject' => 'Reminder: :tableTitle, :date',
        'greeting' => 'Hello, :name!',
        'greeting_anonymous' => 'Hello!',
        'intro' => 'This is a reminder of your registration at the table ":tableTitle", which starts on :date.',
        'location' => 'Location',
        'view_table' => 'View table',
        'cancel' => 'If you can no longer come, cancel your registration from the table page to free the seat.',
        'cancel_guest' => 'If you can no longer come, cancel your registration to free the seat: :url',
    ],

    'campaign_sessions' => [
        'intro' => 'You are signed up for these sessions:',
        'waiting_suffix' => ' (on the waiting list, with priority as a roster member)',
        'no_sessions' => 'There are no sessions scheduled yet: we will let you know when the next one is published.',
        'absence_hint' => 'If you cannot make a session, withdraw from it on its page: you will stay in the campaign.',
    ],

    'campaign_session_scheduled' => [
        'subject' => 'New session of :campaignTitle: :date',
        'greeting' => 'Hello, :name!',
        'intro' => 'A new session of the campaign ":campaignTitle" has been scheduled and your seat is reserved.',
        'intro_waiting' => 'A new session of the campaign ":campaignTitle" has been scheduled. It is full: you are on the waiting list, with priority as a roster member.',
        'table_title' => 'Table',
        'table_date' => 'Date',
        'table_location' => 'Location',
        'absence_hint' => 'If you cannot make a session, withdraw from it on its page: you will stay in the campaign.',
        'view_session' => 'View session',
    ],

    'campaign_join_request_received' => [
        'subject' => 'Request to join :campaignTitle',
        'greeting' => 'Hello, :name!',
        'intro' => ':player wants to join the campaign ":campaignTitle".',
        'message' => 'Message: ":message"',
        'review_requests' => 'Review requests',
    ],

    'campaign_join_request_accepted' => [
        'subject' => 'You are now part of :campaignTitle',
        'greeting' => 'Hello, :name!',
        'intro' => 'The direction has accepted your request to join the campaign ":campaignTitle".',
        'view_campaign' => 'View campaign',
    ],

    'campaign_join_request_rejected' => [
        'subject' => 'Request for :campaignTitle',
        'greeting' => 'Hello, :name!',
        'intro' => 'The direction has not accepted your request to join the campaign ":campaignTitle".',
        'reason' => 'Reason: :reason',
        'view_campaign' => 'View campaign',
    ],

    'campaign_roster_added' => [
        'subject' => 'You have been added to :campaignTitle',
        'greeting' => 'Hello, :name!',
        'intro' => 'The direction has added you to the roster of the campaign ":campaignTitle".',
        'view_campaign' => 'View campaign',
    ],

    'campaign_roster_removed' => [
        'subject' => 'You are no longer part of :campaignTitle',
        'greeting' => 'Hello, :name!',
        'intro' => 'The direction has removed you from the roster of the campaign ":campaignTitle". Your seats in the upcoming sessions have been freed.',
        'view_campaign' => 'View campaign',
    ],

    'campaign_player_left' => [
        'subject' => ':player has left :campaignTitle',
        'greeting' => 'Hello, :name!',
        'intro' => ':player has left the roster of the campaign ":campaignTitle". Their seats in the upcoming sessions have been freed.',
        'view_roster' => 'View roster',
    ],

    'campaign_invitation' => [
        'subject' => ':inviter invites you to the campaign ":campaignTitle"',
        'greeting' => 'Hello!',
        'intro' => ':inviter invites you to join the campaign ":campaignTitle".',
        'view_campaign' => 'You can see the campaign page [here](:url).',
        'view_invitation' => 'View invitation',
        'reject' => 'If you do not want to join, open the invitation and press Reject: we will delete your email right away.',
        'expires' => 'Expires on :date.',
        'privacy_title' => '**Information about how we handle your email**',
        'privacy_controller' => 'Controller: :name.',
        'privacy_contact' => 'Contact: :email.',
        'privacy_dpo' => 'Data protection officer: :dpo.',
        'privacy_policy' => 'For your other rights, see the [privacy policy](:url).',
        'privacy_default' => "Purpose: inviting you to this campaign.\n\nSource: :inviter gave it to us.\n\nRetention: until you accept, reject or the invitation expires, at most :days days. We also keep an irreversible fingerprint of the email for 30 days to limit how many invitations are sent.\n\nRights: rejecting the invitation deletes your email at once.",
        'notice_label' => 'Campaign invitation email',
        'notice_help' => 'Data protection block of the email received by a person invited to a campaign.',
        'preview_campaign' => 'Sample campaign',
    ],

    'campaign_invitation_accepted' => [
        'subject' => ':player has accepted your invitation to ":campaignTitle"',
        'greeting' => 'Hello, :name!',
        'intro' => ':player has accepted your invitation and is now on the roster of the campaign ":campaignTitle".',
        'view_roster' => 'View roster',
    ],
];
