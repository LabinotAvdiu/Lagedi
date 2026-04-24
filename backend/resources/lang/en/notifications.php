<?php

declare(strict_types=1);

return [
    // New appointment (recipient: owner/employee)
    'appointment_created_title' => 'New booking',
    'appointment_created_body'  => ':client_name booked :service_name at :time.',

    // Confirmation (recipient: client)
    'appointment_confirmed_title' => 'Booking confirmed',
    'appointment_confirmed_body'  => 'Your :service_name appointment at :time has been confirmed.',

    // Rejection (recipient: client)
    'appointment_rejected_title' => 'Booking declined',
    'appointment_rejected_body'  => 'Your :service_name appointment request at :time was declined.',

    // Pro reminder 1h before (recipient: owner/assigned employee)
    'appointment_reminder_owner_title' => 'Reminder — in 1 hour',
    'appointment_reminder_owner_body'  => ':client_name — :service_name at :time.',

    // Client evening reminder (day before at 20:00)
    'appointment_reminder_evening_title' => 'Reminder for tomorrow',
    'appointment_reminder_evening_body'  => 'You have an appointment at :company_name at :time tomorrow.',

    // Client 2h reminder
    'appointment_reminder_2h_title' => 'Reminder — in 2 hours',
    'appointment_reminder_2h_body'  => 'Your appointment starts in 2 hours at :time.',

    // Cancellation by client (recipient: owner/employee)
    'appointment_cancelled_by_client_title' => 'Booking cancelled',
    'appointment_cancelled_by_client_body'  => ':client_name cancelled their :service_name appointment at :time.',

    // Cancellation by salon (recipient: client)
    'appointment_cancelled_by_owner_title' => 'Appointment cancelled',
    'appointment_cancelled_by_owner_body'  => ':company_name cancelled your :service_name appointment at :time.',

    // C8 — New review (recipient: owner)
    'review_new_positive_title' => 'New review — :rating stars ⭐',
    'review_new_neutral_title'  => 'New comment from :client_name',
    'review_new_body'           => ':comment',

    // C9 — Walk-in created by employee (recipient: owner)
    'walk_in_created_title' => 'Walk-in at your salon',
    'walk_in_created_body'  => ':employee_name added :client_name at :time.',

    // C10 — Appointment rescheduled by salon (recipient: client)
    'appointment_rescheduled_by_owner_title' => 'Your appointment has been moved',
    'appointment_rescheduled_by_owner_body'  => 'Moved from :old_time to :new_time. Confirm or cancel.',

    // C11 — Appointment rescheduled by client (recipient: owner/employee)
    'appointment_rescheduled_by_client_title' => 'Change — :client_name',
    'appointment_rescheduled_by_client_body'  => 'Moved to :new_date at :new_time. Auto-confirmed.',

    // C12 — Review request J+1 (recipient: client)
    'review_request_title' => 'How was your visit at :salon_name?',
    'review_request_body'  => 'A 10-sec review helps others make the right choice.',

    // C13 — Support reply (recipient: requester)
    'support_reply_title' => 'Reply from the Termini im team',
    'support_reply_body'  => ':message',

    // C14 — Capacity full (recipient: owner)
    'capacity_full_title' => 'Full for :date',
    'capacity_full_body'  => 'All slots are booked. Do you need to increase capacity?',

    // C15 — Employee invitation received (recipient: invited user)
    'invitation_received_title' => 'New invitation',
    'invitation_received_body'  => ':company invites you to join the team',

    // C16 — Invitation decision (recipient: owner who invited)
    'invitation_accepted_title' => 'Invitation accepted',
    'invitation_accepted_body'  => ':email accepted your invitation',
    'invitation_refused_title'  => 'Invitation declined',
    'invitation_refused_body'   => ':email declined your invitation',
    'invitation_expired_title'  => 'Invitation expired',
    'invitation_expired_body'   => 'The invitation for :email has expired',
];
