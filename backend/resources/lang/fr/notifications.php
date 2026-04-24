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

    // Annulation par le client (destinataire : owner/employé)
    'appointment_cancelled_by_client_title' => 'Réservation annulée',
    'appointment_cancelled_by_client_body'  => ':client_name a annulé son rendez-vous :service_name à :time.',

    // Annulation par le salon (destinataire : client)
    'appointment_cancelled_by_owner_title' => 'Rendez-vous annulé',
    'appointment_cancelled_by_owner_body'  => ':company_name a annulé votre rendez-vous :service_name à :time.',

    // C8 — Nouvel avis (destinataire : owner)
    'review_new_positive_title' => 'Nouvel avis — :rating étoiles ⭐',
    'review_new_neutral_title'  => 'Commentaire de :client_name',
    'review_new_body'           => ':comment',

    // C9 — Walk-in créé par employé (destinataire : owner)
    'walk_in_created_title' => 'Walk-in dans votre salon',
    'walk_in_created_body'  => ':employee_name a ajouté :client_name à :time.',

    // C10 — RDV déplacé par le salon (destinataire : client)
    'appointment_rescheduled_by_owner_title' => 'Modification de votre rendez-vous',
    'appointment_rescheduled_by_owner_body'  => 'Déplacé de :old_time à :new_time. Confirme ou annule.',

    // C11 — RDV déplacé par le client (destinataire : owner/employé)
    'appointment_rescheduled_by_client_title' => 'Modification — :client_name',
    'appointment_rescheduled_by_client_body'  => 'Déplacé au :new_date à :new_time. Confirmé automatiquement.',

    // C12 — Demande d'avis J+1 (destinataire : client)
    'review_request_title' => 'Comment s\'est passé votre visite chez :salon_name ?',
    'review_request_body'  => 'Un avis en 10 sec aide les autres à bien choisir.',

    // C13 — Réponse support (destinataire : demandeur)
    'support_reply_title' => 'Réponse de l\'équipe Termini im',
    'support_reply_body'  => ':message',

    // C14 — Capacité atteinte (destinataire : owner)
    'capacity_full_title' => 'Complet pour le :date',
    'capacity_full_body'  => 'Tous les créneaux sont pris. Faut-il augmenter la capacité ?',
];
