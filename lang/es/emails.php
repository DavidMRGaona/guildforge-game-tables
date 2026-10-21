<?php

declare(strict_types=1);

return [
    'guest_confirmation' => [
        'greeting' => '¡Hola, :name!',
        'intro' => 'Tu inscripción como :role ha sido registrada correctamente.',
        'details' => 'Detalles de la partida:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'cancel_intro' => 'Si necesitas cancelar tu inscripción antes del inicio de la partida, usa el siguiente enlace:',
        'cancel_button' => 'Cancelar inscripción',
        'gdpr_notice' => 'Tus datos se tratan conforme a nuestra política de privacidad.',
        'role_player' => 'jugador',
        'role_spectator' => 'espectador',
    ],

    'guest_email_confirmation' => [
        'subject' => 'Confirma tu inscripción: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Hemos recibido tu inscripción en la mesa «:tableTitle». Confírmala con el botón de abajo.',
        'deadline' => 'Si no la confirmas antes del :deadline, la plaza se liberará.',
        'unguaranteed' => 'Tu plaza no está garantizada hasta que la confirmes.',
        'button' => 'Confirmar inscripción',
        'ignore' => 'Si no te has inscrito tú, ignora este email: la inscripción no seguirá adelante.',
    ],

    'guest_registration_expired' => [
        'subject' => 'Inscripción caducada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'No confirmaste a tiempo tu inscripción en la mesa «:tableTitle» y la plaza se ha liberado.',
        'retry' => 'Si siguen quedando plazas, puedes volver a apuntarte.',
        'button' => 'Ver mesa',
    ],

    'registration_status' => [
        'subject_confirmed' => 'Inscripción confirmada: :tableTitle',
        'subject_pending' => 'Inscripción recibida: :tableTitle',
        'subject_waiting_list' => 'En lista de espera: :tableTitle',
        'confirmed' => 'Tu plaza está confirmada.',
        'pending' => 'Tu inscripción está pendiente de confirmación por parte del organizador. Te avisaremos por correo cuando se revise.',
        'waiting_list' => 'La mesa está completa. Estás en la posición :position de la lista de espera y te avisaremos si se libera una plaza.',
    ],

    'user_confirmation' => [
        'greeting' => '¡Hola, :name!',
        'intro' => 'Tu inscripción como :role ha sido registrada correctamente.',
        'details' => 'Detalles de la partida:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => '¡Nos vemos en la partida!',
    ],

    'registration' => [
        'subject' => 'Nueva inscripción en mesa: :tableTitle',
        'greeting' => '¡Hola!',
        'intro' => ':name se ha inscrito como :role en la mesa «:tableTitle».',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
    ],

    'cancellation' => [
        'subject' => 'Baja en mesa: :tableTitle',
        'greeting' => '¡Hola!',
        'intro' => ':name ha cancelado su inscripción en la mesa «:tableTitle».',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
    ],

    'cancellation_confirmation' => [
        'subject' => 'Cancelación confirmada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Tu inscripción ha sido cancelada correctamente.',
        'details' => 'Detalles de la mesa:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => 'Esperamos verte en futuras partidas.',
        'guest_gdpr_notice' => 'Tus datos se tratan conforme a nuestra política de privacidad.',
    ],

    'organization_cancellation' => [
        'subject' => 'La organización ha cancelado tu inscripción en :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'La organización ha cancelado tu inscripción en la mesa «:tableTitle».',
        'details' => 'Detalles de la mesa:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => 'Si crees que se trata de un error, ponte en contacto con la organización.',
        'guest_gdpr_notice' => 'Tus datos se tratan conforme a nuestra política de privacidad.',
    ],

    'waiting_list_promotion' => [
        'subject' => 'Plaza disponible en mesa: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Se ha liberado una plaza en la mesa «:tableTitle» y has sido promovido/a desde la lista de espera.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => 'Tu plaza ha sido confirmada automáticamente. ¡Nos vemos en la partida!',
        'outro_pending' => 'Tu inscripción queda pendiente de que el director de juego la confirme. Te avisaremos por correo cuando se revise.',
    ],

    'waiting_list_promotion_gm' => [
        'subject' => 'Promoción de lista de espera en mesa: :tableTitle',
        'greeting' => '¡Hola!',
        'intro' => ':name ha sido promovido/a desde la lista de espera en la mesa «:tableTitle».',
        'pending' => 'La mesa tiene confirmación manual: la inscripción queda pendiente hasta que la confirmes o la rechaces en el panel.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
    ],

    'table_cancelled' => [
        'subject' => 'Mesa cancelada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Lamentamos informarte de que la mesa «:tableTitle» ha sido cancelada.',
        'details' => 'Detalles de la mesa cancelada:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha prevista',
        'table_location' => 'Ubicación',
        'outro' => 'Disculpa las molestias. Esperamos verte en futuras partidas.',
    ],

    'confirmation' => [
        'subject' => 'Inscripción confirmada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Tu inscripción en la mesa «:tableTitle» ha sido confirmada.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => '¡Nos vemos en la partida!',
    ],

    'rejection' => [
        'subject' => 'Inscripción rechazada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Lamentamos informarte de que tu inscripción en la mesa «:tableTitle» ha sido rechazada.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'outro' => 'Esperamos verte en futuras partidas.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Moderation Emails
    |--------------------------------------------------------------------------
    */
    'moderation_submitted' => [
        'subject' => 'Nueva mesa pendiente de moderación: :tableTitle',
        'greeting' => '¡Hola!',
        'intro' => 'Se ha enviado una nueva mesa para revisión.',
        'table_title' => 'Mesa',
        'created_by' => 'Creada por',
        'review_table' => 'Revisar mesa',
        'outro' => 'Por favor, revisa la mesa y apruébala o recházala.',
    ],

    'campaign_moderation_submitted' => [
        'subject' => 'Nueva campaña pendiente de moderación: :campaignTitle',
        'greeting' => '¡Hola!',
        'intro' => 'Se ha enviado una nueva campaña para revisión.',
        'campaign_title' => 'Campaña',
        'created_by' => 'Creada por',
        'review_campaign' => 'Revisar campaña',
        'outro' => 'Por favor, revisa la campaña y apruébala o recházala.',
    ],

    'moderation_approved' => [
        'subject' => 'Tu mesa ha sido aprobada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Tu mesa «:tableTitle» ha sido aprobada y ya está publicada.',
        'table_title' => 'Mesa',
        'notes_label' => 'Notas del moderador',
        'view_table' => 'Ver mesa',
        'outro' => '¡Gracias por contribuir a la comunidad!',
    ],

    'moderation_rejected' => [
        'subject' => 'Tu mesa no ha sido aprobada: :tableTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Lamentamos informarte de que tu mesa «:tableTitle» no ha sido aprobada.',
        'table_title' => 'Mesa',
        'reason_label' => 'Motivo',
        'edit_table' => 'Editar mesa',
        'outro' => 'Puedes modificar tu mesa y volver a enviarla para revisión.',
    ],

    'campaign_moderation_approved' => [
        'subject' => 'Tu campaña ha sido aprobada: :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Tu campaña «:campaignTitle» ha sido aprobada y ya está publicada.',
        'notes_label' => 'Notas del moderador',
        'view_campaign' => 'Ver campaña',
        'outro' => '¡Gracias por contribuir a la comunidad!',
    ],

    'campaign_moderation_rejected' => [
        'subject' => 'Tu campaña no ha sido aprobada: :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Lamentamos informarte de que tu campaña «:campaignTitle» no ha sido aprobada.',
        'reason_label' => 'Motivo',
        'edit_campaign' => 'Editar campaña',
        'outro' => 'Puedes modificar tu campaña y volver a enviarla para revisión.',
    ],

    'table_reminder' => [
        'subject' => 'Recordatorio: :tableTitle, :date',
        'greeting' => '¡Hola, :name!',
        'greeting_anonymous' => '¡Hola!',
        'intro' => 'Te recordamos tu inscripción en la mesa «:tableTitle», que empieza el :date.',
        'location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'cancel' => 'Si al final no puedes venir, cancela tu inscripción desde la página de la mesa para dejar la plaza libre.',
        'cancel_guest' => 'Si al final no puedes venir, cancela tu inscripción para dejar la plaza libre: :url',
    ],

    'campaign_sessions' => [
        'intro' => 'Quedas inscrito en estas sesiones:',
        'waiting_suffix' => ' (en lista de espera, con prioridad por ser de la plantilla)',
        'no_sessions' => 'Todavía no hay sesiones programadas: te avisaremos cuando se publique la próxima.',
        'absence_hint' => 'Si no puedes ir a una sesión, date de baja desde su ficha: seguirás en la campaña.',
    ],

    'campaign_session_scheduled' => [
        'subject' => 'Nueva sesión de :campaignTitle: :date',
        'greeting' => '¡Hola, :name!',
        'intro' => 'Se ha programado una nueva sesión de la campaña «:campaignTitle» y ya tienes tu plaza.',
        'intro_waiting' => 'Se ha programado una nueva sesión de la campaña «:campaignTitle». Está completa: estás en la lista de espera, con prioridad por ser de la plantilla.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'absence_hint' => 'Si no puedes ir a una sesión, date de baja desde su ficha: seguirás en la campaña.',
        'view_session' => 'Ver sesión',
    ],

    'campaign_join_request_received' => [
        'subject' => 'Solicitud para unirse a :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => ':player quiere unirse a la campaña «:campaignTitle».',
        'message' => 'Mensaje: «:message»',
        'review_requests' => 'Revisar solicitudes',
    ],

    'campaign_join_request_accepted' => [
        'subject' => 'Ya formas parte de :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'La dirección ha aceptado tu solicitud para unirte a la campaña «:campaignTitle».',
        'view_campaign' => 'Ver campaña',
    ],

    'campaign_join_request_rejected' => [
        'subject' => 'Solicitud para :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'La dirección no ha aceptado tu solicitud para unirte a la campaña «:campaignTitle».',
        'reason' => 'Motivo: :reason',
        'view_campaign' => 'Ver campaña',
    ],

    'campaign_roster_added' => [
        'subject' => 'Te han añadido a :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'La dirección te ha añadido a la plantilla de la campaña «:campaignTitle».',
        'view_campaign' => 'Ver campaña',
    ],

    'campaign_roster_removed' => [
        'subject' => 'Ya no formas parte de :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => 'La dirección te ha quitado de la plantilla de la campaña «:campaignTitle». Tus plazas en las próximas sesiones se han liberado.',
        'view_campaign' => 'Ver campaña',
    ],

    'campaign_player_left' => [
        'subject' => ':player ha salido de :campaignTitle',
        'greeting' => '¡Hola, :name!',
        'intro' => ':player ha salido de la plantilla de la campaña «:campaignTitle». Sus plazas en las próximas sesiones se han liberado.',
        'view_roster' => 'Ver plantilla',
    ],

    'campaign_invitation' => [
        'subject' => ':inviter te invita a la campaña «:campaignTitle»',
        'greeting' => '¡Hola!',
        'intro' => ':inviter te invita a unirte a la campaña «:campaignTitle».',
        'view_campaign' => 'Puedes ver la ficha de la campaña [aquí](:url).',
        'view_invitation' => 'Ver invitación',
        'reject' => 'Si no quieres unirte, abre la invitación y pulsa Rechazar: borraremos tu email al momento.',
        'expires' => 'Caduca el :date.',
        'privacy_title' => '**Información sobre el tratamiento de tu email**',
        'privacy_controller' => 'Responsable: :name.',
        'privacy_contact' => 'Contacto: :email.',
        'privacy_dpo' => 'Delegado de protección de datos: :dpo.',
        'privacy_policy' => 'El resto de tus derechos, en la [política de privacidad](:url).',
        'privacy_default' => "Finalidad: invitarte a esta campaña.\n\nOrigen: te lo facilitó :inviter.\n\nConservación: hasta que aceptes, rechaces o caduque la invitación, como máximo :days días. Además guardamos durante 30 días una huella irreversible del email para limitar los envíos.\n\nDerechos: rechazar la invitación borra tu email al instante.",
        'notice_label' => 'Correo de invitación a una campaña',
        'notice_help' => 'Bloque de protección de datos del correo que recibe una persona invitada a una campaña.',
        'preview_campaign' => 'Campaña de ejemplo',
    ],

    'campaign_invitation_accepted' => [
        'subject' => ':player ha aceptado tu invitación a «:campaignTitle»',
        'greeting' => '¡Hola, :name!',
        'intro' => ':player ha aceptado tu invitación y ya forma parte de la plantilla de la campaña «:campaignTitle».',
        'view_roster' => 'Ver plantilla',
    ],
];
