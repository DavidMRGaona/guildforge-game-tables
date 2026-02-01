<?php

declare(strict_types=1);

return [
    'guest_confirmation' => [
        'subject' => 'Confirmación de inscripción: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Tu inscripción como :role ha sido registrada correctamente.',
        'details' => 'Detalles de la partida:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'cancel_intro' => 'Si necesitas cancelar tu inscripción, usa el siguiente enlace:',
        'cancel_button' => 'Cancelar inscripción',
        'gdpr_notice' => 'Tus datos serán eliminados automáticamente después de la fecha de la partida, de acuerdo con nuestra política de privacidad.',
        'role_player' => 'jugador',
        'role_spectator' => 'espectador',
    ],

    'user_confirmation' => [
        'subject' => 'Confirmación de inscripción: :tableTitle',
        'greeting' => 'Hola :name,',
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
        'greeting' => 'Hola,',
        'intro' => ':name se ha inscrito como :role en la mesa :tableTitle.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
    ],

    'cancellation' => [
        'subject' => 'Baja en mesa: :tableTitle',
        'greeting' => 'Hola,',
        'intro' => ':name ha cancelado su inscripción en la mesa :tableTitle.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
    ],

    'cancellation_confirmation' => [
        'subject' => 'Cancelación confirmada: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Tu inscripción ha sido cancelada correctamente.',
        'details' => 'Detalles de la mesa:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => 'Esperamos verte en futuras partidas.',
        'guest_gdpr_notice' => 'Tus datos serán eliminados automáticamente de acuerdo con nuestra política de privacidad.',
    ],

    'waiting_list_promotion' => [
        'subject' => 'Plaza disponible en mesa: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Se ha liberado una plaza en la mesa :tableTitle y has sido promovido/a desde la lista de espera.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => 'Tu plaza ha sido confirmada automáticamente. ¡Nos vemos en la partida!',
    ],

    'waiting_list_promotion_gm' => [
        'subject' => 'Promoción de lista de espera en mesa: :tableTitle',
        'greeting' => 'Hola,',
        'intro' => ':name ha sido promovido/a desde la lista de espera en la mesa :tableTitle.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
    ],

    'table_cancelled' => [
        'subject' => 'Mesa cancelada: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Lamentamos informarte que la mesa ":tableTitle" ha sido cancelada.',
        'details' => 'Detalles de la mesa cancelada:',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha prevista',
        'table_location' => 'Ubicación',
        'outro' => 'Disculpa las molestias. Esperamos verte en futuras partidas.',
    ],

    'confirmation' => [
        'subject' => 'Inscripción confirmada: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Tu inscripción en la mesa :tableTitle ha sido confirmada.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'view_table' => 'Ver mesa',
        'outro' => '¡Nos vemos en la partida!',
    ],

    'rejection' => [
        'subject' => 'Inscripción rechazada: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Lamentamos informarte que tu inscripción en la mesa :tableTitle ha sido rechazada.',
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
        'greeting' => 'Hola,',
        'intro' => 'Se ha enviado una nueva mesa para revisión.',
        'table_title' => 'Mesa',
        'created_by' => 'Creada por',
        'review_table' => 'Revisar mesa',
        'outro' => 'Por favor, revisa la mesa y apruébala o recházala.',
    ],

    'moderation_approved' => [
        'subject' => 'Tu mesa ha sido aprobada: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Tu mesa :tableTitle ha sido aprobada y ya está publicada.',
        'table_title' => 'Mesa',
        'notes_label' => 'Notas del moderador',
        'view_table' => 'Ver mesa',
        'outro' => '¡Gracias por contribuir a la comunidad!',
    ],

    'moderation_rejected' => [
        'subject' => 'Tu mesa no ha sido aprobada: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Lamentamos informarte que tu mesa :tableTitle no ha sido aprobada.',
        'table_title' => 'Mesa',
        'reason_label' => 'Motivo',
        'edit_table' => 'Editar mesa',
        'outro' => 'Puedes modificar tu mesa y volver a enviarla para revisión.',
    ],
];
