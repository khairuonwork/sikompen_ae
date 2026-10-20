<?php

return [
    'cidrs' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SIKOMPEN_TRUSTED_PROXY_CIDRS', '172.30.0.0/24')),
    ))),
];
