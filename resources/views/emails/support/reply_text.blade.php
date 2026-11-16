{!! __('support.reply_intro', ['name' => $ticket->name, 'subject' => $ticket->subject]) !!}

{!! $body !!}

{!! __('support.app_hint') !!}
{!! __('support.no_reply') !!}

{!! __('support.team') !!}

--
{!! __('mail.sender', ['app' => config('app.name'), 'site' => preg_replace('/^www\./', '', (string) parse_url(config('app.url'), PHP_URL_HOST)), 'email' => config('support.address')]) !!}
