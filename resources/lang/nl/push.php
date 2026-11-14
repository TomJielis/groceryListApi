<?php
return [
    // Before the last of a few named products: "Melk, Brood en Kaas"
    'and' => 'en',
    'invite_accepted' => [
        'title' => 'Uitnodiging geaccepteerd',
        'body' => ':user doet nu mee met je boodschappenlijst ":list".',
    ],
    'list_removed' => [
        'title' => 'Verwijderd van lijst',
        'body' => ':user heeft je verwijderd van de boodschappenlijst ":list".',
    ],
    'list_deleted' => [
        'title' => 'Lijst verwijderd',
        'body' => ':user heeft de boodschappenlijst ":list" verwijderd.',
    ],
    'cookbook_invite_accepted' => [
        'title' => 'Uitnodiging geaccepteerd',
        'body' => ':user doet nu mee met je kookboek ":cookbook".',
    ],
    'cookbook_removed' => [
        'title' => 'Verwijderd uit kookboek',
        'body' => ':user heeft je verwijderd uit het kookboek ":cookbook".',
    ],
    'cookbook_deleted' => [
        'title' => 'Kookboek verwijderd',
        'body' => ':user heeft het kookboek ":cookbook" verwijderd.',
    ],
    'items_added' => [
        'title' => 'Nieuw op ":list"',
        'body' => '{1} :user heeft 1 product toegevoegd.|[2,*] :user heeft :count producten toegevoegd.',
        // 1 to 3 products, by name: "2× Melk, Brood en Kaas"
        'body_named' => ':user heeft :products toegevoegd.',
    ],
    'items_checked' => [
        'title' => 'Afgevinkt op ":list"',
        'body' => '{1} :user heeft 1 product afgevinkt.|[2,*] :user heeft :count producten afgevinkt.',
    ],
    'list_completed' => [
        'title' => 'Boodschappen gedaan',
        'body' => ':user heeft alles op ":list" afgevinkt.',
    ],
    'support_reply' => [
        'title' => 'Antwoord op je vraag',
        'body' => '":subject" is beantwoord.',
    ],
    'support_new' => [
        'title' => 'Nieuwe supportvraag',
        'body' => ':name: :subject',
    ],
    'support_message' => [
        'title' => 'Nieuwe reactie op een supportvraag',
        'body' => ':name: :subject',
    ],
];
