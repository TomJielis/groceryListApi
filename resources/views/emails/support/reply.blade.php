@extends('emails.layout')

@section('title', __('support.reply_title'))
@section('header-title', __('support.reply_title'))

@section('content')
    <p>{{ __('support.reply_intro', ['name' => $ticket->name, 'subject' => $ticket->subject]) }}</p>
    <p style="white-space: pre-line; border-left: 3px solid #5ebd8a; padding-left: 12px;">{{ $body }}</p>
    <p style="text-align: center; margin-top: 24px;"><strong>{{ __('support.app_hint') }}</strong></p>
    <p class="muted" style="text-align: center; font-size: 14px;">{{ __('support.no_reply') }}</p>
    <p style="text-align: center; margin-top: 32px; color: #64748b; font-size: 14px;">{{ __('support.team') }}</p>
@endsection

@section('footer', __('support.footer'))
