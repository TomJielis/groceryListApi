<?php
return [
    // Before the last of a few named products: "Melk, Brood and Kaas"
    'and' => 'and',
    'invite_accepted' => [
        'title' => 'Invite accepted',
        'body' => ':user has joined your grocery list ":list".',
    ],
    'list_removed' => [
        'title' => 'Removed from list',
        'body' => ':user has removed you from the grocery list ":list".',
    ],
    'list_deleted' => [
        'title' => 'List deleted',
        'body' => ':user has deleted the grocery list ":list".',
    ],
    'cookbook_invite_accepted' => [
        'title' => 'Invite accepted',
        'body' => ':user has joined your cookbook ":cookbook".',
    ],
    'cookbook_removed' => [
        'title' => 'Removed from cookbook',
        'body' => ':user removed you from the cookbook ":cookbook".',
    ],
    'cookbook_deleted' => [
        'title' => 'Cookbook deleted',
        'body' => ':user deleted the cookbook ":cookbook".',
    ],
    'items_added' => [
        'title' => 'New on ":list"',
        'body' => '{1} :user added 1 item.|[2,*] :user added :count items.',
        // 1 to 3 products, by name: "2× Melk, Brood en Kaas"
        'body_named' => ':user added :products.',
    ],
    'items_checked' => [
        'title' => 'Checked off on ":list"',
        'body' => '{1} :user checked off 1 item.|[2,*] :user checked off :count items.',
    ],
    'list_completed' => [
        'title' => 'Shopping done',
        'body' => ':user checked off everything on ":list".',
    ],
    'support_reply' => [
        'title' => 'Reply to your question',
        'body' => '":subject" has been answered.',
    ],
    'support_new' => [
        'title' => 'New support question',
        'body' => ':name: :subject',
    ],
    'support_message' => [
        'title' => 'New reply on a support question',
        'body' => ':name: :subject',
    ],
];
