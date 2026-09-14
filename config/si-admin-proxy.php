<?php

$enabled = env('SI_ADMIN_PROXY_ENABLED', false);

return [
    'enabled' => $enabled,

    'shared_secret' => env('SI_ADMIN_PROXY_SHARED_SECRET'),

    'signature_ttl_seconds' => (int) env('SI_ADMIN_PROXY_SIGNATURE_TTL_SECONDS', 60),

    'session_max_age_seconds' => (int) env('SI_ADMIN_PROXY_SESSION_MAX_AGE_SECONDS', 7200),

    'public_path' => $enabled
        ? '/'.trim((string) env('SI_ADMIN_PROXY_PUBLIC_PATH', 'sikompen'), '/')
        : '',

    'allowed_roles' => ['admin', 'mahasiswa', 'superuser'],

    'admin_roles' => ['admin', 'superuser'],
];
