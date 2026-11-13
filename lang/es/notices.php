<?php

declare(strict_types=1);

// Notices of the notification centre (the bell): one line each, written when they are delivered
return [
    // Shown before the name of a muted thing in the profile
    'subjects' => [
        'table' => 'Mesa',
        'campaign' => 'Campaña',
    ],

    'sign_ups' => [
        'registration_confirmed' => 'Inscripción confirmada en «:table»',
        'registration_pending' => 'Inscripción recibida en «:table»',
        'registration_waiting_list' => 'Estás en la lista de espera de «:table»',
        'confirmed' => 'Tu plaza en «:table» está confirmada',
        'rejected' => 'Tu inscripción en «:table» no ha sido aceptada',
        'cancelled' => 'Te has dado de baja de «:table»',
        'promoted' => 'Tienes plaza en «:table»',
        'promoted_pending' => 'Tienes plaza en «:table», pendiente de confirmar',
        'table_cancelled' => 'Se ha cancelado la mesa «:table»',
        'organization_cancelled' => 'La organización ha cancelado tu inscripción en «:table»',
        'absence' => 'Ausencia anotada en «:table»',
        'join_request_accepted' => 'Ya formas parte de «:campaign»',
        'join_request_rejected' => 'Tu solicitud para «:campaign» no ha sido aceptada',
        'roster_added' => 'Te han añadido a «:campaign»',
        'roster_removed' => 'Ya no formas parte de «:campaign»',
    ],

    'reminder' => 'Recordatorio: «:table», :date',

    'campaign_news' => [
        'invitation' => ':inviter te invita a la campaña «:campaign»',
        'invitation_expires' => 'Caduca el :date',
        'session' => 'Nueva sesión de «:campaign»: :date',
        'session_waiting' => 'Nueva sesión de «:campaign»: :date, en lista de espera',
    ],

    'direction' => [
        'registered' => ':name se ha apuntado a «:table»',
        'registered_pending' => ':name pide plaza en «:table»',
        'cancelled' => ':name se ha dado de baja de «:table»',
        'promoted' => ':name pasa de la lista de espera a «:table»',
        'promoted_pending' => ':name pasa de la lista de espera a «:table», pendiente de confirmar',
        'join_request' => ':name pide unirse a «:campaign»',
        'player_left' => ':name ha salido de «:campaign»',
        'invitation_accepted' => ':name ha aceptado tu invitación a «:campaign»',
        'grouped' => '{0} :name: novedades en «:thing»|[1,*] :name y :count más: novedades en «:thing»',
        'someone' => 'Alguien',
    ],

    'proposals' => [
        'table_approved' => 'Tu mesa «:table» ha sido aprobada',
        'table_rejected' => 'Tu mesa «:table» no ha sido aprobada',
        'campaign_approved' => 'Tu campaña «:campaign» ha sido aprobada',
        'campaign_rejected' => 'Tu campaña «:campaign» no ha sido aprobada',
    ],

    'moderation' => [
        'table' => 'Mesa pendiente de revisar: «:table»',
        'campaign' => 'Campaña pendiente de revisar: «:campaign»',
        'proposed_by' => 'Propuesta de :name',
        'grouped' => '{1} :count propuesta pendiente de revisar|[2,*] :count propuestas pendientes de revisar',
    ],
];
