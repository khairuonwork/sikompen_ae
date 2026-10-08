<?php

return [
    /*
     * Data aplikasi ini tinggal di koneksi yang sama dengan Sikompen utama,
     * tetapi semua tabelnya memakai awalan kompen_respon_hub_.
     */
    'database_connection' => env('KOMPEN_RESPON_HUB_DB_CONNECTION', 'kompen_db'),

    'import' => [
        'max_upload_kilobytes' => (int) env('SIKOMPEN_IMPORT_MAX_UPLOAD_KILOBYTES', 5120),
        'max_archive_entries' => (int) env('SIKOMPEN_IMPORT_MAX_ARCHIVE_ENTRIES', 64),
        'max_archive_uncompressed_bytes' => (int) env('SIKOMPEN_IMPORT_MAX_ARCHIVE_UNCOMPRESSED_BYTES', 8 * 1024 * 1024),
        'max_archive_entry_bytes' => (int) env('SIKOMPEN_IMPORT_MAX_ARCHIVE_ENTRY_BYTES', 4 * 1024 * 1024),
        'max_archive_compression_ratio' => (int) env('SIKOMPEN_IMPORT_MAX_ARCHIVE_COMPRESSION_RATIO', 100),
    ],

    'export_max_rows' => (int) env('SIKOMPEN_EXPORT_MAX_ROWS', 5000),

    'retention' => [
        'export_hours' => (int) env('SIKOMPEN_EXPORT_RETENTION_HOURS', 24),
        'import_task_days' => (int) env('SIKOMPEN_IMPORT_TASK_RETENTION_DAYS', 30),
    ],

    'queue' => [
        'stalled_task_minutes' => (int) env('SIKOMPEN_STALLED_TASK_MINUTES', 15),
    ],
];
