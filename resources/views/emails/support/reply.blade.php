@extends('emails.layout')

@section('title', __('support.reply_title'))
@section('header-title', __('support.reply_title'))

@section('content')
    <x-email.text>{{ __('support.reply_intro', ['name' => $ticket->name, 'subject' => $ticket->subject]) }}</x-email.text>
    <x-email.quote>{{ $body }}</x-email.quote>
    <x-email.text><strong>{{ __('support.app_hint') }}</strong></x-email.text>
    <x-email.text variant="muted">{{ __('support.no_reply') }}<br>{{ __('support.team') }}</x-email.text>
@endsection

@section('footer', __('support.footer'))
