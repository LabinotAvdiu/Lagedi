{{--
    Share QR email — sent when a salon owner / employee uses the
    "M'envoyer le QR" button on the Settings → Partage QR page.

    Variables:
      $user         — App\Models\User authenticated owner/employee
      $company      — App\Models\Company
      $caption      — string displayed above the QR (defaults to salon name)
      $employeeName — string|null (e.g. "Lina") when the user shared "with me as employee"

    The QR PNG itself is sent as an attachment (`termini-im-qr.png`),
    so this template references it visually but does NOT inline the PNG.
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.share_qr.subject', ['salon' => $company->name]))
@section('preheader', __('emails.share_qr.preheader'))
@section('eyebrow',   __('emails.share_qr.eyebrow'))
@section('heading',   __('emails.share_qr.heading'))

@section('content')

    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.greeting', ['name' => $user->first_name]) }}
    </p>

    {{-- Intro --}}
    <p style="margin:0 0 24px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:26px; color:#332C29;">
        {{ __('emails.share_qr.intro', ['salon' => $company->name]) }}
    </p>

    {{-- Caption (over the QR — visual reference for what the recipient sees) --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="background:#F7F2EA; border:1px solid #C89B47; border-radius:14px; padding:24px 20px; margin:0 0 28px 0;">
        <tr>
            <td align="center">
                <p style="margin:0 0 14px 0; font-family:'Fraunces', Georgia, serif; font-style:italic; font-size:18px; line-height:1.3; color:#171311;">
                    {{ $caption }}
                </p>
                @if ($employeeName)
                    <p style="margin:0 0 14px 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:14px; color:#7A2232;">
                        {{ __('emails.share_qr.with_employee', ['name' => $employeeName]) }}
                    </p>
                @endif
                <p style="margin:14px 0 0 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059;">
                    {{ __('emails.share_qr.attached_hint') }}
                </p>
                <p style="margin:6px 0 0 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:14px; color:#7A2232;">
                    {{ __('emails.share_qr.bottom_text') }}
                </p>
            </td>
        </tr>
    </table>

    {{-- Tip block --}}
    <p style="margin:0 0 24px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; line-height:22px; color:#332C29;">
        {{ __('emails.share_qr.tip') }}
    </p>

    {{-- CTA — open the salon page --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 32px 0;">
        <tr>
            <td align="center">
                <a href="https://www.termini-im.com/company/{{ $company->id }}"
                   style="display:inline-block; padding:14px 36px; background:#7A2232; color:#FCF7EE; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; letter-spacing:0.3px; text-decoration:none; border-radius:8px;">
                    {{ __('emails.share_qr.cta') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Signature --}}
    <p style="margin:0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:14px; line-height:22px; color:#716059;">
        {{ __('emails.share_qr.signature') }}
    </p>

@endsection
