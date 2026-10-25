<?php

// The app's name, set with APP_NAME in .env
$app = config('app.name');

return [
    'title' => $app,
    'header_logo_alt' => $app . ' Logo',
    'header_title' => $app,
    'greeting' => 'Hello :name 👋,',
    'welcome' => 'Welcome to ' . $app . '!',
    'activate_account' => 'Activate your account:',
    'intro' => 'With your new account, you can quickly and easily manage your grocery lists, wherever you are.',
    'share' => 'Share lists easily with friends or family, collaborate in real-time and never forget an item again.',
    'start' => 'Start organizing today and enjoy a smarter, more convenient way of shopping!',
    'tips_title' => 'Tips to get started:',
    'footer' => 'You receive this email because you created an account on www.Tomjielis.com.',
    'tips_for_usage' => 'Tips for usage',
    'tip_1' => 'Set your main list as favorite on the lists page, so you have direct access to it from the dashboard.',
    'tip_2' => 'Share your list with friends and collaborate within the app. You can set this from your lists page.',
    'tip_3' => 'Use it on mobile and desktop',
    'add_app_to_start_screen' => 'Add ' . $app . ' to your Start Screen',
    'instruction_safari' => 'Instructions for iPhone (Safari):',
    'instruction_safari_1' => '1. Open the app in Safari.',
    'instruction_safari_2' => '2. Tap the Share icon (square with arrow, at the bottom).',
    'instruction_safari_3' => '3. Scroll down and tap "Add to Home Screen".',
    'instruction_safari_4' => '4. Confirm the name and tap "Add".',

    'instruction_chrome' => '  Instructions for Android (Chrome or other browsers):',
    'instruction_chrome_1' => '1. Open the app in your browser.',
    'instruction_chrome_2' => '2. Tap the three dots (⋮) in the top right corner.',
    'instruction_chrome_3' => '3. Select "Add to home screen".',
    'instruction_chrome_4' => '4. Confirm the name and tap "Add".',
    'instruction_chrome_5' => 'You\'ll now find ' . $app . ' on your home screen — with its own icon!',


    'wish_message' => 'We wish you a smooth and pleasant shopping experience',
    'team' => ' – The ' . $app . ' Team'


];

