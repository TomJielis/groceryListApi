<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'reply_title' => 'Reply to your question',
    'reply_subject' => 'Reply to your question: :subject',
    'reply_intro' => 'Hello :name, we have answered your question ":subject":',
    'no_reply' => 'Replies to this email are not read.',
    'app_hint' => 'Open the app and go to Profile → Help & contact to read the conversation and reply.',
    'admin_new_title' => 'New support question',
    'admin_message_title' => 'New reply on a support question',
    'admin_intro' => ':name (:email) wrote about ":subject":',
    'admin_hint' => 'Answer in the app: Profile → Admin → Support. You get this email because no admin could be reached by push; replying to it does not reach the person who asked.',
    'team' => '– The ' . $app . ' Team',
    'footer' => 'You are receiving this email because you asked a question to ' . $app . '.',
];
