@extends('emails.layout')

@section('title', __('welcome.welcome'))
@section('header-title', __('welcome.welcome'))

@section('content')
    <x-email.heading>{{ __('welcome.greeting', ['name' => $user->name]) }}</x-email.heading>
    <x-email.text>{{ __('welcome.intro') }}</x-email.text>
    <x-email.text>{{ __('welcome.share') }}</x-email.text>
    <x-email.text>{{ __('welcome.start') }}</x-email.text>

    <x-email.button :href="$url">{{ __('welcome.activate_account') }}</x-email.button>

    <x-email.panel>
        <x-email.label>{{ __('welcome.tips_for_usage') }}</x-email.label>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            @foreach (['✅' => 'tip_1', '👥' => 'tip_2', '📱' => 'tip_3'] as $icon => $tip)
                <tr>
                    <td width="28" valign="top" style="width: 28px; font-size: 15px; line-height: 22px;">{{ $icon }}</td>
                    <td valign="top"><x-email.text variant="small">{{ __('welcome.' . $tip) }}</x-email.text></td>
                </tr>
            @endforeach
        </table>
    </x-email.panel>

    <div style="height: 16px; font-size: 0; line-height: 0;">&nbsp;</div>

    <x-email.heading>{{ __('welcome.add_app_to_start_screen') }}</x-email.heading>

    @foreach (['safari', 'chrome'] as $browser)
        <x-email.panel>
            <x-email.label>{{ rtrim(trim(__('welcome.instruction_' . $browser)), ':') }}</x-email.label>
            @foreach (range(1, 4) as $step)
                <x-email.text variant="small">{{ __('welcome.instruction_' . $browser . '_' . $step) }}</x-email.text>
            @endforeach
        </x-email.panel>
    @endforeach

    <div style="height: 4px; font-size: 0; line-height: 0;">&nbsp;</div>

    <x-email.text>{{ __('welcome.instruction_chrome_5') }}</x-email.text>
    <x-email.text variant="muted">{{ __('welcome.wish_message') }}<br>{{ trim(__('welcome.team')) }}</x-email.text>
@endsection

@section('footer', __('welcome.footer'))
