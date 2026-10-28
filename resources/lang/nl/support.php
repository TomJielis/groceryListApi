<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'reply_title' => 'Antwoord op je vraag',
    'reply_subject' => 'Antwoord op je vraag: :subject',
    'reply_intro' => 'Hoi :name, we hebben je vraag ":subject" beantwoord:',
    'no_reply' => 'Antwoorden op deze e-mail worden niet gelezen.',
    'app_hint' => 'Open de app en ga naar Profiel → Hulp & contact om het gesprek te lezen en te reageren.',
    'admin_new_title' => 'Nieuwe supportvraag',
    'admin_message_title' => 'Nieuwe reactie op een supportvraag',
    'admin_intro' => ':name (:email) schreef over ":subject":',
    'admin_hint' => 'Antwoord in de app: Profiel → Beheer → Support. Je krijgt deze e-mail omdat geen beheerder per push bereikbaar was; een antwoord op deze e-mail komt niet bij de vraagsteller aan.',
    'team' => '– Het ' . $app . ' team',
    'footer' => 'Je ontvangt deze e-mail omdat je een vraag hebt gesteld aan ' . $app . '.',
];
