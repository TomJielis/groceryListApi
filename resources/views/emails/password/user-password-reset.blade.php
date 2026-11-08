@extends('emails.layout')

@section('title', __('password-reset.reset_password'))
@section('header-title', __('password-reset.reset_password'))

@section('content')
    <x-email.heading>{{ __('password-reset.hello') }} {{ $user->name }} 👋</x-email.heading>
    <x-email.text>{{ __('password-reset.reset_message_1') }}</x-email.text>
    <x-email.text>{{ __('password-reset.button_reset_password_message') }}</x-email.text>

    <x-email.button :href="$url">{{ __('password-reset.reset_password') }}</x-email.button>

    <x-email.text variant="muted">{{ trim(__('password-reset.ignore_message')) }}</x-email.text>
@endsection

@section('footer', __('password-reset.footer'))
