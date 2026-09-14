<?php

return [
    'enabled' => env('SI_ADMIN_PROXY_ENABLED', false),

    'shared_secret' => env('SI_ADMIN_PROXY_SHARED_SECRET'),

    'signature_ttl_seconds' => (int) env('SI_ADMIN_PROXY_SIGNATURE_TTL_SECONDS', 60),

    'allowed_roles' => ['admin', 'mahasiswa', 'superuser'],

    'admin_roles' => ['admin', 'superuser'],
];
