@extends('emails.layout')

@section('title', $isNew ? __('support.admin_new_title') : __('support.admin_message_title'))
@section('header-title', $isNew ? __('support.admin_new_title') : __('support.admin_message_title'))

@section('content')
    <p>{{ __('support.admin_intro', ['name' => $ticket->name, 'email' => $ticket->email, 'subject' => $ticket->subject]) }}</p>
    <p style="white-space: pre-line; border-left: 3px solid #cbd5e1; padding-left: 12px; color: #475569;">{{ $body }}</p>
    <p class="muted" style="text-align: center; margin-top: 16px; font-size: 14px;">{{ __('support.admin_hint') }}</p>
@endsection

@section('footer', '#' . $ticket->id)
