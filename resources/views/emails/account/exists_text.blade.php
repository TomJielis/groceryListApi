{!! __('account-exists.hello') !!} {!! $user->name !!},

{!! __('account-exists.message') !!}
{!! __('account-exists.forgot') !!}

{!! __('account-exists.login') !!}: {!! $url !!}

{!! __('account-exists.ignore') !!}

--
{!! __('mail.sender', ['app' => config('app.name'), 'site' => preg_replace('/^www\./', '', (string) parse_url(config('app.url'), PHP_URL_HOST)), 'email' => config('support.address')]) !!}
