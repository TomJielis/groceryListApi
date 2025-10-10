<?php

return [
       'paths' => ['api/*', 'sanctum/csrf-cookie'],
       'allowed_methods' => ['*'],
       'allowed_origins' => ['http://192.168.68.113:3000','http://localhost:3000'],
       'allowed_headers' => ['*'],
       'supports_credentials' => true,
];
