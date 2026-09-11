# Framework files changed for Sikompen

Berikut file framework/konfigurasi yang memiliki peran langsung terhadap Sikompen. File ini penting saat memindahkan aplikasi, meninjau security, atau mengubah integrasi.

| File | Perubahan atau fungsi untuk Sikompen |
| --- | --- |
| `config/kompen-respon-hub.php` | Menentukan nama koneksi domain, default `kompen_db`. Semua model domain membacanya. |
| `config/database.php` | Menambahkan koneksi MySQL `kompen_db` dengan variabel `KOMPEN_DB_*`; strict mode dan UTF-8 MB4 aktif. |
| `config/auth.php` | Mengganti default guard menjadi `admin`, lalu membuat guard session `admin` dengan provider model `KompenResponHubAdmin`. Tidak memakai `users` default. |
| `config/session.php` | Session Laravel diarahkan ke database pada koneksi domain; gunakan `sikompen_sessions` melalui environment saat deployment. |
| `config/queue.php` | Default queue database dan koneksi queue/batches/failed jobs diarahkan ke `kompen_db`; retry window disetel untuk job hingga 10 menit. |
| `config/filesystems.php` | Disk `local` menunjuk `storage/app/private`. Import XLSX berada di sini, bukan di public atau database. |
| `app/Providers/AppServiceProvider.php` | Password kuat untuk produksi, Carbon immutable, larangan destructive command produksi, serta rate limiter login/setup. |
| `bootstrap/app.php` | Memuat route web/API, middleware Inertia/appearance, redirect guest ke login admin, dan JSON error untuk API. |
| `routes/web.php` | Route landing, mahasiswa, login/setup/admin, impor, download template/file, rollback, task status, serta ekspor XLSX/PDF. |
| `routes/api.php` | Empat API data publik dengan throttle 60 request per menit. |
| `resources/views/app.blade.php` | Shell Blade untuk Inertia dan asset Vite. |
| `resources/views/exports/kompen-respon-hub.blade.php` | Blade server-side untuk ekspor PDF, terpisah dari halaman Inertia. |
| `vite.config.ts` | Plugin React, Inertia, Tailwind, Wayfinder, build asset, dan konfigurasi pemeriksaan frontend. |
| `composer.json` | Menambahkan PhpSpreadsheet untuk XLSX dan Dompdf untuk PDF, beserta Inertia/Wayfinder Laravel. |
| `package.json` | Dependensi React/Inertia/Tailwind/Radix/Lucide dan script build/check. |
| `Dockerfile` | Multi-stage build Node + PHP-FPM + Nginx, extension PHP MySQL/GD/ZIP, asset production, serta batas upload/eksekusi 10 menit. |
| `docker-compose.yml` | Menjalankan service `app`, `web`, `queue`, dan `database`, plus volume database dan XLSX private. |
| `nginx.conf` | Nginx public entry point, batas request 25 MB dan 600 detik, FastCGI ke PHP-FPM, serta header security dasar. |
| `docker-entrypoint.sh` | Menetapkan kepemilikan direktori import volume agar PHP-FPM dan queue dapat membaca/menulis aman. |

## Environment penting

Jangan menyimpan secret di Git. Isi nilai sebenarnya pada `.env` di server.

| Variable | Kegunaan |
| --- | --- |
| `APP_ENV=production` | Mengaktifkan perilaku produksi. |
| `APP_DEBUG=false` | Mencegah detail stack trace dibuka ke pengguna. |
| `APP_KEY` | Kunci enkripsi Laravel; harus unik dan tetap stabil pada satu deployment. |
| `APP_URL` / `DOCKER_APP_URL` | URL publik aplikasi. |
| `KOMPEN_RESPON_HUB_DB_CONNECTION=kompen_db` | Memilih koneksi domain. |
| `KOMPEN_DB_HOST`, `KOMPEN_DB_PORT`, `KOMPEN_DB_DATABASE`, `KOMPEN_DB_USERNAME`, `KOMPEN_DB_PASSWORD` | Koneksi data Sikompen. Di Compose host harus `database`. |
| `SESSION_DRIVER=database`, `SESSION_CONNECTION=kompen_db`, `SESSION_TABLE=sikompen_sessions` | Menyimpan session admin pada MariaDB. |
| `QUEUE_CONNECTION=database`, `DB_QUEUE_CONNECTION=kompen_db`, `DB_QUEUE_RETRY_AFTER=660` | Menjalankan task impor pada database queue. |
| `APP_PORT=8080` | Port HTTP host untuk Compose saat ini. |

## Route dan file generate

Setiap perubahan pada `routes/web.php` atau `routes/api.php` yang dipakai React harus diikuti generator Wayfinder:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

File hasilnya di `resources/js/actions/` dan `resources/js/routes/`; file tersebut adalah output generate dan tidak seharusnya diedit manual.
