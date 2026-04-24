{{--
    Email verification
    Variables: $user (App\Models\User), $code (6-char plain token)
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.verify.subject'))
@section('preheader', __('emails.verify.preheader'))
@section('eyebrow',   __('emails.verify.eyebrow'))
@section('heading',   __('emails.verify.heading'))

@section('content')
    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.greeting', ['name' => $user->first_name]) }}
    </p>

    {{-- Intro --}}
    <p style="margin:0 0 32px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:24px; color:#332C29;">
        {{ __('emails.verify.intro') }}
    </p>

    {{-- ==== CODE (hero block) ==== --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 16px 0;">
        <tr>
            <td align="center" style="background:#F7F2EA; border:1px solid #D9CAB3; border-left:3px solid #7A2232; border-radius:10px; padding:30px 20px 26px 20px;">
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:10px; font-weight:500; letter-spacing:2.8px; text-transform:uppercase; color:#A07A2C; margin-bottom:14px;">
                    {{ __('emails.code_hint') }}
                </div>
                <div class="code-cell" style="font-family:'Fraunces', 'Courier New', Georgia, monospace; font-size:40px; line-height:1; font-weight:400; letter-spacing:14px; color:#171311; padding-left:14px;">
                    {{ $code }}
                </div>
            </td>
        </tr>
    </table>

    {{-- Expiration note --}}
    <p style="margin:14px 0 32px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; line-height:20px; color:#716059; text-align:center;">
        {{ __('emails.verify.expires_in') }}
    </p>

    {{-- Emphasis line — Instrument Serif italic --}}
    <p style="margin:0 0 8px 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:14px; line-height:22px; color:#716059;">
        {{ __('emails.verify.ignore') }}
    </p>
@endsection
