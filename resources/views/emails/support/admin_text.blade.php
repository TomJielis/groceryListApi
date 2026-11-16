{!! __('support.admin_intro', ['name' => $ticket->name, 'email' => $ticket->email, 'subject' => $ticket->subject]) !!}

{!! $body !!}

{!! __('support.admin_hint') !!}

--
{!! __('mail.sender', ['app' => config('app.name'), 'site' => preg_replace('/^www\./', '', (string) parse_url(config('app.url'), PHP_URL_HOST)), 'email' => config('support.address')]) !!}
