# Menjalankan Sikompen dengan Docker Compose

Panduan ini untuk lingkungan lokal. Gunakan dokumentasi deployment terpisah untuk lingkungan server.

## 1. Prasyarat

- Docker Desktop/Engine dengan Docker Compose v2.
- Git.
- Port lokal `8080`, `8090`, dan `3307` belum dipakai, atau override variabel Compose yang sesuai.

## 2. Clone dan konfigurasi

```bash
git clone <url-repository> sikompen
cd sikompen
git checkout staging # bila ingin menyamakan branch kerja staging
cp .env.example .env
```

Atur paling tidak `APP_KEY` (atau biarkan entrypoint mengelolanya bila konfigurasi lokal mendukung), password database lokal, dan URL yang diperlukan. Jangan commit `.env`.

`APP_BIND_HOST` dan `API_MINI_APP_BIND_HOST` adalah override opsional Compose; keduanya default ke loopback. `API_MINI_APP_PORT` default `8090`.

## 3. Build dan jalankan

```bash
docker compose up -d --build
docker compose ps
```

Stack menyediakan `web`, `app`, `database`, `api-mini-app`, `queue`, `imports`, `exports`, `api-sync`, dan `scheduler`. Network `sikompen_proxy` disiapkan khusus untuk Cloudflare Tunnel dan tidak perlu dipakai saat lokal.

## 4. Siapkan aplikasi

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan storage:link
```

Buka aplikasi pada `http://localhost:8080`. Buat admin reguler pertama melalui setup. Panel pengujian API mini tersedia pada `http://localhost:8090`; endpoint health ada di `/health`.

## 5. Pemeriksaan cepat

```bash
docker compose ps
docker compose logs app --tail=100
docker compose logs imports --tail=100
docker compose logs exports --tail=100
docker compose logs api-sync --tail=100
docker compose exec app php artisan about
```

Untuk menguji API mini, isi `SIKOMPEN_API_BEARER_TOKEN` pada `.env`, lalu daftarkan token serta IP container API mini pada `SIKOMPEN_API_CLIENTS_JSON`. Allowlist default container mini adalah `172.30.0.10/32`.

## 6. Memperbarui kode lokal

```bash
git pull
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

Jika route/controller berubah, generate Wayfinder. Jika asset frontend berubah dan image tidak dibangun ulang, build ulang service aplikasi/web.

## 7. Diagnosis

| Gejala | Periksa |
| --- | --- |
| HTTP 502/500 | `docker compose logs web app --tail=100` dan status database. |
| Impor tertahan | `docker compose logs imports --tail=100`. |
| Ekspor tertahan | `docker compose logs exports --tail=100`. |
| Sync API gagal | `docker compose logs api-sync --tail=100`; periksa token/client/rate limit, serta IP allowlist bila client tersebut menggunakannya. |
| Panel mini tidak siap | Buka `/health`; lengkapi `SIKOMPEN_API_BASE_URL` dan `SIKOMPEN_API_BEARER_TOKEN`. |
| Port bentrok | Ubah `APP_PORT`, `API_MINI_APP_PORT`, atau `KOMPEN_DB_PORT` di `.env`. |

## 8. Menghentikan atau mereset data lokal

```bash
docker compose down
```

Volume database dan storage tetap dipertahankan. Jangan hapus volume bila data lokal masih diperlukan; tindakan itu menghapus database dan file private secara permanen.
