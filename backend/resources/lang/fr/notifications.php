<?php

declare(strict_types=1);

return [
    // Nouveau rendez-vous (destinataire : owner/employé du salon)
    'appointment_created_title' => 'Nouvelle réservation',
    'appointment_created_body'  => ':client_name a réservé :service_name à :time.',

    // Confirmation (destinataire : client)
    'appointment_confirmed_title' => 'Réservation confirmée',
    'appointment_confirmed_body'  => 'Votre rendez-vous pour :service_name à :time a été confirmé.',

    // Refus (destinataire : client)
    'appointment_rejected_title' => 'Réservation refusée',
    'appointment_rejected_body'  => 'Votre demande de rendez-vous pour :service_name à :time a été refusée.',

    // Rappel pro 1h avant (destinataire : owner/employé assigné)
    'appointment_reminder_owner_title' => 'Rappel — dans 1h',
    'appointment_reminder_owner_body'  => ':client_name — :service_name à :time.',

    // Rappel client la veille à 20h
    'appointment_reminder_evening_title' => 'Rappel pour demain',
    'appointment_reminder_evening_body'  => 'Vous avez un rendez-vous chez :company_name à :time demain.',

    // Rappel client 2h avant
    'appointment_reminder_2h_title' => 'Rappel — dans 2h',
    'appointment_reminder_2h_body'  => 'Votre rendez-vous commence dans 2h à :time.',
];
