<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'subject' => 'You already have a ' . $app . ' account',
    'hello' => 'Hello',
    'message' => 'Someone tried to create a ' . $app . ' account with this email address, but you already have one.',
    'forgot' => 'Forgot your password? You can request a new one on the login page.',
    'login' => 'Log in',
    'ignore' => 'Wasn\'t this you? Then you don\'t need to do anything, nothing has changed on your account.',
    'footer' => 'You receive this email because this address belongs to a ' . $app . ' account.',
];
