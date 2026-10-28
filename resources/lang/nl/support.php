<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'reply_title' => 'Antwoord op je vraag',
    'reply_subject' => 'Antwoord op je vraag: :subject',
    'reply_intro' => 'Hoi :name, we hebben je vraag ":subject" beantwoord:',
    'reply_button' => 'Reageren op je vraag',
    'no_reply' => 'Antwoorden op deze e-mail worden niet gelezen. Gebruik de knop hierboven om te reageren, dan komt je reactie bij je vraag.',
    'app_hint' => 'Heb je de app, dan vind je het gesprek ook onder Profiel → Hulp & contact.',
    'admin_new_title' => 'Nieuwe supportvraag',
    'admin_message_title' => 'Nieuwe reactie op een supportvraag',
    'admin_intro' => ':name (:email) schreef over ":subject":',
    'admin_button' => 'Openen in de admin',
    'admin_hint' => 'Antwoord in de admin van de app of de website; een antwoord op deze e-mail komt niet bij de vraagsteller aan.',
    'team' => '– Het ' . $app . ' team',
    'footer' => 'Je ontvangt deze e-mail omdat je een vraag hebt gesteld aan ' . $app . '.',
];
