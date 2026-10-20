<?php

return [
       'paths' => ['api/*', 'sanctum/csrf-cookie'],
       'allowed_methods' => ['*'],
       // Comma-separated, e.g. CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:8081 (Expo web)
       'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
       'allowed_headers' => ['*'],
       'supports_credentials' => true,
];
