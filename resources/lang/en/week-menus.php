<?php
// Week menus (WeekMenuController)
return [
    'default_name' => 'Week menu',
    'left' => 'You left this week menu.',
    'deleted' => 'The week menu has been deleted.',
    'only_owner' => 'Only the creator of the week menu can do this.',
    'owner_cannot_leave' => "As its creator you can't leave your own week menu. Delete the week menu if you no longer want it.",
    'range_too_long' => 'Ask for two months at most at a time.',
    'no_recipe_access' => 'You can only plan recipes from your own recipe book.',
    'entry_deleted' => 'The dish has been taken out of the week menu.',
    // Validation (WeekMenuRequest): the rules recipes has no message for, and the field names
    'validation' => [
        'rules' => [
            'date_format' => ':Attribute must be a date (yyyy-mm-dd).',
            'after_or_equal' => ':Attribute is invalid.',
            'before' => ':Attribute is invalid.',
        ],
        'attributes' => [
            'name' => 'the name',
            'from' => 'the start date',
            'to' => 'the end date',
            'date' => 'the date',
            'recipe_id' => 'the recipe',
            'servings' => 'the number of servings',
            'reset_added' => 'put on the list again',
            'entry_ids' => 'the dishes',
            'entry_ids.*' => 'a dish',
            'list_id' => 'the list',
            'undo' => 'undo',
        ],
    ],
];
