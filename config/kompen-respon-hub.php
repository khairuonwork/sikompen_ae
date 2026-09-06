<?php

return [
    /*
     * Data aplikasi ini tinggal di koneksi yang sama dengan Sikompen utama,
     * tetapi semua tabelnya memakai awalan kompen_respon_hub_.
     */
    'database_connection' => env('KOMPEN_RESPON_HUB_DB_CONNECTION', 'kompen_db'),
];
