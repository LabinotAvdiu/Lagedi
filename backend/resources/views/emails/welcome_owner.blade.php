{{--
    Welcome owner email — 5-step onboarding checklist
    Variables: $user (App\Models\User), $company (App\Models\Company)
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.welcome_owner.subject'))
@section('preheader', __('emails.welcome_owner.preheader'))
@section('eyebrow',   __('emails.welcome_owner.eyebrow'))
@section('heading',   __('emails.welcome_owner.heading', ['salon' => $company->name]))

@section('content')

    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.greeting', ['name' => $user->first_name]) }}
    </p>

    {{-- Intro --}}
    <p style="margin:0 0 28px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:26px; color:#332C29;">
        {{ __('emails.welcome_owner.intro') }}
    </p>

    {{-- 5-step checklist --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 32px 0;">

        @php
            $steps = [
                ['key' => 'step1', 'color' => '#7A2232', 'link' => 'https://app.termini-im.com/my-salon/gallery'],
                ['key' => 'step2', 'color' => '#C89B47', 'link' => 'https://app.termini-im.com/my-salon/services'],
                ['key' => 'step3', 'color' => '#9E3D4F', 'link' => 'https://app.termini-im.com/my-salon/hours'],
                ['key' => 'step4', 'color' => '#A07A2C', 'link' => 'https://app.termini-im.com/my-salon/team'],
                ['key' => 'step5', 'color' => '#511522', 'link' => 'https://app.termini-im.com/my-salon'],
            ];
        @endphp

        @foreach ($steps as $i => $step)
        <tr>
            <td style="padding:0 0 {{ $i < 4 ? '12' : '0' }}px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#F7F2EA; border:1px solid #E8DCC8; border-left:3px solid {{ $step['color'] }}; border-radius:8px; padding:14px 20px;">
                    <tr>
                        <td width="36" valign="middle" style="padding-right:14px;">
                            <div style="width:28px; height:28px; background:{{ $step['color'] }}; border-radius:50%; text-align:center; line-height:28px;">
                                <span style="font-family:'Fraunces', Georgia, serif; font-size:13px; color:#FCF7EE; font-weight:400;">{{ $i + 1 }}</span>
                            </div>
                        </td>
                        <td valign="middle">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311; margin-bottom:2px;">
                                {{ __('emails.welcome_owner.' . $step['key'] . '_title') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059; line-height:20px;">
                                {{ __('emails.welcome_owner.' . $step['key'] . '_desc') }}
                                &nbsp;<a href="{{ $step['link'] }}" style="color:#7A2232; text-decoration:underline;">{{ __('emails.welcome_owner.go_link') }}</a>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @endforeach

    </table>

    {{-- Primary CTA --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 36px 0;">
        <tr>
            <td align="center">
                <a href="https://app.termini-im.com/my-salon"
                   style="display:inline-block; padding:14px 36px; background:#7A2232; color:#FCF7EE; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; letter-spacing:0.3px; text-decoration:none; border-radius:8px;">
                    {{ __('emails.welcome_owner.cta') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Founder signature with WhatsApp --}}
    <p style="margin:0 0 6px 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:15px; line-height:24px; color:#332C29;">
        {{ __('emails.welcome_owner.signature_line1') }}
    </p>
    <p style="margin:0 0 4px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#716059;">
        {{ __('emails.welcome_owner.signature_line2') }}
    </p>
    <p style="margin:0 0 32px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#716059;">
        {{ __('emails.welcome_owner.whatsapp_label') }}
        <a href="https://wa.me/38349000000" style="color:#7A2232; text-decoration:none;">+383 49 000 000</a>
    </p>

@endsection
