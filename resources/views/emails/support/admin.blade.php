@extends('emails.layout')

@section('title', $isNew ? __('support.admin_new_title') : __('support.admin_message_title'))
@section('header-title', $isNew ? __('support.admin_new_title') : __('support.admin_message_title'))

@section('content')
    <x-email.text>{{ __('support.admin_intro', ['name' => $ticket->name, 'email' => $ticket->email, 'subject' => $ticket->subject]) }}</x-email.text>
    <x-email.quote>{{ $body }}</x-email.quote>
    <x-email.text variant="muted">{{ __('support.admin_hint') }}</x-email.text>
@endsection

@section('footer', '#' . $ticket->id)
