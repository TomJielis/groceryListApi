<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'title' => 'Uitnodiging voor boodschappenlijst',
    'grocery_list' => $app,
    'hello' => 'Hello',
    'reset_message_1' => 'You have requested to reset the password for your ' . $app . ' account.',
    'button_reset_password_message' => 'Click the button below to set a new password:',
    'reset_password' => 'Reset Password',
    'ignore_message' => 'If you did not make this request, you can safely ignore this email.',
    'footer' => 'You receive this email because you have an account on www.Tomjielis.com.'
];

