{{--
    Booking confirmation email
    Variables:
      $appointment (App\Models\Appointment) — with user, company, service, companyUser.user loaded
      $cancelUrl   (string)
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.booking_confirmation.subject', ['salon' => $appointment->company->name, 'date' => $appointment->date->translatedFormat('d/m/Y')]))
@section('preheader', __('emails.booking_confirmation.preheader', ['salon' => $appointment->company->name]))
@section('eyebrow',   __('emails.booking_confirmation.eyebrow'))
@section('heading',   __('emails.booking_confirmation.heading'))

@section('content')

@php
    $appt      = $appointment;
    $company   = $appt->company;
    $service   = $appt->service;
    $employee  = optional(optional($appt->companyUser)->user);

    $dateFormatted = $appt->date instanceof \Carbon\Carbon
        ? $appt->date->translatedFormat('l d F Y')
        : substr((string) $appt->date, 0, 10);

    $timeStart = substr((string) $appt->start_time, 0, 5);
    $timeEnd   = substr((string) $appt->end_time, 0, 5);

    $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode(
        trim(($company->address ?? '') . ', ' . ($company->city ?? ''))
    );
@endphp

    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.greeting', ['name' => $appt->user->first_name]) }}
    </p>

    {{-- Confirmation badge --}}
    <p style="margin:0 0 28px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:26px; color:#332C29;">
        {{ __('emails.booking_confirmation.intro', ['salon' => $company->name]) }}
    </p>

    {{-- Appointment recap card --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="background:#F7F2EA; border:1px solid #D9CAB3; border-radius:10px; margin:0 0 28px 0; overflow:hidden;">

        {{-- Card header --}}
        <tr>
            <td style="background:#7A2232; padding:14px 20px;">
                <span style="font-family:'Fraunces', Georgia, serif; font-size:17px; color:#FCF7EE; font-weight:400;">
                    {{ $company->name }}
                </span>
            </td>
        </tr>

        {{-- Date / time --}}
        <tr>
            <td style="padding:16px 20px 0 20px; border-bottom:1px solid #E8DCC8;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                        <td width="50%" style="padding-bottom:14px;">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2px; text-transform:uppercase; color:#7A2232; margin-bottom:4px;">
                                {{ __('emails.booking_confirmation.label_date') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311;">
                                {{ $dateFormatted }}
                            </div>
                        </td>
                        <td width="50%" style="padding-bottom:14px;">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2px; text-transform:uppercase; color:#7A2232; margin-bottom:4px;">
                                {{ __('emails.booking_confirmation.label_time') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311;">
                                {{ $timeStart }} – {{ $timeEnd }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Service --}}
        @if ($service)
        <tr>
            <td style="padding:14px 20px 0 20px; border-bottom:1px solid #E8DCC8;">
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2px; text-transform:uppercase; color:#A07A2C; margin-bottom:6px;">
                    {{ __('emails.booking_confirmation.label_service') }}
                </div>
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="padding-bottom:14px;">
                    <tr>
                        <td>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311;">
                                {{ $service->name }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059; margin-top:2px;">
                                {{ $service->duration }} min
                            </div>
                        </td>
                        <td align="right">
                            <div style="font-family:'Fraunces', Georgia, serif; font-size:16px; color:#7A2232;">
                                {{ number_format((float) $service->price, 2) }} €
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @endif

        {{-- Employee (optional) --}}
        @if ($employee && $employee->first_name)
        <tr>
            <td style="padding:14px 20px 0 20px; border-bottom:1px solid #E8DCC8;">
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2px; text-transform:uppercase; color:#7A2232; margin-bottom:4px;">
                    {{ __('emails.booking_confirmation.label_employee') }}
                </div>
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; color:#171311; padding-bottom:14px;">
                    {{ $employee->first_name }} {{ $employee->last_name }}
                </div>
            </td>
        </tr>
        @endif

        {{-- Address with Maps link --}}
        @if ($company->address)
        <tr>
            <td style="padding:14px 20px;">
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2px; text-transform:uppercase; color:#7A2232; margin-bottom:4px;">
                    {{ __('emails.booking_confirmation.label_address') }}
                </div>
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#332C29; line-height:20px;">
                    {{ $company->address }}@if($company->city), {{ $company->city }}@endif
                    &nbsp;<a href="{{ $mapsUrl }}" style="color:#7A2232; font-size:12px; text-decoration:underline;">{{ __('emails.booking_confirmation.maps_link') }}</a>
                </div>
            </td>
        </tr>
        @endif

    </table>

    {{-- Cancellation policy --}}
    <p style="margin:0 0 28px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#716059; background:#EFE6D5; border:1px solid #D9CAB3; border-radius:8px; padding:12px 16px;">
        {{ __('emails.booking_confirmation.cancel_policy') }}
    </p>

    {{-- Action buttons --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 36px 0;">
        <tr>
            <td align="center">
                {{-- Add to calendar --}}
                <a href="{{ $cancelUrl }}"
                   style="display:inline-block; padding:12px 24px; background:#FCF7EE; color:#7A2232; border:1px solid #D9CAB3; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; text-decoration:none; border-radius:8px; margin-right:12px;">
                    {{ __('emails.booking_confirmation.btn_add_calendar') }}
                </a>
                {{-- Cancel --}}
                <a href="{{ $cancelUrl }}"
                   style="display:inline-block; padding:12px 24px; background:#7A2232; color:#FCF7EE; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; text-decoration:none; border-radius:8px;">
                    {{ __('emails.booking_confirmation.btn_cancel') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Note about ICS --}}
    <p style="margin:0 0 32px 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:14px; line-height:22px; color:#716059; text-align:center;">
        {{ __('emails.booking_confirmation.ics_note') }}
    </p>

@endsection
