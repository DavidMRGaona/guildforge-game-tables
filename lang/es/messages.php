<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation & UI Labels
    |--------------------------------------------------------------------------
    */
    'navigation_group' => 'Mesas de rol',

    'navigation' => [
        'group' => 'Mesas de rol',
        'catalog_group' => 'Catálogo de juegos',
        'tables' => 'Mesas',
        'campaigns' => 'Campañas',
        'game_systems' => 'Sistemas de juego',
        'publishers' => 'Editoriales',
        'content_warnings' => 'Avisos de contenido',
        'settings' => 'Configuración',
        'config' => 'Configuración',
    ],

    'routes' => [
        'tables' => 'Mesas de rol',
        'tables_calendar' => 'Calendario de mesas',
        'campaigns' => 'Campañas',
    ],

    'model' => [
        'game_table' => [
            'singular' => 'Mesa',
            'plural' => 'Mesas',
        ],
        'campaign' => [
            'singular' => 'Campaña',
            'plural' => 'Campañas',
        ],
        'game_system' => [
            'singular' => 'Sistema de juego',
            'plural' => 'Sistemas de juego',
        ],
        'publisher' => [
            'singular' => 'Editorial',
            'plural' => 'Editoriales',
        ],
        'content_warning' => [
            'singular' => 'Aviso de contenido',
            'plural' => 'Avisos de contenido',
        ],
        'participant' => [
            'singular' => 'Participante',
            'plural' => 'Participantes',
        ],
    ],

    'pages' => [
        'create_table' => 'Crear mesa',
        'edit_table' => 'Editar mesa',
        'create_campaign' => 'Crear campaña',
        'edit_campaign' => 'Editar campaña',
        'create_game_system' => 'Crear sistema de juego',
        'edit_game_system' => 'Editar sistema de juego',
        'create_publisher' => 'Crear editorial',
        'edit_publisher' => 'Editar editorial',
        'create_content_warning' => 'Crear aviso de contenido',
        'edit_content_warning' => 'Editar aviso de contenido',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tabs
    |--------------------------------------------------------------------------
    */
    'tabs' => [
        'basic_info' => 'Información básica',
        'direction' => 'Dirección',
        'schedule' => 'Horario y ubicación',
        'capacity' => 'Capacidad y requisitos',
        'content' => 'Contenido y seguridad',
        'registration' => 'Inscripciones',
        'publication' => 'Publicación',
        'progress' => 'Progreso',
    ],

    /*
    |--------------------------------------------------------------------------
    | Form Fields
    |--------------------------------------------------------------------------
    */
    'fields' => [
        // GameSystem fields
        'name' => 'Nombre',
        'slug' => 'Slug',
        'description' => 'Descripción',
        'publisher' => 'Editorial',
        'edition' => 'Edición',
        'year' => 'Año',
        'logo_url' => 'Logo',
        'website_url' => 'Sitio web',
        'is_active' => 'Activo',

        // Publisher fields
        'country' => 'País',
        'game_systems_count' => 'Sistemas',

        // ContentWarning fields
        'label' => 'Etiqueta',
        'severity' => 'Severidad',
        'icon' => 'Icono',

        // GameTable fields
        'title' => 'Título',
        'game_system' => 'Sistema de juego',
        'campaign' => 'Campaña',
        'event' => 'Evento',
        'game_master' => 'Director de juego',
        'synopsis' => 'Sinopsis',
        'start_time' => 'Fecha y hora de inicio',
        'duration_minutes' => 'Duración (minutos)',
        'location' => 'Ubicación',
        'online_url' => 'URL online',
        'min_players' => 'Mínimo de jugadores',
        'max_players' => 'Máximo de jugadores',
        'max_spectators' => 'Máximo de espectadores',
        'minimum_age' => 'Edad mínima',
        'language' => 'Idioma',
        'table_type' => 'Tipo de mesa',
        'table_format' => 'Formato',
        'table_status' => 'Estado',
        'genres' => 'Géneros',
        'tone' => 'Tono',
        'experience_level' => 'Nivel de experiencia',
        'character_creation' => 'Creación de personajes',
        'safety_tools' => 'Herramientas de seguridad',
        'content_warnings' => 'Avisos de contenido',
        'custom_warnings' => 'Avisos personalizados',
        'registration_type' => 'Tipo de inscripción',
        'members_early_access_days' => 'Días de acceso anticipado para socios',
        'registration_opens_at' => 'Apertura de inscripciones',
        'registration_closes_at' => 'Cierre de inscripciones',
        'auto_confirm' => 'Confirmar automáticamente',
        'accepts_registrations_in_progress' => 'Permitir inscripciones durante la partida',
        'accepts_registrations_in_progress_help' => 'Permite que usuarios se inscriban aunque la mesa esté en progreso',
        'notification_email' => 'Email de notificaciones',
        'notification_email_help' => 'Dirección de correo donde se enviarán las notificaciones de inscripciones y bajas',
        'is_published' => 'Publicado',
        'is_published_help' => 'Controla la visibilidad pública de la mesa. Las inscripciones se abren automáticamente según las fechas configuradas.',
        'is_published_campaign_help' => 'Controla la visibilidad pública de la campaña.',
        'published_at' => 'Fecha de publicación',
        'notes' => 'Notas',

        // Campaign fields
        'session_count' => 'Número de sesiones',
        'session_count_help' => 'Número total de sesiones previstas. Dejar vacío si no está definido.',
        'current_session' => 'Sesión actual',
        'current_session_help' => 'Usa 0 para campañas que aún no han comenzado.',
        'frequency' => 'Frecuencia',
        'campaign_status' => 'Estado de la campaña',
        'accepts_new_players' => 'Acepta nuevos jugadores',
        'accepts_new_players_help' => 'Indica si la campaña está buscando nuevos jugadores activamente.',

        // Participant fields
        'user' => 'Usuario',
        'participant_role' => 'Rol',
        'participant_status' => 'Estado',
        'registered_at' => 'Fecha de inscripción',
        'confirmed_at' => 'Fecha de confirmación',
        'cancelled_at' => 'Fecha de cancelación',
        'waiting_list_position' => 'Posición en lista de espera',
        'player_notes' => 'Notas del jugador',
        'gm_notes' => 'Notas del director',

        // External person fields
        'first_name' => 'Nombre',
        'last_name' => 'Apellidos',
        'email' => 'Email',
        'phone' => 'Teléfono',

        // Game master fields
        'gm_type' => 'Tipo',
        'gm_type_user' => 'Usuario de la plataforma',
        'gm_type_external' => 'Persona externa',
        'gm_role' => 'Rol',
        'custom_title' => 'Título personalizado',
        'custom_title_placeholder' => 'Ej: Dungeon Master, Narrador...',
        'custom_title_help' => 'Deja vacío para usar el título del sistema de juego',
        'notify_by_email' => 'Notificar por email',
        'is_name_public' => 'Nombre visible públicamente',
        'game_master_title' => 'Título del director de juego',
        'game_master_title_help' => 'Ej: Dungeon Master, Keeper, Narrador, Árbitro...',

        // Participant type fields
        'participant_type' => 'Tipo',
        'participant_type_user' => 'Usuario de la plataforma',
        'participant_type_external' => 'Persona externa',

        // Custom warnings
        'custom_warnings_placeholder' => 'Añadir aviso personalizado',

        // Game masters
        'game_masters' => 'Directores de juego',
        'inherited_badge' => '(heredado)',
        'excluded_badge' => '(excluido)',
        'inherited_from_campaign' => 'Heredado de la campaña',
        'exclude_from_table' => 'Excluir de esta mesa',
        'exclude_from_table_help' => 'El director seguirá en la campaña pero no aparecerá en esta mesa',

        // Image
        'image' => 'Imagen',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sections
    |--------------------------------------------------------------------------
    */
    'sections' => [
        'basic_info' => 'Información básica',
        'game_details' => 'Detalles del juego',
        'schedule' => 'Horario y ubicación',
        'capacity' => 'Capacidad',
        'requirements' => 'Requisitos',
        'content' => 'Contenido',
        'safety' => 'Seguridad y avisos',
        'registration' => 'Inscripciones',
        'publication' => 'Publicación',
        'participants' => 'Participantes',
        'campaign_info' => 'Información de campaña',
        'game_masters' => 'Directores de juego',
    ],

    /*
    |--------------------------------------------------------------------------
    | Enums
    |--------------------------------------------------------------------------
    */
    'enums' => [
        'table_type' => [
            'one_shot' => 'One-shot',
            'adventure' => 'Aventura',
            'campaign_session' => 'Sesión de campaña',
            'demo' => 'Demo',
            'tutorial' => 'Tutorial',
        ],
        'table_format' => [
            'in_person' => 'Presencial',
            'online' => 'Online',
            'hybrid' => 'Híbrido',
        ],
        'table_status' => [
            'draft' => 'Borrador',
            'scheduled' => 'Programada',
            'full' => 'Completa',
            'in_progress' => 'En progreso',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ],
        'genre' => [
            'fantasy' => 'Fantasía',
            'horror' => 'Terror',
            'sci_fi' => 'Ciencia ficción',
            'post_apocalyptic' => 'Post-apocalíptico',
            'cyberpunk' => 'Cyberpunk',
            'steampunk' => 'Steampunk',
            'historical' => 'Histórico',
            'modern' => 'Moderno',
            'superhero' => 'Superhéroes',
            'mystery' => 'Misterio',
            'western' => 'Western',
            'comedy' => 'Comedia',
            'other' => 'Otro',
        ],
        'tone' => [
            'serious' => 'Serio',
            'light' => 'Ligero',
            'mixed' => 'Mixto',
            'dark' => 'Oscuro',
        ],
        'experience_level' => [
            'none' => 'Sin experiencia necesaria',
            'beginner' => 'Principiante',
            'intermediate' => 'Intermedio',
            'advanced' => 'Avanzado',
        ],
        'character_creation' => [
            'pre_generated' => 'Personajes pregenerados',
            'bring_own' => 'Trae tu personaje',
            'create_at_table' => 'Creación en mesa',
            'any' => 'Cualquiera',
        ],
        'safety_tool' => [
            'x_card' => 'Carta X',
            'lines_and_veils' => 'Líneas y velos',
            'open_door' => 'Puerta abierta',
            'stars' => 'Estrellas y deseos',
            'support_flower' => 'Flor de apoyo',
            'script' => 'Script change',
            'roses' => 'Rosas y espinas',
            'other' => 'Otro',
        ],
        'registration_type' => [
            'everyone' => 'Abierto a todos',
            'members_only' => 'Solo socios',
            'invite' => 'Solo por invitación',
        ],
        'participant_role' => [
            'game_master' => 'Director de juego',
            'co_gm' => 'Co-director',
            'player' => 'Jugador',
            'spectator' => 'Espectador',
        ],
        'gm_role' => [
            'main' => 'Director principal',
            'co_gm' => 'Co-director',
        ],
        'participant_status' => [
            'pending' => 'Pendiente',
            'confirmed' => 'Confirmado',
            'waiting_list' => 'Lista de espera',
            'cancelled' => 'Cancelado',
            'rejected' => 'Rechazado',
            'no_show' => 'No presentado',
        ],
        'campaign_status' => [
            'recruiting' => 'Reclutando',
            'active' => 'Activa',
            'on_hold' => 'En pausa',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ],
        'campaign_frequency' => [
            'weekly' => 'Semanal',
            'biweekly' => 'Quincenal',
            'monthly' => 'Mensual',
            'irregular' => 'Irregular',
        ],
        'warning_severity' => [
            'mild' => 'Leve',
            'moderate' => 'Moderada',
            'severe' => 'Severa',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets' => [
        'upcoming_tables' => [
            'title' => 'Próximas mesas',
            'no_tables' => 'No hay mesas programadas',
        ],
        'table_stats' => [
            'title' => 'Estadísticas',
            'total_tables' => 'Total de mesas',
            'upcoming_tables' => 'Mesas próximas',
            'active_campaigns' => 'Campañas activas',
            'total_participants' => 'Participantes totales',
        ],
        'popular_systems' => [
            'title' => 'Sistemas populares',
            'no_data' => 'Sin datos disponibles',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    'settings' => [
        'title' => 'Configuración del módulo',
        'sections' => [
            'creation' => 'Creación de mesas',
            'membership' => 'Integración con socios',
            'defaults' => 'Valores por defecto',
            'notifications' => 'Notificaciones',
            'limits' => 'Límites',
        ],
        'fields' => [
            'creators' => 'Quién puede crear mesas',
            'creators_help' => 'Define quiénes pueden crear nuevas mesas de juego',
            'membership_enabled' => 'Integración con socios habilitada',
            'membership_enabled_help' => 'Permite acceso anticipado y restricciones para socios',
            'early_access_days' => 'Días de acceso anticipado',
            'early_access_days_help' => 'Días de ventaja para inscripción de socios',
            'default_duration' => 'Duración por defecto (minutos)',
            'default_min_players' => 'Mínimo de jugadores por defecto',
            'default_max_players' => 'Máximo de jugadores por defecto',
            'default_max_spectators' => 'Máximo de espectadores por defecto',
            'auto_confirm' => 'Confirmar inscripciones automáticamente',
            'auto_confirm_help' => 'Las inscripciones se confirman sin intervención del director',
            'default_registration_type' => 'Tipo de inscripción por defecto',
            'notify_on_registration' => 'Notificar al inscribirse',
            'notify_on_cancellation' => 'Notificar al cancelar',
            'notify_waiting_list' => 'Notificar promoción de lista de espera',
            'max_players_limit' => 'Límite máximo de jugadores',
            'max_spectators_limit' => 'Límite máximo de espectadores',
            'max_duration' => 'Duración máxima (minutos)',
        ],
        'creators' => [
            'admin' => 'Solo administradores',
            'members' => 'Cualquier socio',
            'permission' => 'Usuarios con permiso específico',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */
    'permissions' => [
        'gametables' => [
            'view_any' => 'Ver listado de mesas',
            'view' => 'Ver mesa',
            'create' => 'Crear mesa',
            'update' => 'Editar mesa',
            'delete' => 'Eliminar mesa',
        ],
        'campaigns' => [
            'view_any' => 'Ver listado de campañas',
            'view' => 'Ver campaña',
            'create' => 'Crear campaña',
            'update' => 'Editar campaña',
            'delete' => 'Eliminar campaña',
        ],
        'gamesystems' => [
            'view_any' => 'Ver sistemas de juego',
            'manage' => 'Gestionar sistemas de juego',
        ],
        'contentwarnings' => [
            'view_any' => 'Ver avisos de contenido',
            'manage' => 'Gestionar avisos de contenido',
        ],
        'settings' => 'Configurar módulo de mesas',
    ],

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */
    'actions' => [
        'publish' => 'Publicar',
        'unpublish' => 'Despublicar',
        'start' => 'Iniciar',
        'complete' => 'Completar',
        'cancel' => 'Cancelar mesa',
        'cancel_confirmation_title' => '¿Cancelar esta mesa?',
        'cancel_confirmation_description' => 'Esta acción cancelará la mesa y notificará a todos los participantes inscritos. Esta acción no se puede deshacer.',
        'cancel_confirm' => 'Sí, cancelar mesa',
        'create_publisher' => 'Crear editorial',
        'add_game_master' => 'Añadir director de juego',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
    'messages' => [
        'registration' => [
            'success' => 'Inscripción realizada correctamente',
            'cancelled' => 'Inscripción cancelada',
            'waiting_list' => 'Añadido a la lista de espera',
            'promoted' => 'Promovido desde la lista de espera',
            'already_registered' => 'Ya estás inscrito en esta mesa',
            'table_full' => 'La mesa está completa',
            'registration_closed' => 'Las inscripciones están cerradas',
            'members_only' => 'Esta mesa es solo para socios',
            'minimum_age' => 'No cumples con la edad mínima requerida',
        ],
        'table' => [
            'published' => 'Mesa publicada correctamente',
            'unpublished' => 'Mesa despublicada',
            'started' => 'Mesa iniciada',
            'completed' => 'Mesa marcada como completada',
            'cancelled' => 'Mesa cancelada',
        ],
        'registration_status' => [
            'opens_in' => 'Abre en :days días',
            'open_until' => 'Abierta hasta :date',
            'closed' => 'Cerrada',
            'member_early_access' => 'Acceso anticipado para socios activo',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Errors & Success Messages
    |--------------------------------------------------------------------------
    */
    'errors' => [
        'unauthenticated' => 'Debes iniciar sesión para realizar esta acción',
        'registration_closed' => 'Las inscripciones están cerradas',
        'registration_not_open' => 'Las inscripciones aún no están abiertas',
        'table_full' => 'La mesa está completa',
        'spectators_full' => 'No quedan plazas de espectador disponibles',
        'already_registered' => 'Ya estás inscrito en esta mesa',
        'guest_already_registered' => 'Ya existe una inscripción con este email',
        'members_only' => 'Esta mesa es solo para socios',
        'guests_not_allowed' => 'Esta mesa no permite inscripciones de invitados',
        'minimum_age' => 'No cumples con la edad mínima requerida',
        'not_found' => 'No se encontró la inscripción',
        'cannot_cancel' => 'No es posible cancelar la inscripción en este momento',
        'table_not_found' => 'No se encontró la mesa',
        'campaign_not_found' => 'No se encontró la campaña',
        'invalid_token' => 'El enlace de cancelación no es válido o ha expirado',
    ],

    'success' => [
        'registered' => 'Te has inscrito correctamente',
        'guest_registered' => 'Te has inscrito correctamente. Recibirás un email de confirmación.',
        'cancelled' => 'Inscripción cancelada correctamente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'table_cancelled' => 'Mesa cancelada correctamente',
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Messages
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'invalid_role' => 'El rol seleccionado no es válido',
        'notes_max' => 'Las notas no pueden superar los 500 caracteres',
        'first_name_required' => 'El nombre es obligatorio',
        'first_name_max' => 'El nombre no puede superar los 100 caracteres',
        'email_required' => 'El email es obligatorio',
        'email_invalid' => 'El email no es válido',
        'phone_max' => 'El teléfono no puede superar los 20 caracteres',
        'gdpr_consent_required' => 'Debes aceptar la política de privacidad para continuar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Relation Managers
    |--------------------------------------------------------------------------
    */
    'relation_managers' => [
        'participants' => [
            'title' => 'Participantes',
            'add' => 'Añadir participante',
            'confirm' => 'Confirmar',
            'reject' => 'Rechazar',
            'promote' => 'Promover',
            'move_to_waiting' => 'Mover a lista de espera',
        ],
        'sessions' => [
            'title' => 'Sesiones',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Registration Types
    |--------------------------------------------------------------------------
    */
    'registration_types' => [
        'everyone' => 'Abierto a todos',
        'members_only' => 'Solo socios',
    ],
];
