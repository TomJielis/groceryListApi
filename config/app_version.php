<?php

return [
    // The oldest app version that still works, per platform (the version in the app's app.json, e.g. 1.1.0). An older
    // app shows a screen that can't be closed, sending the user to update_url. Raise it when a release must not be
    // skipped (a change old apps can't handle); 0.0.0 lets every version through.
    'minimum' => [
        'ios' => env('MIN_APP_VERSION_IOS', '0.0.0'),
        'android' => env('MIN_APP_VERSION_ANDROID', '0.0.0'),
    ],

    // The oldest build that still works, per platform: the build number (iOS CFBundleVersion, the TestFlight build;
    // Android versionCode). For builds of the same version, as while the app is in TestFlight (every build 1.0.0).
    // An app is blocked when it's older on either: version or build. 0 lets every build through.
    'minimum_build' => [
        'ios' => (int) env('MIN_APP_BUILD_IOS', 0),
        'android' => (int) env('MIN_APP_BUILD_ANDROID', 0),
    ],

    // Where that screen sends the user. iOS: the App Store; while the app is in beta, set APP_UPDATE_URL_IOS to the
    // public TestFlight link. Android: the APK on the website.
    'update_url' => [
        'ios' => env('APP_UPDATE_URL_IOS', 'https://apps.apple.com/app/id6824506915'),
        'android' => env('APP_UPDATE_URL_ANDROID', rtrim((string) env('APP_URL', ''), '/') . '/android'),
    ],
];
