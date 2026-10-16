# Menjalankan Sikompen dengan Docker Compose

Panduan ini untuk teman pengembang yang ingin mencoba aplikasi secara lokal setelah melakukan clone repository. Tidak perlu memasang PHP, Composer, Node.js, atau MariaDB di host; semuanya berjalan dalam container.

> Gunakan `.env.example` untuk komputer lokal. Jangan menyalin `.env` dari server dan jangan memakai password produksi.

## 1. Prasyarat

- Git.
- Docker Desktop (macOS/Windows) atau Docker Engine beserta Docker Compose plugin (Linux).
- Port `8080`, `8090`, dan `3307` tidak sedang dipakai aplikasi lain.

Periksa instalasi:

```bash
git --version
docker --version
docker compose version
```

## 2. Clone repository

```bash
git clone https://github.com/khairuonwork/sikompen_ae.git
cd sikompen_ae
git checkout fullstack-login-mahasiswa
git pull --ff-only
```

Jika branch yang dipakai tim berubah, ganti `fullstack-login-mahasiswa` dengan branch yang diberitahukan maintainer.

## 3. Buat konfigurasi lokal

```bash
cp .env.example .env
```

Buat application key lokal:

```bash
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Salin hasilnya ke nilai `APP_KEY=` pada file `.env`.

Untuk percobaan standar, nilai lain dari `.env.example` sudah cocok. Yang penting:

```dotenv
APP_PORT=8080
KOMPEN_DB_PORT=3307
SESSION_SECURE_COOKIE=false
```

Jika Docker network bernama `sikompen_internal` sudah dipakai aplikasi lain, ubah salah satu nilai berikut di `.env` lalu jalankan Compose kembali:

```dotenv
SIKOMPEN_NETWORK_NAME=sikompen_internal_teman
SIKOMPEN_NETWORK_SUBNET=172.31.0.0/24
API_MINI_APP_IP=172.31.0.10
```

## 4. Build dan jalankan

```bash
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --class=Database\\Seeders\\KompenResponHubSystemSettingSeeder --force
docker compose ps
```

Tunggu sampai database berstatus `healthy` dan service lain berstatus `Up`.

| Service | Tugas |
| --- | --- |
| `web` | Nginx yang dapat dibuka dari browser di port `8080`. |
| `app` | Laravel PHP-FPM. |
| `database` | MariaDB lokal, dipublikasikan hanya di `127.0.0.1:3307`. |
| `queue` | Pekerjaan umum aplikasi. |
| `imports` | Worker khusus impor workbook dan roster mahasiswa aktif. |
| `exports` | Worker khusus XLSX/PDF ekspor. |
| `api-sync` | Worker khusus artifact API `NDJSON.GZ`. |
| `scheduler` | Rekonsiliasi task macet dan retensi data. |
| `api-mini-app` | Panel internal untuk menguji API provider; hanya di `127.0.0.1:8090`. |

## 5. Buka aplikasi dan buat admin pertama

Buka:

```text
http://localhost:8080
```

Pada instalasi kosong, buka:

```text
http://localhost:8080/admin/setup
```

Buat akun admin reguler pertama dari halaman tersebut. Tidak ada akun admin atau password default.

Untuk mencoba fitur normal admin, langkah ini sudah cukup. Jangan menjalankan seeder akun darurat kecuali memang ingin menguji alur pemulihan password; seeder tersebut membutuhkan empat nilai `SIKOMPEN_EMERGENCY_ADMIN_*` di `.env`.

## 6. Pemeriksaan cepat

```bash
curl -fsS http://localhost:8080/up
curl -fsS http://127.0.0.1:8090/health
docker compose ps
```

Uji impor dari halaman admin dengan mengunggah workbook contoh. Saat impor/ekspor berjalan, pantau worker:

```bash
docker compose logs -f imports exports queue api-sync scheduler
```

Keluar dari mode log dengan `Ctrl+C`; container tetap berjalan.

## 7. Mengambil pembaruan kode

```bash
git pull --ff-only
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose ps
```

Migration aman dijalankan kembali; Laravel hanya mengeksekusi migration yang belum tercatat.

## 8. Diagnosis masalah umum

### Build gagal saat mengambil image Docker Hub

Ini biasanya gangguan sementara Docker Hub/CloudFront, bukan kesalahan source code.

```bash
docker pull node:22-bookworm-slim
docker pull php:8.4-fpm-bookworm
docker pull composer:2
docker pull nginx:1.27-alpine
docker compose up -d --build
```

Jika masih gagal, tunggu beberapa menit lalu ulangi. Jangan menghapus volume database hanya karena build gagal.

### Browser menampilkan 502 atau 500

```bash
docker compose ps
docker compose logs --tail=150 web app
```

Setelah perubahan kode atau environment, rebuild dan recreate service:

```bash
docker compose up -d --build --force-recreate app web queue imports exports api-sync scheduler
docker compose exec -T app php artisan migrate --force
```

### Impor atau ekspor berhenti

```bash
docker compose logs --tail=200 imports exports queue
docker compose exec -T app php artisan queue:failed
```

Worker akan direkonsiliasi oleh `scheduler`; task macet akan ditandai gagal agar dapat diunggah atau diminta ulang.

### Port atau network Docker bentrok

Ubah `APP_PORT`, `KOMPEN_DB_PORT`, atau nilai network pada `.env`, kemudian:

```bash
docker compose down
docker compose up -d --build
```

## 9. Menghentikan atau mereset data lokal

Hentikan container sambil mempertahankan database dan file:

```bash
docker compose down
```

Untuk menghapus **seluruh data lokal** dan memulai dari kosong:

```bash
docker compose down -v
```

`-v` menghapus volume database, upload, ekspor, dan artifact API. Jangan pernah gunakan command ini pada staging atau production.
