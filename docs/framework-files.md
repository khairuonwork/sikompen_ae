# File Framework dan Konfigurasi Sikompen

| File | Peran di Sikompen |
| --- | --- |
| `config/kompen-respon-hub.php` | Koneksi domain, limit upload/archive XLSX, batas XLSX export, retensi task, dan ambang task macet. |
| `config/si-admin-proxy.php` | Mode proxy Si-Admin, shared secret HMAC, TTL, role, serta public path. |
| `config/database.php` | Koneksi MariaDB `kompen_db` untuk data Sikompen. |
| `config/auth.php` | Guard session `admin` dengan provider `KompenResponHubAdmin`; tidak memakai user default. |
| `config/session.php` | Session database `sikompen_sessions`, encryption, cookie, dan SameSite. |
| `config/queue.php` | Database queue pada `kompen_db`; timeout/retry mendukung job hingga 10 menit. |
| `config/filesystems.php` | Disk local private untuk import/export; tidak ada file XLSX di database/public disk. |
| `app/Providers/AppServiceProvider.php` | Password policy produksi, Carbon immutable, guard command destruktif, serta limiter. |
| `bootstrap/app.php` | Route registration, middleware Inertia, alias middleware Sikompen/proxy, serta error response API. |
| `routes/web.php` | Semua surface browser dan mutasi admin. |
| `routes/api.php` | Data API filter/mahasiswa/detail yang dilindungi context session dan limiter. |
| `routes/console.php` | Jadwal reconciling task dan retensi. |
| `resources/views/app.blade.php` | Shell Blade Inertia/Vite. |
| `resources/views/exports/kompen-respon-hub.blade.php` | Layout PDF landscape untuk export. |
| `vite.config.ts` | Vite + React + Inertia + Tailwind + Wayfinder. |
| `Dockerfile` | Multi-stage Node/PHP-FPM/Nginx; asset production dan extension PHP. |
| `docker-compose.yml` | `web`, `app`, `queue`, `scheduler`, `database`, serta volume database/import/export. |
| `nginx.conf` | Entry public Nginx, FastCGI PHP, batas request dan timeout. |
| `docker-entrypoint.sh` | Membuat/chown storage logs, imports, dan exports agar web/worker konsisten. |

## Environment penting

Nilai rahasia hanya ada di `.env`, tidak di Git.

| Variable | Fungsi |
| --- | --- |
| `APP_ENV=production`, `APP_DEBUG=false` | Perilaku dan error handling produksi. |
| `APP_KEY` | Kunci enkripsi Laravel; unik dan stabil per deployment. |
| `APP_URL`, `DOCKER_APP_URL`, `APP_PORT` | URL/base URL serta binding port aplikasi. |
| `KOMPEN_RESPON_HUB_DB_CONNECTION=kompen_db` | Koneksi yang digunakan semua model domain. |
| `KOMPEN_DB_*` | Host, port, database, user, password MariaDB; Compose memakai host `database`. |
| `SESSION_*` | Driver, koneksi, cookie, encryption, secure cookie, SameSite, dan path session. |
| `QUEUE_CONNECTION`, `DB_QUEUE_CONNECTION`, `DB_QUEUE_RETRY_AFTER` | Queue import/export pada database. |
| `SIKOMPEN_IMPORT_*` | Batas file/archive workbook. |
| `SIKOMPEN_EXPORT_MAX_ROWS`, `SIKOMPEN_EXPORT_RETENTION_HOURS` | Batas XLSX dan usia hasil export. |
| `SIKOMPEN_IMPORT_TASK_RETENTION_DAYS`, `SIKOMPEN_STALLED_TASK_MINUTES` | Retensi task dan deteksi task macet. |
| `SI_ADMIN_PROXY_*` | Mengaktifkan proxy HMAC, secret, TTL, session max age, dan public path. |

## Route generated untuk frontend

Setiap perubahan route/controller yang dipakai React memerlukan:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

Output di `resources/js/actions/` serta `resources/js/routes/` harus di-commit bersama perubahan route, tetapi tidak diedit manual.
