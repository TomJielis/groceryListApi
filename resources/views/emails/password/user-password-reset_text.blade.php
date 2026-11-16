{!! __('password-reset.hello') !!} {!! $user->name !!},

{!! __('password-reset.reset_message_1') !!}
{!! __('password-reset.button_reset_password_message') !!}

{!! $url !!}

{!! trim(__('password-reset.ignore_message')) !!}

--
{!! __('mail.sender', ['app' => config('app.name'), 'site' => preg_replace('/^www\./', '', (string) parse_url(config('app.url'), PHP_URL_HOST)), 'email' => config('support.address')]) !!}
