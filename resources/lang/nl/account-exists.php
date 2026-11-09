<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'subject' => 'Je hebt al een ' . $app . '-account',
    'hello' => 'Hallo',
    'message' => 'Iemand probeerde een ' . $app . '-account aan te maken met dit e-mailadres, maar je hebt al een account.',
    'forgot' => 'Wachtwoord vergeten? Op de inlogpagina kun je een nieuw wachtwoord aanvragen.',
    'login' => 'Inloggen',
    'ignore' => 'Was jij dit niet? Dan hoef je niets te doen, er is niets aan je account veranderd.',
    'footer' => 'Je ontvangt deze e-mail omdat dit adres bij een ' . $app . '-account hoort.',
];
