@extends('emails.layout')

@section('title', __('support.reply_title'))
@section('header-title', __('support.reply_title'))

@section('content')
    <p>{{ __('support.reply_intro', ['name' => $ticket->name, 'subject' => $ticket->subject]) }}</p>
    <p style="white-space: pre-line; border-left: 3px solid #5ebd8a; padding-left: 12px;">{{ $body }}</p>
    <p style="text-align: center; margin-top: 24px;">
        <a href="{{ $ticket->publicUrl() }}" class="cta-button">{{ __('support.reply_button') }}</a>
    </p>
    <p class="muted" style="text-align: center; margin-top: 16px; font-size: 14px;"><strong>{{ __('support.no_reply') }}</strong></p>
    <p class="muted" style="text-align: center; font-size: 14px;">{{ __('support.app_hint') }}</p>
    <p style="text-align: center; margin-top: 32px; color: #64748b; font-size: 14px;">{{ __('support.team') }}</p>
@endsection

@section('footer', __('support.footer'))
