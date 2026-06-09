@extends('emails.layout')

@section('title', __('invite.title'))
@section('header-title', __('invite.title'))

@section('content')
    <h2 style="text-align: center;">{{ __('invite.title') }}</h2>
    <p style="text-align: center;">
        {{ __('invite.greeting', ['name' => $invitedUser->name ?? '']) }}<br>
        {{ __('invite.invited_by', ['user' => $user->name, 'list' => $list->name]) }}
    </p>
    @if(!isset($invitedUser))
        <p style="text-align: center;">{{ __('invite.create_account') }}</p>
        <p style="text-align: center;">
            <a href="{{ $url }}" class="cta-button">{{ __('invite.create_account_button') }}</a>
        </p>
        <p style="text-align: center; margin-top: 24px;" class="muted">{{ __('invite.already_account') }}</p>
    @endif
    <p style="text-align: center; margin-top: 32px; color: #64748b; font-size: 14px;">
        {{ __('invite.enjoy') }}<br>
        {{ __('invite.team') }}
    </p>
@endsection

@section('footer', __('invite.footer'))
