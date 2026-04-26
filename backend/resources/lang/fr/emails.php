<?php

declare(strict_types=1);

return [
    // ---------------------------------------------------------------------
    // Shared
    // ---------------------------------------------------------------------
    'brand_tagline' => 'Beauté & Style',
    'greeting'      => 'Bonjour :name,',
    'footer_note'   => 'Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet email.',
    'footer_legal'  => 'Email envoyé automatiquement — merci de ne pas répondre.',
    'footer_brand'  => 'Termini Im · Votre salon, vos rendez-vous.',
    'code_hint'     => 'Saisissez ce code dans l\'application',

    // ---------------------------------------------------------------------
    // Verify email
    // ---------------------------------------------------------------------
    'verify' => [
        'subject'    => 'Confirmez votre adresse email — Termini Im',
        'preheader'  => 'Votre code de confirmation Termini Im',
        'eyebrow'    => 'Confirmation de compte',
        'heading'    => 'Un dernier pas vers votre salon',
        'intro'      => 'Bienvenue dans Termini Im. Pour activer votre compte, utilisez le code ci-dessous dans l\'application.',
        'expires_in' => 'Ce code expire dans 24 heures.',
        'ignore'     => 'Vous n\'avez pas créé de compte chez nous ? Aucune action n\'est requise — vous pouvez supprimer cet email en toute sécurité.',
    ],

    // ---------------------------------------------------------------------
    // Welcome client
    // ---------------------------------------------------------------------
    'welcome_client' => [
        'subject'        => 'Bienvenue sur Termini im — ton premier RDV en 30 secondes',
        'preheader'      => 'Ton compte est activé — trouve ton salon dès maintenant',
        'eyebrow'        => 'Bienvenue',
        'heading'        => 'C\'est parti !',
        'intro'          => 'Ton compte est activé. En 3 étapes, tu prends ton premier rendez-vous en beauté.',
        'step1_title'    => 'Trouver ton salon',
        'step1_desc'     => 'Explore les salons près de chez toi par service, disponibilité ou avis.',
        'step2_title'    => 'Réserver en 30 secondes',
        'step2_desc'     => 'Choisis le service, le jour et l\'heure — et confirme sans appeler.',
        'step3_title'    => 'Recevoir un rappel',
        'step3_desc'     => 'Un rappel automatique la veille. Tu ne rates plus jamais ton RDV.',
        'cta'            => 'Trouver mon salon',
        'signature_line1' => 'À très vite sur Termini im,',
        'signature_line2' => 'L\'équipe Termini im · Prishtinë 2026',
    ],

    // ---------------------------------------------------------------------
    // Welcome owner
    // ---------------------------------------------------------------------
    'welcome_owner' => [
        'subject'         => 'Bienvenue — 5 étapes pour ton salon sur Termini im',
        'preheader'       => 'Ton espace salon t\'attend — configure-le en quelques minutes',
        'eyebrow'         => 'Espace propriétaire',
        'heading'         => 'Bienvenue, :salon est en ligne',
        'intro'           => 'Ton salon est créé. Voici les 5 étapes pour être prêt à recevoir tes premiers clients.',
        'step1_title'     => 'Ajouter des photos',
        'step1_desc'      => 'Une galerie soignée double les réservations. Ajoute au moins 3 photos.',
        'step2_title'     => 'Configurer les services',
        'step2_desc'      => 'Durées, tarifs, catégories — tout ce que ton client verra avant de réserver.',
        'step3_title'     => 'Charger les horaires',
        'step3_desc'      => 'Définis tes jours et horaires d\'ouverture pour que les créneaux s\'affichent correctement.',
        'step4_title'     => 'Inviter l\'équipe',
        'step4_desc'      => 'Ajoute tes employés pour qu\'ils gèrent leur propre planning.',
        'step5_title'     => 'Partager ton lien salon',
        'step5_desc'      => 'Envoie ton lien à tes clients habituels et laisse-les réserver directement.',
        'go_link'         => 'Configurer →',
        'cta'             => 'Accéder à mon espace salon',
        'signature_line1' => 'On est là si tu as besoin,',
        'signature_line2' => 'L\'équipe Termini im · Prishtinë 2026',
        'whatsapp_label'  => 'WhatsApp direct : ',
    ],

    // ---------------------------------------------------------------------
    // Employee invitation
    // ---------------------------------------------------------------------
    'employee_invitation' => [
        'subject'    => ':owner t\'invite à rejoindre Termini im',
        'preheader'  => 'Tu as été invité(e) à rejoindre :salon sur Termini im',
        'eyebrow'    => 'Invitation',
        'heading'    => ':owner t\'invite',
        'body'       => ':owner t\'a invité(e) à rejoindre le salon :salon sur Termini im. Accepte l\'invitation pour accéder à ton planning et gérer tes rendez-vous.',
        'cta'        => 'Accepter l\'invitation',
        'link_hint'  => 'Si le bouton ne fonctionne pas, copie ce lien dans ton navigateur :',
        'signature'  => 'L\'équipe de :salon',
    ],

    // ---------------------------------------------------------------------
    // Booking confirmation
    // ---------------------------------------------------------------------
    'booking_confirmation' => [
        'subject'        => 'Ton RDV ✓ chez :salon, :date',
        'preheader'      => 'Confirmation de ton rendez-vous chez :salon',
        'eyebrow'        => 'Confirmation de rendez-vous',
        'heading'        => 'Ton rendez-vous est confirmé',
        'intro'          => 'Bonne nouvelle ! Ton rendez-vous chez :salon est bien enregistré. Retrouve tous les détails ci-dessous.',
        'label_date'     => 'Date',
        'label_time'     => 'Heure',
        'label_service'  => 'Service',
        'label_employee' => 'Professionnel',
        'label_address'  => 'Adresse',
        'maps_link'      => 'Voir sur Maps →',
        'cancel_policy'  => 'Annulation gratuite jusqu\'à 2h avant le rendez-vous. Au-delà, le salon se réserve le droit d\'appliquer des frais.',
        'btn_add_calendar' => 'Ajouter au calendrier',
        'btn_cancel'     => 'Annuler en 1 clic',
        'ics_note'       => 'Le fichier calendrier (.ics) est joint à cet email — il s\'ouvre dans Google Calendar, Apple Calendar et Outlook.',
        'ics_summary'    => 'RDV chez :salon',
        'ics_employee'   => 'Professionnel',
        'ics_cancel'     => 'Annuler',
    ],

    // ---------------------------------------------------------------------
    // Invitation (link-based, employee)
    // ---------------------------------------------------------------------
    'invitation' => [
        'subject'    => 'Invitation à rejoindre :company',
        'greeting'   => 'Bonjour :name,',
        'intro'      => ':owner t\'invite à rejoindre l\'équipe de :company sur Termini im.',
        'cta'        => 'Créer mon compte',
        'expires_at' => 'Ce lien expire le :date.',
        'footer'     => 'Si tu n\'attendais pas cette invitation, ignore simplement cet email.',
    ],

    // ---------------------------------------------------------------------
    // Reset password
    // ---------------------------------------------------------------------
    'reset' => [
        'subject'    => 'Réinitialisation du mot de passe — Termini Im',
        'preheader'  => 'Votre code de réinitialisation Termini Im',
        'eyebrow'    => 'Mot de passe oublié',
        'heading'    => 'Réinitialisez votre mot de passe',
        'intro'      => 'Vous avez demandé la réinitialisation de votre mot de passe. Utilisez le code ci-dessous dans l\'application pour définir un nouveau mot de passe.',
        'expires_in' => 'Ce code expire dans 60 minutes.',
        'ignore'     => 'Vous n\'avez pas demandé cette réinitialisation ? Ignorez simplement cet email — votre mot de passe actuel reste inchangé.',
    ],

    'share_qr' => [
        'subject'        => 'Ton QR code pour :salon',
        'preheader'      => 'Ton QR code de salon est en pièce jointe — imprime-le ou partage-le.',
        'eyebrow'        => 'Carte de visite',
        'heading'        => 'Ton QR code',
        'intro'          => 'Voici le QR code de :salon. Le scan ouvre directement la prise de RDV.',
        'with_employee'  => 'Avec :name',
        'attached_hint'  => '(QR code en pièce jointe — termini-im-qr.png)',
        'bottom_text'    => 'Ajoute-moi en favori et prends RDV',
        'tip'            => 'Imprime-le, encadre-le dans ton salon, ou partage-le par WhatsApp ou Instagram. Le scan ajoute le salon aux favoris du client et ouvre directement la réservation.',
        'cta'            => 'Ouvrir mon salon',
        'signature'      => 'L\'équipe Termini im — bukuria fillon me një takim.',
    ],
];
