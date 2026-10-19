# File Framework dan Konfigurasi Sikompen

| File | Peran |
| --- | --- |
| `bootstrap/app.php` | Alias middleware, cookie exception, dan redirect guard. |
| `bootstrap/providers.php` | Pendaftaran provider aplikasi. |
| `app/Providers/AppServiceProvider.php` | Rate limiter dan konfigurasi aplikasi saat boot. |
| `config/auth.php` | Guard/provider admin dan mahasiswa. |
| `config/kompen-respon-hub.php` | Koneksi domain, import, queue, retention, API sync, dan emergency admin. |
| `config/database.php`, `queue.php`, `cache.php`, `session.php` | Koneksi `kompen_db`, database queue/cache, dan session aplikasi. |
| `routes/web.php`, `routes/api.php`, `routes/console.php` | Surface browser, API, dan schedule. |
| `docker-compose.yml` | Stack lokal sembilan service dan volume private. |
| `Dockerfile`, `docker-entrypoint.sh`, `nginx*.conf` | Image aplikasi/web, bootstrap runtime, dan Nginx. |
| `vite.config.ts`, `tsconfig.json`, `components.json` | Vite, TypeScript, Tailwind/UI tooling, dan Wayfinder. |

## Environment domain penting

| Kelompok | Variabel |
| --- | --- |
| Data | `KOMPEN_RESPON_HUB_DB_CONNECTION`, `KOMPEN_DB_*`, `SESSION_*`, `DB_QUEUE_CONNECTION`. |
| Import/export | `SIKOMPEN_IMPORT_*`, `SIKOMPEN_EXPORT_MAX_ROWS`, `SIKOMPEN_EXPORT_RETENTION_HOURS`, `SIKOMPEN_IMPORT_TASK_RETENTION_DAYS`. |
| Worker | `SIKOMPEN_IMPORT_QUEUE`, `SIKOMPEN_EXPORT_QUEUE`, `SIKOMPEN_API_SYNC_QUEUE`, `SIKOMPEN_STALLED_TASK_MINUTES`, `SIKOMPEN_QUEUED_TASK_MINUTES`. |
| API sync | `SIKOMPEN_API_SYNC_ARTIFACT_RETENTION_HOURS`, `SIKOMPEN_API_SYNC_RUN_RETENTION_DAYS`. |
| API mini app | `SIKOMPEN_API_BASE_URL`, `SIKOMPEN_API_CLIENT_ID`, `SIKOMPEN_API_BEARER_TOKEN`, `API_MINI_APP_PORT`, serta bind/IP/network override opsional Compose. |
| Darurat | `SIKOMPEN_EMERGENCY_ADMIN_1_*`, `SIKOMPEN_EMERGENCY_ADMIN_2_*`. |
| Cache | `DB_CACHE_CONNECTION`, `DB_CACHE_TABLE`, `DB_CACHE_LOCK_CONNECTION`, `DB_CACHE_LOCK_TABLE`, `CACHE_PREFIX`. |

Salin `.env.example` untuk nilai awal lokal. Jangan memasukkan credential, token, atau password aktual ke dokumentasi maupun commit.

## Route TypeScript generated

Wayfinder menghasilkan `resources/js/actions/` dan `resources/js/routes/`. Keduanya bukan source manual. Setelah route atau signature controller berubah, jalankan:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```
