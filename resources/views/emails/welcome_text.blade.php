{!! __('welcome.greeting', ['name' => $user->name]) !!}

{!! __('welcome.welcome') !!}

{!! __('welcome.intro') !!}

{!! __('welcome.share') !!}

{!! __('welcome.start') !!}

{!! rtrim(__('welcome.activate_account'), ':') !!}: {!! $url !!}

{!! __('welcome.download_title') !!}
{!! __('welcome.download_text') !!}
{!! __('welcome.download_iphone') !!}: {!! config('app.url') !!}/information
{!! __('welcome.download_android') !!}: {!! config('app.url') !!}/android

{!! __('welcome.tips_for_usage') !!}
- {!! __('welcome.tip_1') !!}
- {!! __('welcome.tip_2') !!}
- {!! __('welcome.tip_3') !!}

{!! __('welcome.wish_message') !!}
{!! trim(__('welcome.team')) !!}
