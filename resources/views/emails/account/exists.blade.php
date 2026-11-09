@extends('emails.layout')

@section('title', __('account-exists.subject'))
@section('header-title', __('account-exists.subject'))

@section('content')
    <x-email.heading>{{ __('account-exists.hello') }} {{ $user->name }} 👋</x-email.heading>
    <x-email.text>{{ __('account-exists.message') }}</x-email.text>
    <x-email.text>{{ __('account-exists.forgot') }}</x-email.text>

    <x-email.button :href="$url">{{ __('account-exists.login') }}</x-email.button>

    <x-email.text variant="muted">{{ __('account-exists.ignore') }}</x-email.text>
@endsection

@section('footer', __('account-exists.footer'))
