{{--
    Welcome client email
    Variables: $user (App\Models\User)
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.welcome_client.subject'))
@section('preheader', __('emails.welcome_client.preheader'))
@section('eyebrow',   __('emails.welcome_client.eyebrow'))
@section('heading',   __('emails.welcome_client.heading'))

@section('content')

    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.greeting', ['name' => $user->first_name]) }}
    </p>

    {{-- Intro --}}
    <p style="margin:0 0 32px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:26px; color:#332C29;">
        {{ __('emails.welcome_client.intro') }}
    </p>

    {{-- 3-step visual block --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 32px 0;">

        {{-- Step 1 --}}
        <tr>
            <td style="padding:0 0 16px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#F7F2EA; border:1px solid #E8DCC8; border-left:3px solid #7A2232; border-radius:8px; padding:16px 20px;">
                    <tr>
                        <td width="36" valign="middle" style="padding-right:14px;">
                            <div style="width:32px; height:32px; background:#7A2232; border-radius:50%; text-align:center; line-height:32px;">
                                <span style="font-family:'Fraunces', Georgia, serif; font-size:15px; color:#FCF7EE; font-weight:400;">1</span>
                            </div>
                        </td>
                        <td valign="middle">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311; margin-bottom:2px;">
                                {{ __('emails.welcome_client.step1_title') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059; line-height:20px;">
                                {{ __('emails.welcome_client.step1_desc') }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Step 2 --}}
        <tr>
            <td style="padding:0 0 16px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#F7F2EA; border:1px solid #E8DCC8; border-left:3px solid #C89B47; border-radius:8px; padding:16px 20px;">
                    <tr>
                        <td width="36" valign="middle" style="padding-right:14px;">
                            <div style="width:32px; height:32px; background:#C89B47; border-radius:50%; text-align:center; line-height:32px;">
                                <span style="font-family:'Fraunces', Georgia, serif; font-size:15px; color:#FCF7EE; font-weight:400;">2</span>
                            </div>
                        </td>
                        <td valign="middle">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311; margin-bottom:2px;">
                                {{ __('emails.welcome_client.step2_title') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059; line-height:20px;">
                                {{ __('emails.welcome_client.step2_desc') }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- Step 3 --}}
        <tr>
            <td style="padding:0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
                       style="background:#F7F2EA; border:1px solid #E8DCC8; border-left:3px solid #9E3D4F; border-radius:8px; padding:16px 20px;">
                    <tr>
                        <td width="36" valign="middle" style="padding-right:14px;">
                            <div style="width:32px; height:32px; background:#9E3D4F; border-radius:50%; text-align:center; line-height:32px;">
                                <span style="font-family:'Fraunces', Georgia, serif; font-size:15px; color:#FCF7EE; font-weight:400;">3</span>
                            </div>
                        </td>
                        <td valign="middle">
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:14px; font-weight:600; color:#171311; margin-bottom:2px;">
                                {{ __('emails.welcome_client.step3_title') }}
                            </div>
                            <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059; line-height:20px;">
                                {{ __('emails.welcome_client.step3_desc') }}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

    </table>

    {{-- CTA button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 36px 0;">
        <tr>
            <td align="center">
                <a href="https://www.termini-im.com"
                   style="display:inline-block; padding:14px 36px; background:#7A2232; color:#FCF7EE; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; letter-spacing:0.3px; text-decoration:none; border-radius:8px;">
                    {{ __('emails.welcome_client.cta') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Founder signature --}}
    <p style="margin:0 0 6px 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:15px; line-height:24px; color:#332C29;">
        {{ __('emails.welcome_client.signature_line1') }}
    </p>
    <p style="margin:0 0 32px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#716059;">
        {{ __('emails.welcome_client.signature_line2') }}
    </p>

@endsection
