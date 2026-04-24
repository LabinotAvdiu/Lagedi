<?php

declare(strict_types=1);

return [
    // ---------------------------------------------------------------------
    // Shared
    // ---------------------------------------------------------------------
    'brand_tagline' => 'Beauty & Style',
    'greeting'      => 'Hello :name,',
    'footer_note'   => 'If you didn\'t initiate this request, you can safely ignore this email.',
    'footer_legal'  => 'This email was sent automatically — please don\'t reply.',
    'footer_brand'  => 'Termini Im · Your salon, your appointments.',
    'code_hint'     => 'Enter this code in the app',

    // ---------------------------------------------------------------------
    // Verify email
    // ---------------------------------------------------------------------
    'verify' => [
        'subject'    => 'Confirm your email address — Termini Im',
        'preheader'  => 'Your Termini Im confirmation code',
        'eyebrow'    => 'Account confirmation',
        'heading'    => 'One last step to your salon',
        'intro'      => 'Welcome to Termini Im. To activate your account, enter the code below in the app.',
        'expires_in' => 'This code expires in 24 hours.',
        'ignore'     => 'Didn\'t create an account with us? No action is needed — feel free to delete this email.',
    ],

    // ---------------------------------------------------------------------
    // Welcome client
    // ---------------------------------------------------------------------
    'welcome_client' => [
        'subject'        => 'Welcome to Termini im — your first appointment in 30 seconds',
        'preheader'      => 'Your account is active — find your salon now',
        'eyebrow'        => 'Welcome',
        'heading'        => 'Let\'s go!',
        'intro'          => 'Your account is active. In 3 steps, book your first appointment effortlessly.',
        'step1_title'    => 'Find your salon',
        'step1_desc'     => 'Browse salons near you by service, availability or reviews.',
        'step2_title'    => 'Book in 30 seconds',
        'step2_desc'     => 'Choose the service, day and time — confirm without calling.',
        'step3_title'    => 'Get a reminder',
        'step3_desc'     => 'An automatic reminder the day before. Never miss an appointment again.',
        'cta'            => 'Find my salon',
        'signature_line1' => 'See you on Termini im,',
        'signature_line2' => 'The Termini im team · Prishtinë 2026',
    ],

    // ---------------------------------------------------------------------
    // Welcome owner
    // ---------------------------------------------------------------------
    'welcome_owner' => [
        'subject'         => 'Welcome — 5 steps to get your salon live on Termini im',
        'preheader'       => 'Your salon space is ready — set it up in minutes',
        'eyebrow'         => 'Owner dashboard',
        'heading'         => 'Welcome, :salon is live',
        'intro'           => 'Your salon is created. Here are the 5 steps to be ready for your first clients.',
        'step1_title'     => 'Add photos',
        'step1_desc'      => 'A well-curated gallery doubles bookings. Add at least 3 photos.',
        'step2_title'     => 'Configure services',
        'step2_desc'      => 'Durations, prices, categories — everything your client sees before booking.',
        'step3_title'     => 'Set your opening hours',
        'step3_desc'      => 'Define your opening days and hours so slots display correctly.',
        'step4_title'     => 'Invite your team',
        'step4_desc'      => 'Add your staff so they can manage their own schedule.',
        'step5_title'     => 'Share your salon link',
        'step5_desc'      => 'Send your link to existing clients and let them book directly.',
        'go_link'         => 'Set up →',
        'cta'             => 'Go to my salon dashboard',
        'signature_line1' => 'We\'re here if you need us,',
        'signature_line2' => 'The Termini im team · Prishtinë 2026',
        'whatsapp_label'  => 'Direct WhatsApp: ',
    ],

    // ---------------------------------------------------------------------
    // Employee invitation
    // ---------------------------------------------------------------------
    'employee_invitation' => [
        'subject'    => ':owner has invited you to join Termini im',
        'preheader'  => 'You have been invited to join :salon on Termini im',
        'eyebrow'    => 'Invitation',
        'heading'    => ':owner has invited you',
        'body'       => ':owner has invited you to join the salon :salon on Termini im. Accept the invitation to access your schedule and manage your appointments.',
        'cta'        => 'Accept invitation',
        'link_hint'  => 'If the button doesn\'t work, copy this link into your browser:',
        'signature'  => 'The :salon team',
    ],

    // ---------------------------------------------------------------------
    // Booking confirmation
    // ---------------------------------------------------------------------
    'booking_confirmation' => [
        'subject'        => 'Your appointment ✓ at :salon, :date',
        'preheader'      => 'Confirmation of your appointment at :salon',
        'eyebrow'        => 'Appointment confirmed',
        'heading'        => 'Your appointment is confirmed',
        'intro'          => 'Great news! Your appointment at :salon is booked. Find all the details below.',
        'label_date'     => 'Date',
        'label_time'     => 'Time',
        'label_service'  => 'Service',
        'label_employee' => 'Professional',
        'label_address'  => 'Address',
        'maps_link'      => 'View on Maps →',
        'cancel_policy'  => 'Free cancellation up to 2 hours before the appointment. After that, the salon may apply a cancellation fee.',
        'btn_add_calendar' => 'Add to calendar',
        'btn_cancel'     => 'Cancel in 1 click',
        'ics_note'       => 'A calendar file (.ics) is attached to this email — it opens in Google Calendar, Apple Calendar and Outlook.',
        'ics_summary'    => 'Appointment at :salon',
        'ics_employee'   => 'Professional',
        'ics_cancel'     => 'Cancel',
    ],

    // ---------------------------------------------------------------------
    // Invitation (link-based, employee)
    // ---------------------------------------------------------------------
    'invitation' => [
        'subject'    => 'Invitation to join :company',
        'greeting'   => 'Hello :name,',
        'intro'      => ':owner has invited you to join :company on Termini im.',
        'cta'        => 'Create my account',
        'expires_at' => 'This link expires on :date.',
        'footer'     => 'If you weren\'t expecting this invitation, just ignore this email.',
    ],

    // ---------------------------------------------------------------------
    // Reset password
    // ---------------------------------------------------------------------
    'reset' => [
        'subject'    => 'Password reset — Termini Im',
        'preheader'  => 'Your Termini Im password reset code',
        'eyebrow'    => 'Forgot password',
        'heading'    => 'Reset your password',
        'intro'      => 'You requested a password reset. Use the code below in the app to set a new password.',
        'expires_in' => 'This code expires in 60 minutes.',
        'ignore'     => 'Didn\'t request a reset? Just ignore this email — your current password stays unchanged.',
    ],
];
