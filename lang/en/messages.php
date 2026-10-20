<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation & UI Labels
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Game tables',

    'navigation' => [
        'group' => 'Game tables',
        'tables' => 'Tables',
        'campaigns' => 'Campaigns',
        'game_systems' => 'Game systems',
        'content_warnings' => 'Content warnings',
        'settings' => 'Settings',
        'config' => 'Configuration',
    ],

    'calendar' => [
        'source_label' => 'Game tables',
        'meta' => [
            'game_system' => 'Game system',
            'game_master' => 'Game master',
            'players' => 'Players',
            'format' => 'Format',
        ],
    ],

    'model' => [
        'game_table' => [
            'singular' => 'Table',
            'plural' => 'Tables',
        ],
        'campaign' => [
            'singular' => 'Campaign',
            'plural' => 'Campaigns',
        ],
        'game_system' => [
            'singular' => 'Game system',
            'plural' => 'Game systems',
        ],
        'content_warning' => [
            'singular' => 'Content warning',
            'plural' => 'Content warnings',
        ],
        'participant' => [
            'singular' => 'Participant',
            'plural' => 'Participants',
        ],
    ],

    'pages' => [
        'create_table' => 'Create table',
        'edit_table' => 'Edit table',
        'create_campaign' => 'Create campaign',
        'edit_campaign' => 'Edit campaign',
        'create_game_system' => 'Create game system',
        'edit_game_system' => 'Edit game system',
        'create_content_warning' => 'Create content warning',
        'edit_content_warning' => 'Edit content warning',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */
    'tabs' => [
        'basic_info' => 'Basic information',
        'direction' => 'Direction',
        'schedule' => 'Schedule and location',
        'capacity' => 'Capacity and requirements',
        'content' => 'Content and safety',
        'registration' => 'Registration',
        'publication' => 'Publication',
        'progress' => 'Progress',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */
    'fields' => [
        'name' => 'Name',
        'slug' => 'Slug',
        'description' => 'Description',
        'publisher' => 'Publisher',
        'edition' => 'Edition',
        'year' => 'Year',
        'logo_url' => 'Logo',
        'website_url' => 'Website',
        'is_active' => 'Active',
        'label' => 'Label',
        'severity' => 'Severity',
        'icon' => 'Icon',
        'title' => 'Title',
        'game_system' => 'Game system',
        'campaign' => 'Campaign',
        'event' => 'Event',
        'game_master' => 'Game master',
        'synopsis' => 'Synopsis',
        'start_time' => 'Start date and time',
        'duration_minutes' => 'Duration (minutes)',
        'location' => 'Location',
        'online_url' => 'Online URL',
        'min_players' => 'Minimum players',
        'max_players' => 'Maximum players',
        'max_spectators' => 'Maximum spectators',
        'minimum_age' => 'Minimum age',
        'language' => 'Language',
        'table_type' => 'Table type',
        'table_format' => 'Format',
        'table_status' => 'Status',
        'genres' => 'Genres',
        'tone' => 'Tone',
        'experience_level' => 'Experience level',
        'character_creation' => 'Character creation',
        'safety_tools' => 'Safety tools',
        'content_warnings' => 'Content warnings',
        'custom_warnings' => 'Custom warnings',
        'registration_type' => 'Registration type',
        'members_early_access_days' => 'Member early access days',
        'registration_opens_at' => 'Registration opens',
        'registration_closes_at' => 'Registration closes',
        'registration_closes_at_help' => 'At the latest, the start of the table (or its end, if it admits registrations in play). Participants can always cancel only until the start.',
        'accepts_registrations_in_progress' => 'Allow registrations during the session',
        'accepts_registrations_in_progress_help' => 'Admits registrations from the start to the end of the session, even if «Start» was not pressed, only with free seats: no waiting list and no cancellations',
        'auto_confirm' => 'Auto confirm',
        'is_published' => 'Published',
        'is_published_help' => 'Controls the public visibility of the table. Registrations open automatically based on configured dates.',
        'is_published_campaign_help' => 'Controls the public visibility of the campaign.',
        'is_published_not_submitted' => 'The author has not sent it to review yet.',
        'is_published_pending_review' => 'It is awaiting review: approve or reject it with “Approve” or “Reject”.',
        'is_published_campaign_pending_review' => 'It is awaiting review: approve or reject it from the campaign list.',
        'published_at' => 'Published date',
        'notes' => 'Notes',
        'session_count' => 'Session count',
        'session_count_help' => 'Total number of planned sessions. Leave empty if not defined.',
        'current_session' => 'Current session',
        'current_session_help' => 'Use 0 for campaigns that have not started yet.',
        'frequency' => 'Frequency',
        'campaign_status' => 'Campaign status',
        'accepts_new_players' => 'Accepts new players',
        'accepts_new_players_help' => 'Indicates if the campaign is actively looking for new players.',
        'user' => 'User',
        'participant_role' => 'Role',
        'participant_status' => 'Status',
        'registered_at' => 'Registered at',
        'confirmed_at' => 'Confirmed at',
        'cancelled_at' => 'Cancelled at',
        'waiting_list_position' => 'Place on the waiting list',
        'player_notes' => 'Player notes',
        'gm_notes' => 'GM notes',

        // External person fields
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'email' => 'Email',
        'phone' => 'Phone',

        // Game master fields
        'gm_type' => 'Type',
        'gm_type_user' => 'Platform user',
        'gm_type_external' => 'External person',
        'gm_role' => 'Role',
        'custom_title' => 'Custom title',
        'custom_title_placeholder' => 'E.g.: Dungeon Master, Narrator...',
        'custom_title_help' => 'Leave empty to use the game system title',
        'notify_by_email' => 'Notify by email',
        'is_name_public' => 'Name publicly visible',
        'game_master_title' => 'Game master title',
        'game_master_title_help' => 'E.g.: Dungeon Master, Keeper, Narrator, Referee...',

        // Participant type fields
        'participant_type' => 'Type',
        'participant_type_user' => 'Platform user',
        'participant_type_external' => 'External person',

        // Custom warnings
        'custom_warnings_placeholder' => 'Add custom warning',

        // Game masters
        'game_masters' => 'Game masters',

        // Image
        'image' => 'Image',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    */
    'sections' => [
        'basic_info' => 'Basic information',
        'game_details' => 'Game details',
        'schedule' => 'Schedule and location',
        'capacity' => 'Capacity',
        'requirements' => 'Requirements',
        'content' => 'Content',
        'safety' => 'Safety and warnings',
        'registration' => 'Registration',
        'publication' => 'Publication',
        'participants' => 'Participants',
        'campaign_info' => 'Campaign information',
        'game_masters' => 'Game masters',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enums
    |--------------------------------------------------------------------------
    */
    'enums' => [
        'table_type' => [
            'one_shot' => 'One-shot',
            'campaign_session' => 'Campaign session',
            'demo' => 'Demo',
            'tutorial' => 'Tutorial',
        ],
        'table_format' => [
            'in_person' => 'In person',
            'online' => 'Online',
            'hybrid' => 'Hybrid',
        ],
        'table_status' => [
            'draft' => 'Draft',
            'scheduled' => 'Scheduled',
            'full' => 'Full',
            'in_progress' => 'In progress',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'genre' => [
            'fantasy' => 'Fantasy',
            'horror' => 'Horror',
            'sci_fi' => 'Science fiction',
            'post_apocalyptic' => 'Post-apocalyptic',
            'cyberpunk' => 'Cyberpunk',
            'steampunk' => 'Steampunk',
            'historical' => 'Historical',
            'modern' => 'Modern',
            'superhero' => 'Superhero',
            'mystery' => 'Mystery',
            'western' => 'Western',
            'comedy' => 'Comedy',
            'other' => 'Other',
        ],
        'tone' => [
            'serious' => 'Serious',
            'light' => 'Light',
            'mixed' => 'Mixed',
            'dark' => 'Dark',
        ],
        'experience_level' => [
            'none' => 'No experience required',
            'beginner' => 'Beginner',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
        ],
        'character_creation' => [
            'pre_generated' => 'Pre-generated characters',
            'bring_own' => 'Bring your own character',
            'create_at_table' => 'Create at table',
            'any' => 'Any',
        ],
        'safety_tool' => [
            'x_card' => 'X-Card',
            'lines_and_veils' => 'Lines and veils',
            'open_door' => 'Open door',
            'stars' => 'Stars and wishes',
            'support_flower' => 'Support flower',
            'script' => 'Script change',
            'roses' => 'Roses and thorns',
            'other' => 'Other',
        ],
        'registration_type' => [
            'everyone' => 'Open to everyone',
            'members_only' => 'Members only',
            'invite' => 'Invite only',
        ],
        'participant_role' => [
            'game_master' => 'Game master',
            'co_gm' => 'Co-GM',
            'player' => 'Player',
            'spectator' => 'Spectator',
        ],
        'gm_role' => [
            'main' => 'Main game master',
            'co_gm' => 'Co-GM',
        ],
        'participant_status' => [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'waiting_list' => 'Waiting list',
            'cancelled' => 'Cancelled',
            'rejected' => 'Rejected',
            'no_show' => 'No show',
            'expired' => 'Expired',
        ],
        'campaign_status' => [
            'recruiting' => 'Recruiting',
            'active' => 'Active',
            'on_hold' => 'On hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        ],
        'roster_access' => [
            'anyone' => 'Any account',
            'members_only' => 'Members only',
        ],
        'join_request_status' => [
            'pending' => 'Pending',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
            'withdrawn' => 'Withdrawn',
        ],
        'campaign_frequency' => [
            'weekly' => 'Weekly',
            'biweekly' => 'Biweekly',
            'monthly' => 'Monthly',
            'irregular' => 'Irregular',
        ],
        'warning_severity' => [
            'mild' => 'Mild',
            'moderate' => 'Moderate',
            'severe' => 'Severe',
        ],
        'language' => [
            'es' => 'Spanish',
            'en' => 'English',
            'ca' => 'Catalan',
            'eu' => 'Basque',
            'gl' => 'Galician',
        ],
        'scheduling_mode' => [
            'free' => 'Free schedule',
            'free_description' => 'Tables can be scheduled at any time during the event',
            'slots' => 'Slot-based',
            'slots_description' => 'Tables are assigned to predefined time slots',
        ],
        'location_mode' => [
            'free' => 'Free choice',
            'free_description' => 'The table creator chooses the location',
            'fixed' => 'Fixed location',
            'fixed_description' => 'All tables use a predefined location',
            'event' => 'Event location',
            'event_description' => 'Tables use the event location',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'upcoming_tables' => [
            'title' => 'Upcoming tables',
            'no_tables' => 'No scheduled tables',
        ],
        'table_stats' => [
            'title' => 'Statistics',
            'total_tables' => 'Total tables',
            'upcoming_tables' => 'Upcoming tables',
            'active_campaigns' => 'Active campaigns',
            'total_participants' => 'Total participants',
        ],
        'popular_systems' => [
            'title' => 'Popular systems',
            'no_data' => 'No data available',
        ],
        'pending_moderation' => [
            'title' => 'Pending moderation',
            'type' => 'Type',
            'count' => ':count table(s) pending',
            'no_pending' => 'No tables or campaigns pending review',
            'view_all' => 'View all',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Module settings',
        'sections' => [
            'membership' => 'Early access for association members',
            'registration' => 'Registrations',
            'guest_registrations' => 'Guest registrations',
            'automation' => 'Automation',
            'defaults' => 'Default values',
            'notifications' => 'Notifications',
            'limits' => 'Limits',
        ],
        'fields' => [
            'membership_enabled' => 'Early access for association members',
            'membership_enabled_help' => 'Holders of the association member role chosen in the site settings can register before opening, by the early access days of each table. It does not depend on the memberships module',
            'waiting_list_enabled' => 'Waiting list',
            'waiting_list_enabled_help' => 'When the player seats are taken, players can join the waiting list. Each freed seat goes automatically to the first in the list until the table starts (or until registrations close, if that comes first); after that, automatic promotion stops and the game master can promote by hand. On tables with manual confirmation pending players hold seats and the promoted player waits for the game master to confirm them. Spectators have no waiting list',
            'auto_status_enabled' => 'Update the status of tables automatically',
            'auto_status_enabled_help' => 'Every five minutes, scheduled or full tables move to «In progress» at their start time and tables in progress move to «Completed» when they end (start plus duration). No emails are sent. Drafts and cancelled tables never change. When off, the status only changes with the Start and Complete actions of the panel',
            'early_access_days' => 'Early access days',
            'early_access_days_help' => 'Initial member early access days of each new table',
            'default_duration' => 'Default duration (minutes)',
            'default_min_players' => 'Default minimum players',
            'default_max_players' => 'Default maximum players',
            'default_max_spectators' => 'Default maximum spectators',
            'auto_confirm' => 'Auto confirm registrations',
            'auto_confirm_help' => 'Registrations are confirmed without GM intervention',
            'default_registration_type' => 'Default registration type',
            'notify_on_registration' => 'Notify on registration',
            'notify_on_cancellation' => 'Notify on cancellation',
            'notify_waiting_list' => 'Notify waiting list promotion',
            'notify_waiting_list_help' => 'Tells the directors and the table email. The person moved up from the waiting list always gets their own email.',
            'send_reminder' => 'Send a reminder before the table',
            'send_reminder_help' => 'An email to every confirmed participant (account or confirmed email) the given hours before the start. Off by default.',
            'reminder_hours_before' => 'Reminder lead time (hours)',
            'max_players_limit' => 'Maximum players limit',
            'max_spectators_limit' => 'Maximum spectators limit',
            'max_duration' => 'Maximum duration (minutes)',
            'guest_confirmation_mode' => 'Seat while not confirmed',
            'guest_hold_hours' => 'Confirmation deadline (hours)',
            'guest_max_unconfirmed_held' => 'Maximum unconfirmed seats per table',
            'guest_max_unconfirmed_held_help' => 'Unconfirmed guest registrations beyond this number wait without taking a seat.',
            'guest_accounts_mode' => 'Email with a registered account',
            'guest_rate_per_minute' => 'Guest registrations per minute and connection',
            'guest_rate_per_hour' => 'Guest registrations per hour and connection',
            'duplicate_window_hours' => '"Possible duplicate" window (hours)',
        ],
        'guest_confirmation_mode' => [
            'hold' => 'Holds the seat with a deadline',
            'wait' => 'Takes no seat until confirmed',
        ],
        'guest_accounts_mode' => [
            'require_login' => 'Ask them to sign in',
            'allow_guest' => 'Allow as a guest unless that account is already registered',
        ],
        'validation' => [
            'above_players_limit' => 'It cannot exceed the maximum players limit (:limit)',
            'above_spectators_limit' => 'It cannot exceed the maximum spectators limit (:limit)',
            'above_duration_limit' => 'It cannot exceed the maximum duration (:limit minutes)',
            'min_above_max_players' => 'It cannot exceed the default maximum players (:limit)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'gametables' => [
            'view_any' => 'View table list',
            'view' => 'View table',
            'create' => 'Create table',
            'update' => 'Edit table',
            'delete' => 'Delete table',
        ],
        'campaigns' => [
            'view_any' => 'View campaign list',
            'view' => 'View campaign',
            'create' => 'Create campaign',
            'update' => 'Edit campaign',
            'delete' => 'Delete campaign',
        ],
        'gamesystems' => [
            'view_any' => 'View game systems',
            'manage' => 'Manage game systems',
        ],
        'contentwarnings' => [
            'view_any' => 'View content warnings',
            'manage' => 'Manage content warnings',
        ],
        'settings' => 'Configure table module',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'publish' => 'Publish',
        'unpublish' => 'Unpublish',
        'start' => 'Start',
        'complete' => 'Complete',
        'cancel' => 'Cancel table',
        'create_publisher' => 'Create publisher',
        'add_game_master' => 'Add game master',
        'view' => 'View',
    ],

    'bulk_actions' => [
        'approve' => 'Approve',
        'reject' => 'Reject',
        'approved_count' => ':count table(s) approved',
        'rejected_count' => ':count table(s) rejected',
    ],

    'table_tabs' => [
        'all' => 'All',
        'pending_moderation' => 'Pending moderation',
    ],

    'moderation' => [
        'approve' => 'Approve',
        'reject' => 'Reject',
        'approve_confirmation' => 'Approve this table?',
        'approve_description' => 'The table will be published and visible to all users.',
        'reject_confirmation' => 'Reject this table?',
        'reject_description' => 'The creator will be notified of the rejection with the reason provided.',
        'rejection_reason' => 'Rejection reason',
        'rejection_reason_placeholder' => 'Explain why this table is being rejected...',
        'campaign_approve_confirmation' => 'Approve this campaign?',
        'campaign_approve_description' => 'The campaign will be published and visible to all users.',
        'campaign_reject_confirmation' => 'Reject this campaign?',
        'campaign_reject_description' => 'The campaign will not be published; its creator will see the reason in their campaigns.',
        'campaign_rejection_reason_placeholder' => 'Explain why this campaign is rejected...',
    ],

    'notifications' => [
        'table_cancelled' => 'Table cancelled successfully',
        'table_approved' => 'Table approved successfully',
        'table_rejected' => 'Table rejected successfully',
        'campaign_approved' => 'Campaign approved successfully',
        'campaign_rejected' => 'Campaign rejected successfully',
        'status_already_changed' => 'The table is no longer in a status that allows this change',
        'campaign_status_already_changed' => 'The campaign is no longer in a status that allows this change',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
    'cookies' => [
        'guest_registrations' => [
            'purpose' => 'Remembers which tables you signed up to as a guest from this browser, to warn you if you try to sign up again.',
            'duration' => '90 days',
        ],
    ],

    'messages' => [
        'registration' => [
            'success' => 'Registration successful',
            'cancelled' => 'Registration cancelled',
            'waiting_list' => 'Added to waiting list',
            'promoted' => 'Promoted from waiting list',
            'already_registered' => 'You are already registered for this table',
            'table_full' => 'The table is full',
            'registration_closed' => 'Registration is closed',
            'members_only' => 'This table is for members only',
            'minimum_age' => 'You do not meet the minimum age requirement',
        ],
        'table' => [
            'published' => 'Table published successfully',
            'unpublished' => 'Table unpublished',
            'started' => 'Table started',
            'completed' => 'Table marked as completed',
            'cancelled' => 'Table cancelled',
        ],
        'registration_status' => [
            'opens_in' => 'Opens in :days days',
            'open_until' => 'Open until :date',
            'closed' => 'Closed',
            'member_early_access' => 'Member early access active',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Errors & Success Messages
    |--------------------------------------------------------------------------
    */
    'errors' => [
        'unauthenticated' => 'You must be logged in to perform this action',
        'registration_closed' => 'Registration is closed',
        'registration_not_open' => 'Registration is not open yet',
        'table_started' => 'The session has already started',
        'table_ended' => 'The session has already ended',
        'table_full' => 'The table is full',
        'spectators_full' => 'No spectator seats are left',
        'already_registered' => 'You are already registered for this table',
        'members_only' => 'This table is for members only',
        'invitation_only' => 'This table is by invitation only: the organisation registers the players',
        'minimum_age' => 'You do not meet the minimum age requirement',
        'not_found' => 'Registration not found',
        'cannot_cancel' => 'Unable to cancel registration at this time',
        'cannot_cancel_started' => 'The session has already started: you can no longer cancel your registration',
        'cannot_mark_no_show' => 'Only a confirmed participant can be marked as a no-show, once the table has started',
        'table_not_found' => 'Table not found',
        'campaign_not_found' => 'Campaign not found',
        'guest_already_registered' => 'This email is already registered for this table',
        'email_registered_as_guest' => 'There is already a registration with your email.',
        'email_has_account' => 'This email belongs to an account. Sign in to register.',
        'guests_not_allowed' => 'This table does not allow guest registrations',
        'invalid_token' => 'The cancellation link is invalid or has expired',
        'invalid_confirmation' => 'This link is no longer valid. If there are seats left, you can register again.',
        'confirmation_closed' => 'Registrations have already closed.',
        'confirmation_filled_up' => 'The table filled up while you were confirming.',
        'too_many_confirmation_attempts' => 'Too many attempts with this link. Wait a moment and try again.',
        'guest_rate_limited' => 'You have made too many registrations in a row. Wait a few minutes and try again.',
        'session_below_roster' => 'This session has fewer seats (:max) than the campaign roster (:count). Raise the maximum number of players before publishing it.',
    ],

    'success' => [
        'registered' => 'You have successfully registered',
        'cancelled' => 'Registration cancelled successfully',
        'waiting_list' => 'You have joined the waiting list (position :position). If a seat is freed before the table starts (or before registrations close, if that comes first), you will take it automatically and we will let you know by email.',
        'waiting_list_manual' => 'You have joined the waiting list (position :position). If a seat is freed before the table starts (or before registrations close, if that comes first), you will take it pending confirmation by the game master and we will let you know by email.',
        'guest_email_confirmed' => 'Your registration is confirmed. We have sent you an email with the details.',
        'guest_email_already_confirmed' => 'Your registration was already confirmed.',
        'guest_email_confirmed_pending' => 'You have confirmed your email. Your registration is pending approval by the organisation: we will let you know by email.',
        'guest_email_confirmed_waiting_list' => 'You have confirmed your email. You are on the waiting list (position :position): if a seat is freed, we will let you know by email.',
        'registration_linked' => 'You already had a guest registration; it is now on your account.',
        'registration_linked_waiting_list' => 'You already had a guest registration; it is now on your account. You are on the waiting list (position :position).',
        'guest_confirmation_pending' => 'You already have a pending registration: check your email.',
        'guest_confirmation_sent' => 'We have sent you an email to confirm the registration. Confirm it before :deadline or the seat will be released.',
        'guest_confirmation_sent_waiting_list' => 'We have sent you an email to confirm the registration. Confirm it before :deadline to keep your place :position on the waiting list.',
        'guest_confirmation_sent_unguaranteed' => 'We have sent you an email to confirm the registration. Your seat is not guaranteed until you confirm it.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'first_name' => [
            'required' => 'Name is required',
            'max' => 'Name cannot exceed :max characters',
        ],
        'email' => [
            'required' => 'Email is required',
            'email' => 'Enter a valid email address',
            'max' => 'Email cannot exceed :max characters',
        ],
        'phone' => [
            'max' => 'Phone cannot exceed :max characters',
        ],
        'role' => [
            'required' => 'You must select a role',
            'in' => 'The selected role is invalid',
        ],
        'gdpr_consent' => [
            'required' => 'You must accept the privacy policy',
            'accepted' => 'You must accept the privacy policy',
        ],
        'title_required' => 'Title is required',
        'title_min' => 'Title must be at least 5 characters',
        'starts_at_future' => 'Start date must be in the future',
        'max_players_gte_min' => 'Maximum players must be greater than or equal to minimum',
        'language_required' => 'Language is required',
        'experience_level_required' => 'Experience level is required',
        'character_creation_required' => 'Character creation type is required',
        'campaign_not_owned' => 'You can only link the table to a campaign you created',
        'event_not_open' => 'You can only link the table to an event open to new tables',
        'registration_closes_after_start' => 'Registrations cannot close after the table starts (:date)',
        'registration_closes_after_end' => 'Registrations cannot close after the session ends (:date)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Relation Managers
    |--------------------------------------------------------------------------
    */
    'relation_managers' => [
        'participants' => [
            'title' => 'Participants',
            'add' => 'Add participant',
            'confirm' => 'Confirm',
            'reject' => 'Reject',
            'promote' => 'Promote',
            'cancel_registration' => 'Cancel registration',
            'cancel_registration_description' => 'Their seat is freed (and, where it applies, the first person on the waiting list takes it). They will be told by email that the organizers have cancelled their registration.',
            'no_show' => 'No-show',
            'no_show_description' => 'They are marked as a no-show. No email is sent and nobody is promoted from the waiting list.',
            'move_to_waiting' => 'Move to waiting list',
            'confirm_email' => 'Mark email as confirmed',
            'confirm_email_description' => 'The guest\'s email will be taken as confirmed, as if they had followed the link, and they will get the registration email.',
            'flags' => [
                'label' => 'Flags',
                'email_unconfirmed' => 'Email not confirmed',
                'expires_at' => 'Expires on :date',
                'possible_duplicate' => 'Possible duplicate',
                'possible_duplicate_tooltip' => 'Another registration at this table was made from the same connection and browser :hours h apart or less',
            ],
            'delete_not_allowed' => 'Not deleted: the registration is no longer cancelled, rejected or expired',
            'bulk_delete' => [
                'deleted' => '{1} 1 registration deleted|[2,*] :count registrations deleted',
                'none_deleted' => 'No registration deleted',
                'skipped' => '{1} 1 registration was not deleted: only cancelled, rejected or expired ones can be deleted; cancel it first with “Cancel registration”.|[2,*] :count registrations were not deleted: only cancelled, rejected or expired ones can be deleted; cancel them first with “Cancel registration”.',
                'failed' => '{1} 1 registration could not be deleted because of an error.|[2,*] :count registrations could not be deleted because of an error.',
            ],
        ],
        'sessions' => [
            'title' => 'Sessions',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Types
    |--------------------------------------------------------------------------
    */
    'registration_types' => [
        'everyone' => 'Open to everyone',
        'members_only' => 'Members only',
    ],

    /*
    |--------------------------------------------------------------------------
    | Event Game Table Configuration
    |--------------------------------------------------------------------------
    */
    'event_config' => [
        'title' => 'Game tables configuration',
        'general' => 'General',
        'tables_enabled' => 'Enable tables for this event',
        'tables_enabled_help' => 'Allows game tables to be created within this event',

        // Scheduling
        'scheduling' => 'Scheduling',
        'scheduling_mode' => 'Scheduling mode',
        'time_slots' => 'Time slots',
        'time_slot_label' => 'Slot name',
        'start_time' => 'Start time',
        'end_time' => 'End time',
        'max_tables' => 'Maximum tables',

        // Location
        'location' => 'Location',
        'location_mode' => 'Location mode',
        'fixed_location' => 'Fixed location',
        'fixed_location_help' => 'All tables will be located here',

        // Eligibility override
        'eligibility_override' => 'Override eligibility',
        'eligibility_override_description' => 'Override the global configuration for who can create tables',
        'eligibility_override_enabled' => 'Use event configuration',
        'eligibility_override_enabled_help' => 'Ignores global settings for this event',
        'eligibility_access_level' => 'Access level',
        'eligibility_allowed_roles' => 'Allowed roles',
        'eligibility_required_permission' => 'Required permission',
        'eligibility_required_permission_help' => 'Permission name required to create tables in this event',

        // Table creation
        'table_creation' => 'Table creation',

        // Early access
        'early_access' => 'Early access',
        'early_access_description' => 'Allows certain users to create tables before the general opening date',
        'early_access_enabled' => 'Enable early access',
        'early_access_enabled_help' => 'Users with special roles/permissions can create tables before others',
        'creation_opens_at' => 'General opening date',
        'creation_opens_at_help' => 'Date from which all eligible users can create tables',
        'early_access_type' => 'Early access type',
        'early_access_roles' => 'Roles with early access',
        'early_access_roles_help' => 'Users with these roles can create tables earlier',
        'early_access_permission' => 'Early access permission',
        'early_access_permission_help' => 'Users with this permission can create tables earlier',
        'early_access_days_before' => 'Days advantage',
        'early_access_days_before_help' => 'Number of days before general opening they can create tables',

        // Actions
        'save' => 'Save configuration',
        'saved' => 'Configuration saved',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest confirmation page
    |--------------------------------------------------------------------------
    */
    'confirm_registration' => [
        // The state shown until the guest follows the emailed link
        'awaiting_email' => 'Awaiting confirmation',
    ],

    'roster' => [
        'errors' => [
            'unavailable' => 'This campaign is not published.',
            'finished' => 'The campaign has ended: its roster no longer changes.',
            'not_accepting' => 'This campaign is not accepting new players.',
            'roster_full' => 'The roster is full.',
            'members_only' => 'This campaign only accepts requests from members.',
            'already_member' => 'You are already part of the roster.',
            'account_already_member' => 'That account is already part of the roster.',
            'request_pending' => 'You already have a pending request.',
            'is_director' => 'Whoever runs the campaign cannot join the roster as a player.',
            'account_not_found' => 'That account could not be added. Check the email.',
            'not_pending' => 'This request is no longer pending.',
            'not_member' => 'That person is not part of the roster.',
            'max_below_roster' => 'The maximum number of players cannot be lower than the current roster (:count).',
            'session_not_in_campaign' => 'That session does not belong to this campaign.',
            'session_started' => 'The session has already started.',
            'nothing_to_change' => 'There is nothing to change in that session.',
        ],
    ],

    // Server-rendered page metadata (link previews and search engines)
    'seo' => [
        'tables_title' => 'Game tables',
        'tables_description' => 'Find and join role-playing sessions',
        'calendar_title' => 'Game table calendar',
        'campaigns_title' => 'Campaigns',
        'campaigns_description' => 'Explore the ongoing role-playing campaigns',
        'create_table_title' => 'Create table',
        'edit_table_title' => 'Edit table',
        'my_tables_title' => 'My game tables',
        'cancel_registration_title' => 'Cancel registration',
        'confirm_registration_title' => 'Confirm registration',
        'create_campaign_title' => 'Create campaign',
        'edit_campaign_title' => 'Edit campaign',
        'my_campaigns_title' => 'My campaigns',
    ],
];
