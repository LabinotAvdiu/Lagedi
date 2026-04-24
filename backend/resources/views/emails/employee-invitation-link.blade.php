{{--
    Employee invitation — link-based (new flow)
    Variables:
      $firstName   (string)          — invited person's first name
      $ownerName   (string)          — owner full name
      $companyName (string)          — salon name
      $deepLink    (string)          — registration URL with pre-filled token
      $expiresAt   (Carbon\Carbon)   — invitation expiry date
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.invitation.subject', ['company' => $companyName]))
@section('preheader', __('emails.invitation.intro', ['owner' => $ownerName, 'company' => $companyName]))
@section('eyebrow',   'Invitation')
@section('heading',   $companyName)

@section('content')

    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.invitation.greeting', ['name' => $firstName]) }}
    </p>

    {{-- Intro body --}}
    <p style="margin:0 0 32px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:26px; color:#332C29;">
        {{ __('emails.invitation.intro', ['owner' => $ownerName, 'company' => $companyName]) }}
    </p>

    {{-- CTA button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px 0;">
        <tr>
            <td align="center">
                <a href="{{ $deepLink }}"
                   style="display:inline-block; padding:14px 36px; background:#7A2232; color:#FCF7EE; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; letter-spacing:0.3px; text-decoration:none; border-radius:8px;">
                    {{ __('emails.invitation.cta') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Expiry notice --}}
    <p style="margin:0 0 24px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#716059; text-align:center;">
        @php
            try {
                $formattedDate = $expiresAt->isoFormat('D MMMM YYYY');
            } catch (\Throwable $e) {
                $formattedDate = $expiresAt->format('d/m/Y');
            }
        @endphp
        {{ __('emails.invitation.expires_at', ['date' => $formattedDate]) }}
    </p>

    {{-- Fallback link --}}
    <p style="margin:0 0 8px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:12px; line-height:18px; color:#716059; text-align:center;">
        <a href="{{ $deepLink }}" style="color:#7A2232; word-break:break-all;">{{ $deepLink }}</a>
    </p>

    {{-- Footer disclaimer --}}
    <p style="margin:24px 0 12px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:12px; line-height:18px; color:#A07A2C; text-align:center;">
        {{ __('emails.invitation.footer') }}
    </p>

@endsection
