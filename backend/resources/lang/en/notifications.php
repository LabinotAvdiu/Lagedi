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
];
