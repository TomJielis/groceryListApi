<?php

return [
       'paths' => ['api/*', 'sanctum/csrf-cookie'],
       'allowed_methods' => ['*'],
       'allowed_origins' => ['http://localhost:3000', 'http://localhost:8081'],
       'allowed_headers' => ['*'],
       'supports_credentials' => true,
];
