<?php
// Week menus (WeekMenuController)
return [
    'default_name' => 'Weekmenu',
    'left' => 'Je doet niet meer mee met dit weekmenu.',
    'deleted' => 'Het weekmenu is verwijderd.',
    'only_owner' => 'Alleen de maker van het weekmenu kan dit doen.',
    'owner_cannot_leave' => 'Als maker kun je niet uit je eigen weekmenu stappen. Verwijder het weekmenu als je het niet meer wilt.',
    'range_too_long' => 'Vraag maximaal twee maanden tegelijk op.',
    'no_recipe_access' => 'Je kunt alleen recepten uit je eigen receptenboek inplannen.',
    'entry_deleted' => 'Het gerecht is uit het weekmenu gehaald.',
    // Validation (WeekMenuRequest): the rules recipes has no message for, and the field names
    'validation' => [
        'rules' => [
            'date_format' => ':Attribute moet een datum zijn (jjjj-mm-dd).',
            'after_or_equal' => ':Attribute is ongeldig.',
            'before' => ':Attribute is ongeldig.',
        ],
        'attributes' => [
            'name' => 'de naam',
            'from' => 'de begindatum',
            'to' => 'de einddatum',
            'date' => 'de datum',
            'recipe_id' => 'het recept',
            'servings' => 'het aantal personen',
            'reset_added' => 'opnieuw op de lijst',
            'entry_ids' => 'de gerechten',
            'entry_ids.*' => 'een gerecht',
            'list_id' => 'de lijst',
            'undo' => 'ongedaan maken',
        ],
    ],
];
