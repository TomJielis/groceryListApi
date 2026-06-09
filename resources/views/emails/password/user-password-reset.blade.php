@extends('emails.layout')

@section('title', $title)
@section('header-title', __('password-reset.grocery_list'))

@section('content')
    <p style="text-align: center;">{{ __('password-reset.hello') }} {{ $user->name }} 👋</p>
    <p style="text-align: center;">
        {{ __('password-reset.reset_message_1') }}<br>
        {{ __('password-reset.button_reset_password_message') }}
    </p>
    <p style="text-align: center;">
        <a href="{{ $url }}" class="cta-button">{{ __('password-reset.reset_password') }}</a>
    </p>
    <p style="text-align: center; margin-top: 24px;" class="muted">
        {{ __('password-reset.ignore_message') }}
    </p>
@endsection

@section('footer', __('password-reset.footer'))
