<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'title' => 'Uitnodiging voor boodschappenlijst',
    'greeting' => 'Hallo :name,',
    'invited_by' => 'Je bent uitgenodigd door :user om samen de boodschappenlijst ":list" te beheren.',
    'create_account' => 'Maak een account aan via onderstaande knop om toegang te krijgen tot de lijst:',
    'create_account_button' => 'Account aanmaken',
    'already_account' => 'Heb je al een account? Log dan in met je bestaande gegevens.',
    'enjoy' => 'Veel plezier met het samenstellen van je boodschappenlijst!',
    'team' => '– Het ' . $app . ' Team',
    'footer' => 'Je ontvangt deze e-mail omdat je bent uitgenodigd voor een boodschappenlijst op www.Tomjielis.com.'
];

