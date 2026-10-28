<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'reply_title' => 'Reply to your question',
    'reply_subject' => 'Reply to your question: :subject',
    'reply_intro' => 'Hello :name, we have answered your question ":subject":',
    'reply_button' => 'Reply to your question',
    'no_reply' => 'Replies to this email are not read. Use the button above to reply, so your reply ends up with your question.',
    'app_hint' => 'If you have the app, you will also find the conversation under Profile → Help & contact.',
    'admin_new_title' => 'New support question',
    'admin_message_title' => 'New reply on a support question',
    'admin_intro' => ':name (:email) wrote about ":subject":',
    'admin_button' => 'Open in the admin',
    'admin_hint' => 'Answer in the admin of the app or the website; replying to this email does not reach the person who asked.',
    'team' => '– The ' . $app . ' Team',
    'footer' => 'You are receiving this email because you asked a question to ' . $app . '.',
];
