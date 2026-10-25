<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'title' => 'Uitnodiging voor boodschappenlijst',
    'grocery_list' => $app,
    'hello' => 'Hallo',
    'reset_message_1' => 'Je hebt gevraagd om je wachtwoord voor je ' . $app . '-account te resetten.',
    'button_reset_password_message' => 'Klik op de onderstaande knop om een nieuw wachtwoord in te stellen:',
    'reset_password' => 'Reset Wachtwoord',
    'ignore_message' => ' Als je dit verzoek niet hebt gedaan, kun je deze e-mail veilig negeren.',
    'footer' => 'Je ontvangt deze e-mail omdat je een account hebt op www.Tomjielis.com.'
];

