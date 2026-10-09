# Worker, Queue, dan Scheduler

## Service yang harus hidup

Docker Compose menjalankan lima service aplikasi:

| Service | Tanggung jawab |
| --- | --- |
| `web` | Nginx yang melayani HTTP dan meneruskan PHP ke `app`. |
| `app` | PHP-FPM Laravel untuk request web/API. |
| `queue` | `php artisan queue:work database --sleep=3 --tries=1 --timeout=600 --memory=90%`. Menjalankan task impor dan ekspor secara serial. |
| `scheduler` | `php artisan schedule:work`, menjalankan pemeliharaan terjadwal. |

`queue` dan `scheduler` bukan service opsional. Upload dan export memang dapat diterima oleh web, tetapi tidak akan selesai tanpa worker queue. File impor dan ekspor memakai Docker volume terpisah yang dimount pada `app` dan `queue`, sehingga kedua service melihat path private yang sama.

## Task impor

| State | Makna | Ditulis oleh |
| --- | --- | --- |
| `queued` | File sudah disimpan dan menunggu worker. | Controller upload |
| `processing` | Worker sedang memvalidasi atau menyimpan versi. | `ProcessKompenResponHubImport` |
| `completed` | Versi workbook telah tersimpan dan dapat dipilih di List File. | Worker |
| `failed` | File/validasi/server gagal; alasan tersedia pada task. | Worker atau reconciler |

`ProcessKompenResponHubImport` memiliki satu percobaan dan timeout 600 detik. Ia memvalidasi archive XLSX sebelum parser membaca workbook, agar ZIP bomb, path tidak aman, struktur Office XML tidak lengkap, dan formula pada cell dapat ditolak lebih awal.

Task gagal menghapus file task-nya dari storage private. Versi yang telah selesai tidak otomatis aktif; admin memilihnya di **List File**.

## Task ekspor

| State | Makna | Aksi pengguna |
| --- | --- | --- |
| `queued` | Permintaan masuk antrean. | Menunggu atau batalkan. |
| `processing` | Worker sedang mengambil data dan membangun file. | Menunggu atau batalkan. |
| `completed` | File tersedia sampai waktu retensi berakhir. | Download atau tutup notifikasi. |
| `failed` | Worker/validasi gagal. | Baca pesan, buat ulang dengan filter yang tepat. |
| `cancelled` | Dibatalkan sebelum selesai. | Buat ekspor baru bila diperlukan. |

`GenerateKompenResponHubExport` memakai `WithoutOverlapping('sikompen:exports')`. Akibatnya hanya satu ekspor aktif di satu waktu; permintaan lain tetap `queued`. Ini disengaja agar penggunaan RAM untuk PhpSpreadsheet/Dompdf tidak saling berebut.

Task menggunakan dua bukti akses ketika polling atau download:

1. `access_token` acak 32-byte yang disimpan bersama task.
2. session ID browser yang membuat task.

Keduanya harus cocok. Worker mencatat kegagalan teknis ke log server dan menampilkan pesan aman pada UI tanpa membocorkan detail internal.

## Jadwal pemeliharaan

| Command | Jadwal | Dampak |
| --- | --- | --- |
| `sikompen:reconcile-tasks` | setiap 5 menit | Menandai task impor/ekspor queued/processing yang melampaui ambang waktu sebagai gagal, lalu membersihkan file sementara yang relevan. |
| `sikompen:purge-exports` | setiap jam | Menghapus file dan record ekspor yang sudah melewati retensi. |
| `sikompen:purge-import-tasks` | setiap hari | Menghapus record task impor completed/failed yang sudah lewat retensi. |
| `sikompen:purge-activity-logs` | setiap hari | Menghapus aktivitas lebih lama daripada kebijakan retensi admin. |

Seluruh command schedule memakai `withoutOverlapping()`. Ambang task macet minimal 15 menit dan dapat diatur oleh `SIKOMPEN_STALLED_TASK_MINUTES`.

## Triage operasional

| Gejala | Pemeriksaan pertama | Tindakan aman |
| --- | --- | --- |
| Upload/ekspor berhenti di antrean | `docker compose ps`, lalu `docker compose logs queue --tail=100` | Pastikan `queue` hidup; jangan menghapus task terlebih dahulu. |
| Task gagal | Buka pesan error di panel task dan log `queue` | Perbaiki workbook/filter, lalu buat task baru. |
| Status tidak berubah lebih dari ambang | Periksa `scheduler` dan log-nya | Reconciler akan menandai gagal; setelah itu task boleh dibuat ulang. |
| File ekspor hilang | Periksa waktu retensi | Buat ekspor baru; file kedaluwarsa memang dihapus. |
| Aktivasi versi ditolak | Periksa masih ada task upload aktif | Tunggu task selesai/gagal, lalu coba lagi. |

Untuk command dan volume lengkap, lihat [deployment-guide.md](deployment-guide.md).
