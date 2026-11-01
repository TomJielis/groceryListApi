@extends('emails.layout')

@section('title', __('cookbook-invite.title'))
@section('header-title', __('cookbook-invite.title'))

@section('content')
    <h2 style="text-align: center;">{{ __('cookbook-invite.title') }}</h2>
    <p style="text-align: center;">
        {{ __('invite.greeting', ['name' => $invitedUser->name ?? '']) }}<br>
        {{ __('cookbook-invite.invited_by', ['user' => $user->name, 'cookbook' => $cookbook->name]) }}
    </p>
    @if(!isset($invitedUser))
        <p style="text-align: center;">{{ __('cookbook-invite.create_account') }}</p>
        <p style="text-align: center;">
            <a href="{{ $url }}" class="cta-button">{{ __('invite.create_account_button') }}</a>
        </p>
        <p style="text-align: center; margin-top: 24px;" class="muted">{{ __('invite.already_account') }}</p>
    @else
        <p style="text-align: center;">{{ __('cookbook-invite.open_app') }}</p>
    @endif
    <p style="text-align: center; margin-top: 32px; color: #64748b; font-size: 14px;">
        {{ __('cookbook-invite.enjoy') }}<br>
        {{ __('invite.team') }}
    </p>
@endsection

@section('footer', __('cookbook-invite.footer'))
