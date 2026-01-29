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
        'auto_confirm' => 'Auto confirm',
        'is_published' => 'Published',
        'is_published_help' => 'Controls the public visibility of the table. Registrations open automatically based on configured dates.',
        'is_published_campaign_help' => 'Controls the public visibility of the campaign.',
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
        'waiting_list_position' => 'Waiting list position',
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
        ],
        'campaign_status' => [
            'recruiting' => 'Recruiting',
            'active' => 'Active',
            'on_hold' => 'On hold',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Module settings',
        'sections' => [
            'creation' => 'Table creation',
            'membership' => 'Membership integration',
            'defaults' => 'Default values',
            'notifications' => 'Notifications',
            'limits' => 'Limits',
        ],
        'fields' => [
            'creators' => 'Who can create tables',
            'creators_help' => 'Defines who can create new game tables',
            'membership_enabled' => 'Membership integration enabled',
            'membership_enabled_help' => 'Enables early access and restrictions for members',
            'early_access_days' => 'Early access days',
            'early_access_days_help' => 'Days advantage for member registration',
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
            'max_players_limit' => 'Maximum players limit',
            'max_spectators_limit' => 'Maximum spectators limit',
            'max_duration' => 'Maximum duration (minutes)',
        ],
        'creators' => [
            'admin' => 'Administrators only',
            'members' => 'Any member',
            'permission' => 'Users with specific permission',
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
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
        'table_full' => 'The table is full',
        'already_registered' => 'You are already registered for this table',
        'members_only' => 'This table is for members only',
        'minimum_age' => 'You do not meet the minimum age requirement',
        'not_found' => 'Registration not found',
        'cannot_cancel' => 'Unable to cancel registration at this time',
        'table_not_found' => 'Table not found',
        'campaign_not_found' => 'Campaign not found',
        'guest_already_registered' => 'This email is already registered for this table',
        'guests_not_allowed' => 'This table does not allow guest registrations',
        'invalid_token' => 'The cancellation link is invalid or has expired',
    ],

    'success' => [
        'registered' => 'You have successfully registered',
        'cancelled' => 'Registration cancelled successfully',
        'guest_registered' => 'You have been registered successfully. You will receive a confirmation email.',
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
            'move_to_waiting' => 'Move to waiting list',
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
];
