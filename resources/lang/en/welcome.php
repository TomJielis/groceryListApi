<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'title' => $app,
    'header_logo_alt' => $app . ' Logo',
    'header_title' => $app,
    'greeting' => 'Hello :name 👋,',
    'welcome' => 'Welcome to ' . $app . '!',
    'activate_account' => 'Activate your account',
    'intro' => 'With your new account, you can quickly and easily manage your grocery lists, wherever you are.',
    'share' => 'Share lists easily with friends or family, collaborate in real-time and never forget an item again.',
    'start' => 'Start organizing today and enjoy a smarter, more convenient way of shopping!',
    'tips_title' => 'Tips to get started:',
    'footer' => 'You receive this email because you created an account on www.Tomjielis.com.',
    'tips_for_usage' => 'Tips for usage',
    'tip_1' => 'Set your main list as favorite on the lists page, so you have direct access to it from the dashboard.',
    'tip_2' => 'Share your list with friends and collaborate within the app. You can set this from your lists page.',
    'tip_3' => 'Use it on mobile and desktop',
    'download_title' => 'Get the app',
    'download_text' => $app . ' is available for iPhone and Android. Download it to always have your lists with you.',
    'download_iphone' => 'Download for iPhone',
    'download_android' => 'Download for Android',

    'wish_message' => 'We wish you a smooth and pleasant shopping experience',
    'team' => ' – The ' . $app . ' Team'

];

