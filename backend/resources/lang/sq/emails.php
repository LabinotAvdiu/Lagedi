<?php

declare(strict_types=1);

return [
    // ---------------------------------------------------------------------
    // Shared
    // ---------------------------------------------------------------------
    'brand_tagline' => 'Bukuri & Stil',
    'greeting'      => 'Përshëndetje :name,',
    'footer_note'   => 'Nëse nuk e ke nisur ti këtë kërkesë, mund ta injorosh këtë email.',
    'footer_legal'  => 'Ky email është dërguar automatikisht — mos u përgjigj këtij mesazhi.',
    'footer_brand'  => 'Termini Im · Salloni yt, terminet e tua.',
    'code_hint'     => 'Shkruaje këtë kod në aplikacion',

    // ---------------------------------------------------------------------
    // Verify email
    // ---------------------------------------------------------------------
    'verify' => [
        'subject'    => 'Konfirmo email-in tënd — Termini Im',
        'preheader'  => 'Kodi yt i konfirmimit Termini Im',
        'eyebrow'    => 'Konfirmim i llogarisë',
        'heading'    => 'Një hap i fundit drejt sallonit tënd',
        'intro'      => 'Mirë se erdhe në Termini Im. Për të aktivizuar llogarinë, shkruaje kodin më poshtë në aplikacion.',
        'expires_in' => 'Ky kod skadon pas 24 orësh.',
        'ignore'     => 'Nuk ke hapur llogari te ne? Nuk nevojitet asnjë veprim — mund ta fshish këtë email pa problem.',
    ],

    // ---------------------------------------------------------------------
    // Welcome client
    // ---------------------------------------------------------------------
    'welcome_client' => [
        'subject'        => 'Mirë se erdhe te Termini im — termini yt i parë në 30 sek',
        'preheader'      => 'Llogaria jote është aktive — gjej sallonin tënd tani',
        'eyebrow'        => 'Mirë se erdhe',
        'heading'        => 'Hajde fillojmë!',
        'intro'          => 'Llogaria jote është aktivizuar. Në 3 hapa, merr terminin tënd të parë.',
        'step1_title'    => 'Gjej sallonin',
        'step1_desc'     => 'Shfletoni sallone afër jush sipas shërbimit, disponueshmërisë ose vlerësimeve.',
        'step2_title'    => 'Rezervo brenda 30 sekondave',
        'step2_desc'     => 'Zgjidh shërbimin, ditën dhe orën — konfirmo pa telefonuar.',
        'step3_title'    => 'Merr një kujtesë',
        'step3_desc'     => 'Një kujtesë automatike një ditë para. Nuk e humb më asnjë termin.',
        'cta'            => 'Gjej sallonin tim',
        'signature_line1' => 'Shihemi te Termini im,',
        'signature_line2' => 'Ekipi Termini im · Prishtinë 2026',
    ],

    // ---------------------------------------------------------------------
    // Welcome owner
    // ---------------------------------------------------------------------
    'welcome_owner' => [
        'subject'         => 'Mirë se erdhe — 5 hapa për salloni yt në Termini im',
        'preheader'       => 'Hapësira e sallonit tënd është gati — konfiguroje brenda disa minutave',
        'eyebrow'         => 'Paneli i pronarit',
        'heading'         => 'Mirë se erdhe, :salon është aktiv',
        'intro'           => 'Salloni yt është krijuar. Këtu janë 5 hapat për të qenë gati për klientët e parë.',
        'step1_title'     => 'Shto foto',
        'step1_desc'      => 'Një galeri e mirë dyfishon rezervimet. Shto të paktën 3 foto.',
        'step2_title'     => 'Konfiguro shërbimet',
        'step2_desc'      => 'Kohëzgjatjet, çmimet, kategoritë — gjithçka që klienti sheh para rezervimit.',
        'step3_title'     => 'Vendos oraret',
        'step3_desc'      => 'Përcakto ditët dhe oraret e hapjes për të shfaqur oraret e lira.',
        'step4_title'     => 'Fto ekipin',
        'step4_desc'      => 'Shto punonjësit që të menaxhojnë orarin e tyre.',
        'step5_title'     => 'Ndaj lidhjen e sallonit',
        'step5_desc'      => 'Dërgoja lidhjen klientëve të zakonshëm dhe lërë t\'i rezervojnë direkt.',
        'go_link'         => 'Konfiguro →',
        'cta'             => 'Shko te paneli im',
        'signature_line1' => 'Jemi këtu nëse ke nevojë,',
        'signature_line2' => 'Ekipi Termini im · Prishtinë 2026',
        'whatsapp_label'  => 'WhatsApp direkt: ',
    ],

    // ---------------------------------------------------------------------
    // Employee invitation
    // ---------------------------------------------------------------------
    'employee_invitation' => [
        'subject'    => ':owner të ka ftuar te Termini im',
        'preheader'  => 'Je ftuar të bashkohesh me :salon në Termini im',
        'eyebrow'    => 'Ftesë',
        'heading'    => ':owner të ka ftuar',
        'body'       => ':owner të ka ftuar të bashkohesh me sallonin :salon në Termini im. Prano ftesën për të hyrë në orarin tënd dhe menaxhuar terminet.',
        'cta'        => 'Prano ftesën',
        'link_hint'  => 'Nëse butoni nuk funksionon, kopjo këtë lidhje në shfletuesin tënd:',
        'signature'  => 'Ekipi i :salon',
    ],

    // ---------------------------------------------------------------------
    // Booking confirmation
    // ---------------------------------------------------------------------
    'booking_confirmation' => [
        'subject'        => 'Termini yt ✓ te :salon, :date',
        'preheader'      => 'Konfirmim i terminit tënd te :salon',
        'eyebrow'        => 'Termin i konfirmuar',
        'heading'        => 'Termini yt është konfirmuar',
        'intro'          => 'Lajm i mirë! Termini yt te :salon është regjistruar. Gjej të gjitha detajet më poshtë.',
        'label_date'     => 'Data',
        'label_time'     => 'Ora',
        'label_service'  => 'Shërbimi',
        'label_employee' => 'Profesionisti',
        'label_address'  => 'Adresa',
        'maps_link'      => 'Shiko në Maps →',
        'cancel_policy'  => 'Anulim falas deri 2 orë para terminit. Pas kësaj, salloni mund të aplikojë tarifë anulimi.',
        'btn_add_calendar' => 'Shto në kalendar',
        'btn_cancel'     => 'Anulo me 1 klik',
        'ics_note'       => 'Një skedar kalendari (.ics) është bashkangjitur këtij emaili — hapet në Google Calendar, Apple Calendar dhe Outlook.',
        'ics_summary'    => 'Termin te :salon',
        'ics_employee'   => 'Profesionisti',
        'ics_cancel'     => 'Anulo',
    ],

    // ---------------------------------------------------------------------
    // Invitation (link-based, employee)
    // ---------------------------------------------------------------------
    'invitation' => [
        'subject'    => 'Ftesë për t\'u bashkuar me :company',
        'greeting'   => 'Përshëndetje :name,',
        'intro'      => ':owner të ka ftuar të bashkohesh me :company në Termini im.',
        'cta'        => 'Krijo llogarinë time',
        'expires_at' => 'Ky link skadon më :date.',
        'footer'     => 'Nëse nuk e prisje këtë ftesë, thjesht injoroje këtë email.',
    ],

    // ---------------------------------------------------------------------
    // Reset password
    // ---------------------------------------------------------------------
    'reset' => [
        'subject'    => 'Rivendosja e fjalëkalimit — Termini Im',
        'preheader'  => 'Kodi yt për rivendosjen e fjalëkalimit',
        'eyebrow'    => 'Kam harruar fjalëkalimin',
        'heading'    => 'Rivendos fjalëkalimin tënd',
        'intro'      => 'Ke kërkuar rivendosjen e fjalëkalimit. Përdor kodin më poshtë në aplikacion për të caktuar një fjalëkalim të ri.',
        'expires_in' => 'Ky kod skadon pas 60 minutash.',
        'ignore'     => 'Nuk ke kërkuar ti këtë rivendosje? Injoroje këtë email — fjalëkalimi yt aktual mbetet i pandryshuar.',
    ],
];
