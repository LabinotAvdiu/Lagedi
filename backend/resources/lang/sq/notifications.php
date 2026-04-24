<?php

declare(strict_types=1);

return [
    // Takim i ri (marrësi: pronari/punonjësi)
    'appointment_created_title' => 'Rezervim i ri',
    'appointment_created_body'  => ':client_name rezervoi :service_name në :time.',

    // Konfirmim (marrësi: klienti)
    'appointment_confirmed_title' => 'Takimi u konfirmua',
    'appointment_confirmed_body'  => 'Takimi juaj për :service_name në :time u konfirmua.',

    // Refuzim (marrësi: klienti)
    'appointment_rejected_title' => 'Takimi u refuzua',
    'appointment_rejected_body'  => 'Kërkesa juaj për :service_name në :time nuk u pranua.',

    // Kujtesë profesionale 1 orë para (marrësi: pronari/punonjësi i caktuar)
    'appointment_reminder_owner_title' => 'Kujtesë — për 1 orë',
    'appointment_reminder_owner_body'  => ':client_name — :service_name në :time.',

    // Kujtesë klienti mbrëmjen para
    'appointment_reminder_evening_title' => 'Kujtesë për nesër',
    'appointment_reminder_evening_body'  => 'Keni takim te :company_name në :time nesër.',

    // Kujtesë klienti 2 orë para
    'appointment_reminder_2h_title' => 'Kujtesë — për 2 orë',
    'appointment_reminder_2h_body'  => 'Takimi juaj fillon për 2 orë, në :time.',

    // Anulim nga klienti (marrësi: pronari/punonjësi)
    'appointment_cancelled_by_client_title' => 'Rezervimi u anulua',
    'appointment_cancelled_by_client_body'  => ':client_name anuloi takimin e :service_name në :time.',

    // Anulim nga salloni (marrësi: klienti)
    'appointment_cancelled_by_owner_title' => 'Takimi u anulua',
    'appointment_cancelled_by_owner_body'  => ':company_name anuloi takimin tuaj të :service_name në :time.',

    // C8 — Vlerësim i ri (marrësi: pronari)
    'review_new_positive_title' => 'Vlerësim i ri — :rating yje ⭐',
    'review_new_neutral_title'  => 'Koment i ri nga :client_name',
    'review_new_body'           => ':comment',

    // C9 — Walk-in i krijuar nga punonjësi (marrësi: pronari)
    'walk_in_created_title' => 'Walk-in te salloni yt',
    'walk_in_created_body'  => ':employee_name shtoi :client_name në :time.',

    // C10 — RDV zhvendosur nga salloni (marrësi: klienti)
    'appointment_rescheduled_by_owner_title' => 'Ndryshim në terminin tënd',
    'appointment_rescheduled_by_owner_body'  => 'Zhvendosur nga :old_time në :new_time. Konfirmo ose anulo.',

    // C11 — RDV zhvendosur nga klienti (marrësi: pronari/punonjësi)
    'appointment_rescheduled_by_client_title' => 'Ndryshim — :client_name',
    'appointment_rescheduled_by_client_body'  => 'Zhvendosur në :new_date :new_time. Konfirmuar automatikisht.',

    // C12 — Kërkesë vlerësimi J+1 (marrësi: klienti)
    'review_request_title' => 'Si shkoi te :salon_name?',
    'review_request_body'  => 'Një vlerësim në 10 sek ndihmon të tjerët të zgjedhin mirë.',

    // C13 — Përgjigje support (marrësi: kërkuesi)
    'support_reply_title' => 'Përgjigje nga ekipi Termini im',
    'support_reply_body'  => ':message',

    // C14 — Kapaciteti i plotë (marrësi: pronari)
    'capacity_full_title' => 'Plot për :date',
    'capacity_full_body'  => 'Të gjitha vendet janë rezervuar. A duhet të shtosh kapacitet?',

    // C15 — Ftesë punonjësi marrë (marrësi: përdoruesi i ftuar)
    'invitation_received_title' => 'Ftesë e re',
    'invitation_received_body'  => ':company të fton t\'i bashkohesh ekipit',

    // C16 — Vendim ftese (marrësi: pronari që ftoi)
    'invitation_accepted_title' => 'Ftesa u pranua',
    'invitation_accepted_body'  => ':email pranoi ftesën tënde',
    'invitation_refused_title'  => 'Ftesa u refuzua',
    'invitation_refused_body'   => ':email refuzoi ftesën tënde',
    'invitation_expired_title'  => 'Ftesa skadoi',
    'invitation_expired_body'   => 'Ftesa për :email ka skaduar',
];
