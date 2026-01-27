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
        'cancel_intro' => 'Si necesitas cancelar tu inscripción, usa el siguiente enlace:',
        'cancel_button' => 'Cancelar inscripción',
        'gdpr_notice' => 'Tus datos serán eliminados automáticamente después de la fecha de la partida, de acuerdo con nuestra política de privacidad.',
        'role_player' => 'jugador',
        'role_spectator' => 'espectador',
    ],

    'registration' => [
        'subject' => 'Nueva inscripción en mesa: :tableTitle',
        'greeting' => 'Hola,',
        'intro' => ':name se ha inscrito como :role en la mesa :tableTitle.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
    ],

    'cancellation' => [
        'subject' => 'Baja en mesa: :tableTitle',
        'greeting' => 'Hola,',
        'intro' => ':name ha cancelado su inscripción en la mesa :tableTitle.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
    ],

    'waiting_list_promotion' => [
        'subject' => 'Plaza disponible en mesa: :tableTitle',
        'greeting' => 'Hola :name,',
        'intro' => 'Se ha liberado una plaza en la mesa :tableTitle y has sido promovido/a desde la lista de espera.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
        'outro' => 'Tu plaza ha sido confirmada automáticamente. ¡Nos vemos en la partida!',
    ],

    'waiting_list_promotion_gm' => [
        'subject' => 'Promoción de lista de espera en mesa: :tableTitle',
        'greeting' => 'Hola,',
        'intro' => ':name ha sido promovido/a desde la lista de espera en la mesa :tableTitle.',
        'table_title' => 'Mesa',
        'table_date' => 'Fecha',
        'table_location' => 'Ubicación',
    ],
];
