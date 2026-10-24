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

    'calendar' => [
        'source_label' => 'Partidas',
        'meta' => [
            'game_system' => 'Sistema de juego',
            'game_master' => 'Director de juego',
            'players' => 'Jugadores',
            'format' => 'Modalidad',
        ],
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
        'registration_closes_at_help' => 'Como muy tarde, el inicio de la mesa (o su final, si admite inscripciones durante la partida). Las bajas de los participantes se cierran siempre al inicio.',
        'auto_confirm' => 'Confirmar automáticamente',
        'accepts_registrations_in_progress' => 'Permitir inscripciones durante la partida',
        'accepts_registrations_in_progress_help' => 'Admite inscripciones desde el inicio hasta el final de la partida, aunque no se haya pulsado «Iniciar», solo con plazas libres: sin lista de espera ni bajas',
        'delegates_registrations' => 'El director gestiona las inscripciones',
        'delegates_registrations_help' => 'Los directores con cuenta podrán ver los inscritos y aprobar o rechazar las solicitudes pendientes desde la web. La administración conserva todos sus permisos.',
        'notification_email' => 'Email de notificaciones',
        'notification_email_help' => 'Dirección de correo donde se enviarán las notificaciones de inscripciones y bajas',
        'is_published' => 'Publicado',
        'is_published_help' => 'Controla la visibilidad pública de la mesa. Las inscripciones se abren automáticamente según las fechas configuradas.',
        'is_published_campaign_help' => 'Controla la visibilidad pública de la campaña.',
        'is_published_not_submitted' => 'El autor aún no la ha enviado a revisión.',
        'is_published_pending_review' => 'Está pendiente de revisión: apruébala o recházala con «Aprobar» o «Rechazar».',
        'is_published_campaign_pending_review' => 'Está pendiente de revisión: apruébala o recházala desde la lista de campañas.',
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
        'roster_access' => 'Quién puede solicitar',
        'roster_access_help' => 'Quién puede pedir entrar en la plantilla desde la web.',

        // Participant fields
        'user' => 'Usuario',
        'participant_role' => 'Rol',
        'participant_status' => 'Estado',
        'registered_at' => 'Fecha de inscripción',
        'confirmed_at' => 'Fecha de confirmación',
        'cancelled_at' => 'Fecha de cancelación',
        'waiting_list_position' => 'Puesto en lista de espera',
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

        // Moderation fields
        'frontend_creation_status' => 'Estado de moderación',
        'moderation_notes' => 'Notas de moderación',
        'created_from_web' => 'Creado desde web',
        'rejection_reason' => 'Motivo del rechazo',
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
            'expired' => 'Caducada',
        ],
        'campaign_status' => [
            'recruiting' => 'Reclutando',
            'active' => 'Activa',
            'on_hold' => 'En pausa',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ],
        'roster_access' => [
            'anyone' => 'Cualquier cuenta',
            'members_only' => 'Solo socios',
        ],
        'join_request_status' => [
            'pending' => 'Pendiente',
            'accepted' => 'Aceptada',
            'rejected' => 'Rechazada',
            'withdrawn' => 'Retirada',
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
        'creation_access_level' => [
            'everyone' => 'Cualquier persona',
            'registered' => 'Usuarios registrados',
            'role' => 'Usuarios con rol específico',
            'permission' => 'Usuarios con permiso',
        ],
        'frontend_creation_status' => [
            'draft' => 'Borrador',
            'pending_review' => 'Pendiente de revisión',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
        ],
        'language' => [
            'es' => 'Español',
            'en' => 'Inglés',
            'ca' => 'Catalán',
            'eu' => 'Euskera',
            'gl' => 'Gallego',
        ],
        'scheduling_mode' => [
            'free' => 'Horario libre',
            'free_description' => 'Las mesas pueden programarse en cualquier momento durante el evento',
            'slots' => 'Por turnos',
            'slots_description' => 'Las mesas se asignan a turnos predefinidos',
        ],
        'location_mode' => [
            'free' => 'Elección libre',
            'free_description' => 'El creador de la mesa elige la ubicación',
            'fixed' => 'Ubicación fija',
            'fixed_description' => 'Todas las mesas usan una ubicación predefinida',
            'event' => 'Ubicación del evento',
            'event_description' => 'Las mesas usan la ubicación del evento',
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
        'pending_moderation' => [
            'title' => 'Pendientes de moderación',
            'type' => 'Tipo',
            'count' => ':count mesa(s) pendiente(s)',
            'no_pending' => 'No hay mesas ni campañas pendientes de revisión',
            'view_all' => 'Ver todas',
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
            'membership' => 'Acceso anticipado para socios',
            'registration' => 'Inscripciones',
            'guest_registrations' => 'Inscripciones de invitados',
            'automation' => 'Automatización',
            'defaults' => 'Valores por defecto',
            'notifications' => 'Notificaciones',
            'campaign_notifications' => 'Notificaciones de campañas',
            'campaign_notifications_description' => 'Correos de la plantilla de las campañas. Las plantillas empiezan vacías: no se envía nada hasta que alguien las use.',
            'limits' => 'Límites',
            'frontend_creation' => 'Creación desde web',
            'frontend_creation_description' => 'Permite que los usuarios creen mesas y campañas desde el frontend público',
        ],
        'fields' => [
            'membership_enabled' => 'Acceso anticipado para socios',
            'membership_enabled_help' => 'Quienes tienen el rol de socio elegido en la configuración del sitio pueden inscribirse antes de la apertura, según los días de acceso anticipado de cada mesa. No depende del módulo de gestión de socios',
            'waiting_list_enabled' => 'Lista de espera',
            'waiting_list_enabled_help' => 'Con las plazas de jugador completas, los jugadores pueden apuntarse a la lista de espera. Cada plaza liberada pasa automáticamente al primero de la lista hasta el inicio de la partida (o hasta el cierre de inscripciones, si es anterior); después, la promoción automática se detiene y el director puede promover a mano. En las mesas con confirmación manual los pendientes ocupan plaza y el promovido queda pendiente de que el director lo confirme. Los espectadores no tienen lista de espera',
            'auto_status_enabled' => 'Actualizar el estado de las mesas automáticamente',
            'auto_status_enabled_help' => 'Cada cinco minutos, las mesas programadas o completas pasan a «En progreso» a su hora de inicio y las mesas en progreso pasan a «Completada» al terminar (inicio más duración). No se envían correos. Los borradores y las mesas canceladas no cambian. Desactivado, el estado solo cambia con las acciones Iniciar y Completar del panel',
            'early_access_days' => 'Días de acceso anticipado',
            'early_access_days_help' => 'Valor inicial de los días de ventaja de los socios en cada mesa nueva',
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
            'notify_waiting_list_help' => 'Avisa a los directores y al email de la mesa. La persona que pasa de la lista de espera recibe siempre su correo.',
            'send_reminder' => 'Enviar recordatorio antes de la mesa',
            'send_reminder_help' => 'Un correo a cada inscripción confirmada (con cuenta o con el email confirmado) las horas indicadas antes del inicio. Desactivado por defecto.',
            'reminder_hours_before' => 'Antelación del recordatorio (horas)',
            'campaign_notify_session_scheduled' => 'Sesión programada (a la plantilla)',
            'campaign_notify_join_request_received' => 'Solicitud recibida (a la dirección)',
            'campaign_notify_join_request_accepted' => 'Solicitud aceptada',
            'campaign_notify_join_request_rejected' => 'Solicitud rechazada',
            'campaign_notify_roster_added' => 'Añadido a la plantilla',
            'campaign_notify_invitation_accepted' => 'Invitación aceptada (a la dirección)',
            'campaign_notify_roster_removed' => 'Quitado de la plantilla',
            'campaign_notify_player_left' => 'Salida de la plantilla (a la dirección)',
            'max_players_limit' => 'Límite máximo de jugadores',
            'max_spectators_limit' => 'Límite máximo de espectadores',
            'max_duration' => 'Duración máxima (minutos)',
            'guest_confirmation_mode' => 'Plaza mientras no confirma',
            'guest_hold_hours' => 'Plazo de confirmación (horas)',
            'guest_max_unconfirmed_held' => 'Máximo de plazas sin confirmar por mesa',
            'guest_max_unconfirmed_held_help' => 'Las inscripciones de invitado sin confirmar que superen este número esperan sin ocupar plaza.',
            'guest_accounts_mode' => 'Email con cuenta registrada',
            'guest_rate_per_minute' => 'Inscripciones de invitado por minuto y conexión',
            'guest_rate_per_hour' => 'Inscripciones de invitado por hora y conexión',
            'duplicate_window_hours' => 'Ventana de «Posible duplicado» (horas)',
            // Frontend creation fields
            'frontend_creation_enabled' => 'Habilitar creación desde web',
            'frontend_creation_enabled_help' => 'Permite que los usuarios creen mesas desde el frontend público',
            'allowed_content' => 'Contenido permitido',
            'access_level' => 'Nivel de acceso',
            'access_level_help' => 'Quién puede crear contenido desde la web',
            'allowed_roles' => 'Roles permitidos',
            'allowed_roles_help' => 'Lista de roles separados por coma',
            'required_permission' => 'Permiso requerido',
            'required_permission_help' => 'Nombre del permiso necesario para crear',
            'publication_mode' => 'Modo de publicación',
            'publication_mode_help' => 'Cómo se publican las mesas creadas por usuarios',
        ],
        'validation' => [
            'above_players_limit' => 'No puede superar el límite máximo de jugadores (:limit)',
            'above_spectators_limit' => 'No puede superar el límite máximo de espectadores (:limit)',
            'above_duration_limit' => 'No puede superar la duración máxima (:limit minutos)',
            'min_above_max_players' => 'No puede superar el máximo de jugadores por defecto (:limit)',
        ],
        'guest_confirmation_mode' => [
            'hold' => 'Reserva la plaza con plazo',
            'wait' => 'No ocupa plaza hasta confirmar',
        ],
        'guest_accounts_mode' => [
            'require_login' => 'Pedir que inicie sesión',
            'allow_guest' => 'Permitir como invitado salvo que esa cuenta ya esté inscrita',
        ],
        'allowed_content' => [
            'tables' => 'Solo mesas',
            'campaigns' => 'Solo campañas',
            'both' => 'Mesas y campañas',
        ],
        'access_level' => [
            'everyone' => 'Cualquier persona',
            'registered' => 'Usuarios registrados',
            'role' => 'Usuarios con rol específico',
            'permission' => 'Usuarios con permiso específico',
        ],
        'publication_mode' => [
            'auto' => 'Publicación automática',
            'approval' => 'Requiere aprobación',
            'role_based' => 'Según el rol del usuario',
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
        'start_confirmation_title' => '¿Iniciar esta mesa?',
        'start_confirmation_description' => 'La mesa pasará a «En progreso». Después solo se podrá completar o cancelar.',
        'start_confirmation_check' => 'Entiendo que la mesa no podrá volver a «Programada»',
        'start_confirm' => 'Sí, iniciar mesa',
        'complete' => 'Completar',
        'complete_confirmation_title' => '¿Completar esta mesa?',
        'complete_confirmation_description' => 'La mesa quedará completada y ya no podrá cambiar de estado.',
        'complete_confirmation_check' => 'Entiendo que no se puede deshacer',
        'complete_confirm' => 'Sí, completar mesa',
        'cancel' => 'Cancelar mesa',
        'cancel_confirmation_title' => '¿Cancelar esta mesa?',
        'cancel_confirmation_description' => 'Esta acción cancelará la mesa y notificará a todos los participantes inscritos. Esta acción no se puede deshacer.',
        'cancel_confirmation_check' => 'Entiendo que se cancelarán todas las inscripciones, que se avisará por correo a los participantes y que no se puede deshacer',
        'cancel_confirm' => 'Sí, cancelar mesa',
        'delete_confirmation_check' => 'Entiendo que la mesa se borrará definitivamente y que no se puede deshacer',
        'create_publisher' => 'Crear editorial',
        'add_game_master' => 'Añadir director de juego',
        'view' => 'Ver',
    ],

    'bulk_actions' => [
        'approve' => 'Aprobar',
        'reject' => 'Rechazar',
        'approved_count' => ':count mesa(s) aprobada(s)',
        'rejected_count' => ':count mesa(s) rechazada(s)',
    ],

    'table_tabs' => [
        'all' => 'Todas',
        'pending_moderation' => 'Pendientes de moderación',
    ],

    'moderation' => [
        'approve' => 'Aprobar',
        'reject' => 'Rechazar',
        'approve_confirmation' => '¿Aprobar esta mesa?',
        'approve_description' => 'La mesa será publicada y visible para todos los usuarios.',
        'reject_confirmation' => '¿Rechazar esta mesa?',
        'reject_description' => 'El creador será notificado del rechazo con el motivo indicado.',
        'rejection_reason' => 'Motivo del rechazo',
        'rejection_reason_placeholder' => 'Explica por qué se rechaza esta mesa...',
        'campaign_approve_confirmation' => '¿Aprobar esta campaña?',
        'campaign_approve_description' => 'La campaña será publicada y visible para todos los usuarios.',
        'campaign_reject_confirmation' => '¿Rechazar esta campaña?',
        'campaign_reject_description' => 'La campaña no se publicará; su creador verá el motivo en sus campañas.',
        'campaign_rejection_reason_placeholder' => 'Explica por qué se rechaza esta campaña...',
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    */
    'cookies' => [
        'guest_registrations' => [
            'purpose' => 'Recuerda en qué mesas te has inscrito como invitado desde este navegador, para avisarte si intentas inscribirte otra vez.',
            'duration' => '90 días',
        ],
    ],

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
    | «Mis mesas» and «Mis campañas»: outcome of the author's changes
    |--------------------------------------------------------------------------
    */
    'frontend' => [
        'table_created' => 'Mesa creada correctamente',
        'table_updated' => 'Mesa actualizada correctamente',
        'table_deleted' => 'Mesa eliminada correctamente',
        'submitted_for_review' => 'Mesa enviada para revisión',
        'table_published' => 'Mesa publicada correctamente',
        'campaign_created' => 'Campaña creada correctamente',
        'campaign_updated' => 'Campaña actualizada correctamente',
        'campaign_deleted' => 'Campaña eliminada correctamente',
        'campaign_submitted_for_review' => 'Campaña enviada para revisión',
        'campaign_published' => 'Campaña publicada correctamente',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Errors & Success Messages
    |--------------------------------------------------------------------------
    */
    'errors' => [
        'not_pending' => 'Esta inscripción ya no está pendiente.',
        'table_locked_published' => 'Esta mesa ya está publicada y ya no se puede modificar.',
        'table_locked_in_review' => 'Esta mesa ya se ha enviado a revisión y ya no se puede modificar.',
        'table_locked' => 'Esta mesa ya se ha enviado a revisión o está publicada y ya no se puede modificar.',
        'campaign_locked_published' => 'Esta campaña ya está publicada y ya no se puede modificar.',
        'campaign_locked_in_review' => 'Esta campaña ya se ha enviado a revisión y ya no se puede modificar.',
        'campaign_locked' => 'Esta campaña ya se ha enviado a revisión o está publicada y ya no se puede modificar.',
        'unauthenticated' => 'Debes iniciar sesión para realizar esta acción',
        'registration_closed' => 'Las inscripciones están cerradas',
        'registration_not_open' => 'Las inscripciones aún no están abiertas',
        'table_started' => 'La partida ya ha comenzado',
        'table_ended' => 'La partida ya ha terminado',
        'table_full' => 'La mesa está completa',
        'spectators_full' => 'No quedan plazas de espectador disponibles',
        'already_registered' => 'Ya estás inscrito en esta mesa',
        'guest_already_registered' => 'Ya existe una inscripción con este email',
        'email_registered_as_guest' => 'Ya hay una inscripción con tu email.',
        'email_has_account' => 'Este email pertenece a una cuenta. Inicia sesión para inscribirte.',
        'members_only' => 'Esta mesa es solo para socios',
        'invitation_only' => 'Esta mesa es solo por invitación: la organización inscribe a los jugadores',
        'campaign_roster_only' => 'Solo para la plantilla de la campaña',
        'guests_not_allowed' => 'Esta mesa no permite inscripciones de invitados',
        'minimum_age' => 'No cumples con la edad mínima requerida',
        'not_found' => 'No se encontró la inscripción',
        'cannot_cancel' => 'No es posible cancelar la inscripción en este momento',
        'cannot_cancel_started' => 'La partida ya ha comenzado: ya no puedes cancelar la inscripción',
        'cannot_mark_no_show' => 'Solo se puede marcar como no presentado a un participante confirmado, una vez empezada la mesa',
        'table_not_found' => 'No se encontró la mesa',
        'campaign_not_found' => 'No se encontró la campaña',
        'invalid_token' => 'El enlace de cancelación no es válido o ha expirado',
        'invalid_confirmation' => 'Este enlace ya no es válido. Si quedan plazas, puedes volver a apuntarte.',
        'confirmation_closed' => 'Las inscripciones ya se han cerrado.',
        'confirmation_filled_up' => 'Se ha llenado mientras confirmabas.',
        'too_many_confirmation_attempts' => 'Demasiados intentos con este enlace. Espera un momento y vuelve a intentarlo.',
        'guest_rate_limited' => 'Has hecho demasiadas inscripciones seguidas. Espera unos minutos y vuelve a intentarlo.',
        'session_below_roster' => 'Esta sesión tiene menos plazas (:max) que la plantilla de la campaña (:count). Sube el máximo de jugadores antes de publicarla.',
    ],

    'success' => [
        'registered' => 'Te has inscrito correctamente',
        'waiting_list' => 'Te has apuntado a la lista de espera (posición :position). Si se libera una plaza antes del inicio de la partida (o del cierre de inscripciones, si es anterior), pasarás a la mesa automáticamente y te avisaremos por correo.',
        'waiting_list_manual' => 'Te has apuntado a la lista de espera (posición :position). Si se libera una plaza antes del inicio de la partida (o del cierre de inscripciones, si es anterior), pasarás a la mesa pendiente de que el director te confirme y te avisaremos por correo.',
        'cancelled' => 'Inscripción cancelada correctamente',
        'guest_email_confirmed' => 'Tu inscripción está confirmada. Te hemos enviado un email con los detalles.',
        'guest_email_already_confirmed' => 'Tu inscripción ya estaba confirmada.',
        'guest_email_confirmed_pending' => 'Has confirmado tu email. Tu inscripción está pendiente de aprobación por la organización: te avisaremos por correo.',
        'guest_email_confirmed_waiting_list' => 'Has confirmado tu email. Estás en la lista de espera (puesto :position): si se libera una plaza, te avisaremos por correo.',
        'registration_linked' => 'Ya tenías una inscripción como invitado; ahora está en tu cuenta.',
        'registration_linked_waiting_list' => 'Ya tenías una inscripción como invitado; ahora está en tu cuenta. Estás en la lista de espera (posición :position).',
        'guest_confirmation_pending' => 'Ya tienes una inscripción pendiente: revisa tu correo.',
        'guest_confirmation_sent' => 'Te hemos enviado un email para confirmar la inscripción. Confírmala antes del :deadline o la plaza se liberará.',
        'guest_confirmation_sent_waiting_list' => 'Te hemos enviado un email para confirmar la inscripción. Confírmala antes del :deadline para conservar tu puesto :position en la lista de espera.',
        'guest_confirmation_sent_unguaranteed' => 'Te hemos enviado un email para confirmar la inscripción. Tu plaza no está garantizada hasta que la confirmes.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Notifications
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        'table_cancelled' => 'Mesa cancelada correctamente',
        'table_approved' => 'Mesa aprobada correctamente',
        'table_rejected' => 'Mesa rechazada correctamente',
        'campaign_approved' => 'Campaña aprobada correctamente',
        'campaign_rejected' => 'Campaña rechazada correctamente',
        'status_already_changed' => 'La mesa ya no está en un estado que permita este cambio',
        'campaign_status_already_changed' => 'La campaña ya no está en un estado que permita este cambio',
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
        'invitation_consent_required' => 'Confirma que esta persona te ha dado permiso para invitarla.',
        'phone_max' => 'El teléfono no puede superar los 20 caracteres',
        'gdpr_consent_required' => 'Debes aceptar la política de privacidad para continuar',
        'title_required' => 'El título es obligatorio',
        'title_min' => 'El título debe tener al menos 5 caracteres',
        'starts_at_future' => 'La fecha de inicio debe ser posterior a ahora',
        'max_players_gte_min' => 'El máximo de jugadores debe ser mayor o igual al mínimo',
        'language_required' => 'El idioma es obligatorio',
        'experience_level_required' => 'El nivel de experiencia es obligatorio',
        'character_creation_required' => 'El tipo de creación de personajes es obligatorio',
        'campaign_required' => 'Elige la campaña de la sesión.',
        'co_director_first_name_required' => 'El nombre del co-director es obligatorio.',
        'co_director_email_required' => 'El email del co-director es obligatorio.',
        'co_directors_consent_required' => 'Confirma que tienes el consentimiento de los co-directores.',
        'campaign_not_owned' => 'Solo puedes vincular la mesa a una campaña que dirijas.',
        'max_players_below_roster' => 'El máximo de jugadores no puede ser menor que la plantilla de la campaña (:count).',
        'join_message_max' => 'El mensaje no puede superar los 500 caracteres.',
        'rejection_reason_max' => 'El motivo no puede superar los 500 caracteres.',
        'delegated_directors_need_accounts' => 'Para que el director gestione las inscripciones, cada director tiene que estar vinculado a una cuenta del sitio. Elige la cuenta de :name.',
        'event_not_open' => 'Solo puedes vincular la mesa a un evento abierto a nuevas mesas',
        'registration_closes_after_start' => 'El cierre de inscripciones no puede ser posterior al inicio de la mesa (:date)',
        'registration_closes_after_end' => 'El cierre de inscripciones no puede ser posterior al final de la partida (:date)',
    ],

    /*
    |--------------------------------------------------------------------------
    | Relation Managers
    |--------------------------------------------------------------------------
    */
    'relation_managers' => [
        'roster' => [
            'title' => 'Plantilla',
            'name' => 'Nombre',
            'email' => 'Email',
            'since' => 'En la plantilla desde',
            'remove' => 'Quitar',
            'remove_description' => 'Perderá sus plazas en las próximas sesiones y recibirá un correo.',
        ],
        'invitations' => [
            'title' => 'Invitaciones',
            'email' => 'Email',
            'invited_by' => 'Invitada por',
            'send_count' => 'Envíos',
            'expires_at' => 'Caduca',
            'invite' => 'Invitar jugador',
            'consent' => 'Confirmo que esta persona me ha dado permiso para usar su email para invitarla a la campaña.',
            'resend' => 'Reenviar',
            'cancel' => 'Cancelar',
            'cancel_description' => 'La invitación dejará de ser válida.',
            'more_than_seats' => 'Hay más invitaciones pendientes que plazas libres; entrará quien acepte primero.',
        ],
        'join_requests' => [
            'title' => 'Solicitudes',
            'player' => 'Jugador',
            'message' => 'Mensaje',
            'status' => 'Estado',
            'requested_at' => 'Solicitada el',
            'accept' => 'Aceptar',
            'reject' => 'Rechazar',
            'reason' => 'Motivo (opcional)',
        ],
        'participants' => [
            'title' => 'Participantes',
            'add' => 'Añadir participante',
            'confirm' => 'Confirmar',
            'reject' => 'Rechazar',
            'reject_reason' => 'Motivo (opcional)',
            'promote' => 'Promover',
            'cancel_registration' => 'Cancelar inscripción',
            'cancel_registration_description' => 'Se libera su plaza (y, si corresponde, pasa a ocuparla el primero de la lista de espera). Se le avisará por email de que la organización ha cancelado su inscripción.',
            'no_show' => 'No presentado',
            'no_show_description' => 'Queda marcado como no presentado. No se envía ningún email ni se promueve a nadie de la lista de espera.',
            'move_to_waiting' => 'Mover a lista de espera',
            'confirm_email' => 'Marcar email como confirmado',
            'confirm_email_description' => 'Se dará por confirmado el email del invitado, como si hubiera pulsado el enlace, y recibirá el email de inscripción.',
            'flags' => [
                'label' => 'Avisos',
                'email_unconfirmed' => 'Email sin confirmar',
                'expires_at' => 'Caduca el :date',
                'possible_duplicate' => 'Posible duplicado',
                'possible_duplicate_tooltip' => 'Otra inscripción de esta mesa se hizo desde la misma conexión y navegador en :hours h o menos',
            ],
            'delete_not_allowed' => 'No se ha borrado: la inscripción ya no está cancelada, rechazada ni caducada',
            'bulk_delete' => [
                'deleted' => '{1} Se ha borrado 1 inscripción|[2,*] Se han borrado :count inscripciones',
                'none_deleted' => 'No se ha borrado ninguna inscripción',
                'skipped' => '{1} 1 inscripción no se ha borrado: solo se pueden borrar las canceladas, rechazadas o caducadas; antes hay que cancelarla con «Cancelar inscripción».|[2,*] :count inscripciones no se han borrado: solo se pueden borrar las canceladas, rechazadas o caducadas; antes hay que cancelarlas con «Cancelar inscripción».',
                'failed' => '{1} 1 inscripción no se ha podido borrar por un error.|[2,*] :count inscripciones no se han podido borrar por un error.',
            ],
        ],
        'sessions' => [
            'title' => 'Sesiones',
        ],
        'status_log' => [
            'title' => 'Historial de inscripciones',
            'when' => 'Fecha',
            'participant' => 'Inscrito',
            'change' => 'Cambio',
            'actor' => 'Hecho por',
            'reason' => 'Motivo',
            'system' => 'Sistema',
            'by_participant' => 'El propio inscrito',
            'deleted_account' => 'Cuenta eliminada',
            'registered' => 'Inscripción',
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

    /*
    |--------------------------------------------------------------------------
    | Event Game Table Configuration
    |--------------------------------------------------------------------------
    */
    'event_config' => [
        'title' => 'Configuración de mesas',
        'general' => 'General',
        'tables_enabled' => 'Habilitar mesas para este evento',
        'tables_enabled_help' => 'Permite que se creen mesas de juego dentro de este evento',

        // Scheduling
        'scheduling' => 'Programación',
        'scheduling_mode' => 'Modo de programación',
        'time_slots' => 'Turnos',
        'time_slot_label' => 'Nombre del turno',
        'start_time' => 'Hora de inicio',
        'end_time' => 'Hora de fin',
        'max_tables' => 'Máximo de mesas',

        // Location
        'location' => 'Ubicación',
        'location_mode' => 'Modo de ubicación',
        'fixed_location' => 'Ubicación fija',
        'fixed_location_help' => 'Todas las mesas se ubicarán aquí',

        // Eligibility override
        'eligibility_override' => 'Sobrescribir elegibilidad',
        'eligibility_override_description' => 'Permite sobrescribir la configuración global de quién puede crear mesas',
        'eligibility_override_enabled' => 'Usar configuración de evento',
        'eligibility_override_enabled_help' => 'Ignora la configuración global para este evento',
        'eligibility_access_level' => 'Nivel de acceso',
        'eligibility_allowed_roles' => 'Roles permitidos',
        'eligibility_required_permission' => 'Permiso requerido',
        'eligibility_required_permission_help' => 'Nombre del permiso necesario para crear mesas en este evento',

        // Table creation
        'table_creation' => 'Creación de partidas',

        // Early access
        'early_access' => 'Acceso anticipado',
        'early_access_description' => 'Permite que ciertos usuarios creen mesas antes de la fecha de apertura general',
        'early_access_enabled' => 'Habilitar acceso anticipado',
        'early_access_enabled_help' => 'Los usuarios con roles/permisos especiales podrán crear mesas antes que los demás',
        'creation_opens_at' => 'Fecha de apertura general',
        'creation_opens_at_help' => 'Fecha a partir de la cual todos los usuarios elegibles pueden crear mesas',
        'early_access_type' => 'Tipo de acceso anticipado',
        'early_access_roles' => 'Roles con acceso anticipado',
        'early_access_roles_help' => 'Usuarios con estos roles podrán crear mesas antes',
        'early_access_permission' => 'Permiso de acceso anticipado',
        'early_access_permission_help' => 'Usuarios con este permiso podrán crear mesas antes',
        'early_access_days_before' => 'Días de ventaja',
        'early_access_days_before_help' => 'Número de días antes de la apertura general que pueden crear mesas',

        // Actions
        'save' => 'Guardar configuración',
        'saved' => 'Configuración guardada',
    ],

    /*
    |--------------------------------------------------------------------------
    | Guest confirmation page
    |--------------------------------------------------------------------------
    */
    'confirm_registration' => [
        // The state shown until the guest follows the emailed link
        'awaiting_email' => 'Pendiente de confirmar',
    ],

    'roster' => [
        'errors' => [
            'unavailable' => 'Esta campaña no está publicada.',
            'finished' => 'La campaña ha terminado: su plantilla ya no cambia.',
            'not_accepting' => 'Esta campaña no acepta jugadores nuevos.',
            'roster_full' => 'La plantilla está completa.',
            'members_only' => 'Esta campaña solo admite solicitudes de socios.',
            'already_member' => 'Ya formas parte de la plantilla.',
            'account_already_member' => 'Esa cuenta ya forma parte de la plantilla.',
            'request_pending' => 'Ya tienes una solicitud pendiente.',
            'invitation_pending' => 'Ya hay una invitación pendiente para ese email.',
            'invitation_limit' => 'Ya no se pueden enviar más invitaciones a este email en esta campaña por ahora.',
            'invitation_too_soon' => 'Han de pasar 24 horas desde el último envío para volver a enviarla.',
            'invitation_not_found' => 'Esta invitación ya no está disponible: puede haber caducado, haberse rechazado o haberse aceptado.',
            'invitation_other_account' => 'Esta invitación es para otro email.',
            'email_not_verified' => 'Verifica tu email para ver y aceptar tus invitaciones.',
            'is_director' => 'Quien dirige la campaña no puede entrar en la plantilla como jugador.',
            'account_not_found' => 'No se ha podido añadir a esa cuenta. Comprueba el email.',
            'not_pending' => 'Esta solicitud ya no está pendiente.',
            'not_member' => 'Esa persona no forma parte de la plantilla.',
            'max_below_roster' => 'El máximo de jugadores no puede ser menor que la plantilla actual (:count).',
            'session_not_in_campaign' => 'Esa sesión no es de esta campaña.',
            'session_started' => 'La sesión ya ha empezado.',
            'nothing_to_change' => 'No hay nada que cambiar en esa sesión.',
            'not_member_self' => 'No formas parte de la plantilla de esta campaña.',
            'too_many_requests' => 'Has enviado demasiadas solicitudes seguidas. Espera unos minutos y vuelve a intentarlo.',
            'too_many_additions' => 'Has enviado demasiadas invitaciones seguidas. Espera un minuto y vuelve a intentarlo.',
        ],
        'success' => [
            'requested' => 'Solicitud enviada. La dirección te avisará por correo.',
            'withdrawn' => 'Solicitud retirada.',
            'accepted' => 'Solicitud aceptada.',
            'rejected' => 'Solicitud rechazada.',
            'added' => 'Jugador añadido a la plantilla.',
            'removed' => 'Jugador quitado de la plantilla.',
            'left' => 'Has salido de la campaña.',
            'settings_saved' => 'Ajustes guardados.',
            'invited' => 'Invitación enviada a :email.',
            'invitation_resent' => 'Invitación reenviada.',
            'invitation_cancelled' => 'Invitación cancelada.',
            'invitation_accepted' => 'Ya formas parte de la campaña.',
            'invitation_rejected' => 'Invitación rechazada. Hemos borrado tu email de esta invitación.',
            'absence_marked' => 'Ausencia marcada.',
            'absence_cleared' => 'Ausencia quitada.',
            'rejoined' => 'Vuelves a estar inscrito en esta sesión.',
            'session_absence' => 'Te has dado de baja de esta sesión. Sigues en la campaña.',
        ],
    ],

    // The «Gestionar inscritos» page of a table's directors (errors.not_pending is the general one)
    'registrations' => [
        'errors' => [
            'awaiting_email' => 'Esta persona aún no ha confirmado su email; podrás aprobarla cuando lo haga.',
            'too_many_actions' => 'Has hecho demasiados cambios seguidos. Espera un minuto y vuelve a intentarlo.',
            'table_closed' => 'Esta partida ya ha terminado o se ha cancelado.',
        ],
        'success' => [
            'approved' => 'Has aprobado la inscripción de :name.',
            'rejected' => 'Has rechazado la solicitud de :name.',
        ],
    ],

    // Server-rendered page metadata (link previews and search engines)
    'seo' => [
        'tables_title' => 'Mesas de rol',
        'tables_description' => 'Encuentra y únete a partidas de rol',
        'calendar_title' => 'Calendario de mesas',
        'campaigns_title' => 'Campañas',
        'campaigns_description' => 'Explora las campañas de rol en curso',
        'create_table_title' => 'Crear mesa',
        'edit_table_title' => 'Editar mesa',
        'my_tables_title' => 'Mis mesas de juego',
        'cancel_registration_title' => 'Cancelar inscripción',
        'confirm_registration_title' => 'Confirmar inscripción',
        'campaign_invitation_title' => 'Invitación a una campaña',
        'create_campaign_title' => 'Crear campaña',
        'edit_campaign_title' => 'Editar campaña',
        'my_campaigns_title' => 'Mis campañas',
        'roster_title' => 'Plantilla · :title',
        'registrations_title' => 'Inscritos de :title',
    ],

    /*
    |--------------------------------------------------------------------------
    | Web form fields in validation messages (lowercase: they go inside a sentence)
    |--------------------------------------------------------------------------
    */
    'form_attributes' => [
        'game_system_id' => 'sistema de juego',
        'title' => 'título',
        'starts_at' => 'fecha y hora de inicio',
        'duration_minutes' => 'duración',
        'table_type' => 'tipo de partida',
        'table_format' => 'formato',
        'min_players' => 'mínimo de jugadores',
        'max_players' => 'máximo de jugadores',
        'max_spectators' => 'máximo de espectadores',
        'event_id' => 'evento',
        'campaign_id' => 'campaña',
        'synopsis' => 'sinopsis',
        'location' => 'ubicación',
        'online_url' => 'enlace de la sesión online',
        'minimum_age' => 'edad mínima',
        'language' => 'idioma',
        'genres' => 'géneros',
        'genres_item' => 'género',
        'tone' => 'tono de la partida',
        'experience_level' => 'nivel de experiencia',
        'character_creation' => 'creación de personajes',
        'safety_tools' => 'herramientas de seguridad',
        'safety_tools_item' => 'herramienta de seguridad',
        'content_warning_ids' => 'avisos de contenido',
        'content_warning_ids_item' => 'aviso de contenido',
        'custom_warnings' => 'avisos personalizados',
        'custom_warnings_item' => 'aviso personalizado',
        'game_masters' => 'directores de juego',
        'game_masters_id' => 'identificador del co-director',
        'game_masters_user_id' => 'cuenta del director',
        'game_masters_first_name' => 'nombre del co-director',
        'game_masters_last_name' => 'apellidos del co-director',
        'game_masters_email' => 'email del co-director',
        'game_masters_phone' => 'teléfono del director',
        'game_masters_custom_title' => 'título personalizado del director',
        'game_masters_is_name_public' => 'mostrar nombre públicamente',
        'game_masters_role' => 'rol del director',
        'gm_gdpr_consent' => 'consentimiento de los co-directores',
        'notes' => 'notas adicionales',
        'frequency' => 'frecuencia de las sesiones',
        'schedule_notes' => 'notas de horario',
    ],
];
