<?php

return [
    // Where a new question or reply is announced by email (admins also get a push). Use a personal address: answering
    // happens in the admin of the app or the website, not by replying to this mail.
    'notify_email' => env('SUPPORT_NOTIFY_EMAIL', env('MAIL_FROM_ADDRESS')),
];
