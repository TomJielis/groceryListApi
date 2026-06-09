@extends('emails.layout')

@section('title', __('welcome.title'))
@section('header-title', __('welcome.title'))

@section('content')
    <p>{{ __('welcome.hello') }} {{ $user->name }} 👋</p>
    <p>
        {{ __('welcome.welcome') }}<br>
        {{ __('welcome.intro') }}<br>
        {{ __('welcome.share') }}<br>
        {{ __('welcome.start') }}!
    </p>

    <a href="{{ $url }}" class="cta-button">{{ __('welcome.activate_account') }}</a>

    <div class="tips">
        <h3>💡 {{ __('welcome.tips_for_usage') }}</h3>
        <ul>
            <li>✅ {{ __('welcome.tip_1') }}</li>
            <li>👥 {{ __('welcome.tip_2') }}</li>
            <li>📱 {{ __('welcome.tip_3') }}</li>
        </ul>
    </div>

    <div class="divider"></div>

    <h2 style="text-align: center;">{{ __('welcome.add_app_to_start_screen') }}</h2>

    <h4 style="text-align: center;">{{ __('welcome.instruction_safari') }}</h4>
    <ul style="text-align: center; list-style: none; padding: 0;">
        <li>{{ __('welcome.instruction_safari_1') }}</li>
        <li>{{ __('welcome.instruction_safari_2') }}</li>
        <li>{{ __('welcome.instruction_safari_3') }}</li>
        <li>{{ __('welcome.instruction_safari_4') }}</li>
    </ul>

    <h4 style="text-align: center;">{{ __('welcome.instruction_chrome') }}</h4>
    <ul style="text-align: center; list-style: none; padding: 0;">
        <li>{{ __('welcome.instruction_chrome_1') }}</li>
        <li>{{ __('welcome.instruction_chrome_2') }}</li>
        <li>{{ __('welcome.instruction_chrome_3') }}</li>
        <li>{{ __('welcome.instruction_chrome_4') }}</li>
    </ul>
    <p style="text-align: center; margin-top: 16px;">{{ __('welcome.instruction_chrome_5') }}</p>

    <div class="divider"></div>

    <p style="text-align: center; color: #64748b;">
        {{ __('welcome.wish_message') }}<br>
        {{ __('welcome.team') }}
    </p>
@endsection

@section('footer', __('welcome.footer'))
