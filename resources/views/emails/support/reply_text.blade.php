{!! __('support.reply_intro', ['name' => $ticket->name, 'subject' => $ticket->subject]) !!}

{!! $body !!}

{!! __('support.reply_button') !!}: {!! $ticket->publicUrl() !!}

{!! __('support.no_reply') !!}
{!! __('support.app_hint') !!}

{!! __('support.team') !!}
