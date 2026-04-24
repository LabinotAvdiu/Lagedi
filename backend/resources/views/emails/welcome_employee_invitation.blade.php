{{--
    Employee invitation email
    Variables:
      $employee  (App\Models\User)  — the person being invited
      $owner     (App\Models\User)  — the owner who sent the invite
      $company   (App\Models\Company)
      $inviteUrl (string)
--}}
@extends('emails.layouts.base')

@section('title',     __('emails.employee_invitation.subject', ['owner' => $owner->first_name]))
@section('preheader', __('emails.employee_invitation.preheader', ['owner' => $owner->first_name, 'salon' => $company->name]))
@section('eyebrow',   __('emails.employee_invitation.eyebrow'))
@section('heading',   __('emails.employee_invitation.heading', ['owner' => $owner->first_name]))

@section('content')

    {{-- Greeting --}}
    <p style="margin:0 0 18px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:16px; line-height:24px; color:#171311;">
        {{ __('emails.greeting', ['name' => $employee->first_name]) }}
    </p>

    {{-- Invitation body --}}
    <p style="margin:0 0 24px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; line-height:26px; color:#332C29;">
        {{ __('emails.employee_invitation.body', [
            'owner' => $owner->first_name . ' ' . $owner->last_name,
            'salon' => $company->name,
        ]) }}
    </p>

    {{-- Salon info block --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
           style="background:#F7F2EA; border:1px solid #E8DCC8; border-left:3px solid #C89B47; border-radius:8px; padding:16px 20px; margin:0 0 32px 0;">
        <tr>
            <td>
                <div style="font-family:'Fraunces', Georgia, serif; font-size:18px; color:#171311; margin-bottom:4px;">
                    {{ $company->name }}
                </div>
                @if ($company->address)
                <div style="font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:13px; color:#716059; line-height:20px;">
                    {{ $company->address }}@if($company->city), {{ $company->city }}@endif
                </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Accept CTA --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 32px 0;">
        <tr>
            <td align="center">
                <a href="{{ $inviteUrl }}"
                   style="display:inline-block; padding:14px 36px; background:#7A2232; color:#FCF7EE; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:15px; font-weight:600; letter-spacing:0.3px; text-decoration:none; border-radius:8px;">
                    {{ __('emails.employee_invitation.cta') }}
                </a>
            </td>
        </tr>
    </table>

    {{-- Fallback URL for text-only clients --}}
    <p style="margin:0 0 8px 0; font-family:'Instrument Sans', Helvetica, Arial, sans-serif; font-size:12px; line-height:18px; color:#716059; text-align:center;">
        {{ __('emails.employee_invitation.link_hint') }}<br>
        <a href="{{ $inviteUrl }}" style="color:#7A2232; word-break:break-all;">{{ $inviteUrl }}</a>
    </p>

    {{-- Signature: from the salon, not Termini Im --}}
    <p style="margin:24px 0 6px 0; font-family:'Instrument Serif', Georgia, serif; font-style:italic; font-size:15px; line-height:24px; color:#332C29;">
        {{ __('emails.employee_invitation.signature', ['salon' => $company->name]) }}
    </p>

@endsection
