<?php
// release.php

$commands = [
    'composer install',
    'php artisan migrate',
    'php artisan db:seed',
    'php artisan optimize:clear',
    'php artisan route:clear',
];

foreach ($commands as $cmd) {
    echo "Running: $cmd\n";
    exec($cmd . ' 2>&1', $output, $resultCode);
    echo implode("\n", $output) . "\n";
    if ($resultCode !== 0) {
        echo "Error running: $cmd\n";
        exit($resultCode);
    }
    $output = [];
}

echo "Release finished successfully!\n";

