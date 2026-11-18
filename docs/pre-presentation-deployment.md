# Deployment sebelum presentasi

Dokumen ini adalah urutan kerja untuk menyiapkan Sikompen di server demonstrasi. Jalankan perintah dari root repository:

```bash
cd ~/docker-composes/sikompen/kompen-respon-hub
```

URL instance saat ini, setelah Funnel aktif:

```text
Web Sikompen: https://ubuntu-headless-daffa.tail472887.ts.net
API Mini App demo: https://ubuntu-headless-daffa.tail472887.ts.net:8443
API provider: https://ubuntu-headless-daffa.tail472887.ts.net/api/sikompen/v1
```

Selalu konfirmasi URL melalui `tailscale funnel status`, karena nama node atau tailnet dapat berubah.

## 1. Ambil kode staging

```bash
git fetch origin --prune
git switch staging
git pull --ff-only origin staging
git status --short --branch
```

Hasil terakhir harus menunjukkan `## staging...origin/staging`. Jangan menjalankan update bila masih ada perubahan source yang belum dipahami.

## 2. Siapkan `.env`

Jika file belum ada:

```bash
cp .env.production.template .env
```

Isi nilai rahasia berikut pada `.env`, tanpa memasukkannya ke Git:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ubuntu-headless-daffa.tail472887.ts.net
DOCKER_APP_URL=https://ubuntu-headless-daffa.tail472887.ts.net

APP_BIND_HOST=127.0.0.1
APP_PORT=8083
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null

KOMPEN_DB_DATABASE=kompen_database
KOMPEN_DB_USERNAME=kompen_user
KOMPEN_DB_PASSWORD=<password-database-baru>
KOMPEN_DB_ROOT_PASSWORD=<password-root-baru>

API_MINI_APP_BIND_HOST=127.0.0.1
API_MINI_APP_PORT=8090

SIKOMPEN_TRUSTED_PROXY_CIDRS=127.0.0.1/32,172.30.0.0/24
SIKOMPEN_TRUSTED_PROXY_CIDR=172.31.0.0/24
```

`SIKOMPEN_TRUSTED_PROXY_CIDRS` dipakai Laravel dan dapat berisi beberapa CIDR. `SIKOMPEN_TRUSTED_PROXY_CIDR` dipakai template Nginx dan hanya boleh berisi satu CIDR. Jangan menaruh daftar dipisahkan koma pada variabel singular karena Nginx akan gagal start.

Isi juga `SIKOMPEN_EMERGENCY_ADMIN_1_USERNAME`, `SIKOMPEN_EMERGENCY_ADMIN_1_PASSWORD`, `SIKOMPEN_EMERGENCY_ADMIN_2_USERNAME`, dan `SIKOMPEN_EMERGENCY_ADMIN_2_PASSWORD` bila akun darurat akan dipakai.

## 3. Buat `APP_KEY` pada instalasi baru

Nyalakan database dan PHP-FPM terlebih dahulu:

```bash
docker compose up -d --build database app
docker compose exec -T app php artisan key:generate --show
```

Salin output perintah terakhir secara utuh ke nilai `APP_KEY` pada `.env`. Nilainya harus diawali `base64:`.

Sesudah menyimpan `.env`, recreate seluruh service agar environment baru dipakai:

```bash
docker compose up -d --build --force-recreate
```

## 4. Jalankan database dan seed yang diperlukan

```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan db:seed --class=SikompenEmergencyAdminSeeder --force
docker compose exec -T app php artisan optimize
```

Seeder akun darurat bersifat opsional. Jalankan hanya bila empat variabel `SIKOMPEN_EMERGENCY_ADMIN_*` sudah diisi.

Admin aplikasi pertama dibuat melalui URL berikut bila database masih kosong:

```text
https://ubuntu-headless-daffa.tail472887.ts.net/admin/setup
```

## 5. Verifikasi container dan layanan lokal

Jalankan seluruh command berikut sebelum membuka URL publik:

```bash
docker compose ps
curl -i http://127.0.0.1:8083/up
curl -i http://127.0.0.1:8083/
curl -i http://127.0.0.1:8090/health
```

Kondisi yang diharapkan:

- `app`, `web`, `queue`, `imports`, `exports`, `api-sync`, dan `scheduler` berstatus `Up`.
- `/up` dan `/` memberi HTTP `200`.
- `/health` dari API Mini App memberi HTTP `200` apabila panel demo dijalankan.

Jika ada masalah, cek log tanpa menghapus volume:

```bash
docker compose logs --tail=150 web app
docker compose logs --tail=150 queue imports exports api-sync scheduler
```

## 6. Buka URL publik dengan Tailscale Funnel

Funnel untuk aplikasi utama:

```bash
tailscale funnel --bg 8083
tailscale funnel status
```

Funnel API Mini App hanya untuk demo presentasi:

```bash
tailscale funnel --bg --https=8443 8090
tailscale funnel status
```

Uji dari server:

```bash
curl -i https://ubuntu-headless-daffa.tail472887.ts.net/up
curl -i https://ubuntu-headless-daffa.tail472887.ts.net:8443/health
```

## 7. Urutan pengecekan sebelum presentasi

1. Buka web utama dan login sebagai admin.
2. Buka `/admin/setup` hanya jika belum ada akun admin pertama.
3. Pastikan halaman mahasiswa, reset password, upload, daftar file, Kompen dan Respon, Surat Peringatan, serta riwayat aktivitas dapat dibuka.
4. Pastikan `api-sync` berstatus `Up` karena worker ini membuat artifact API besar.
5. Buka API Mini App pada port `8443` hanya jika akan diperlihatkan. Panel ini tidak dipakai tim integrasi.
6. Tunjukkan endpoint provider pada `/api/sikompen/v1`, bukan endpoint Mini App.

## Perubahan konfigurasi yang sudah diperlukan

| Area | Nilai atau keputusan |
| --- | --- |
| Akses host | Web dan Mini App hanya bind ke `127.0.0.1`. Funnel yang membuka akses publik. |
| Web | Port lokal web diganti ke `8083`. |
| Mini App | Port lokal demo adalah `8090`, dipublikasikan Funnel pada HTTPS `8443` hanya selama demo. |
| Database | Tetap hanya bind ke loopback dan tidak dibuka lewat Funnel. |
| Environment | Container harus direcreate setelah mengganti `.env`. Restart biasa tidak selalu memuat nilai baru. |
| Proxy | Nginx menerima satu CIDR pada `SIKOMPEN_TRUSTED_PROXY_CIDR`; Laravel menerima daftar CIDR pada variabel plural. |
| API | Integrasi eksternal memakai Bearer token pada `/api/sikompen/v1`. Artifact NDJSON.GZ dihapus setelah 24 jam. |

## Penanganan masalah yang sudah pernah terjadi

| Gejala | Pemeriksaan dan perbaikan |
| --- | --- |
| `Access denied for user 'kompen_user'` | Password pada `.env` tidak sama dengan password yang tersimpan pada volume MariaDB. Untuk database testing kosong, koreksi `.env`, hapus hanya volume database, lalu bootstrap ulang. Jangan hapus volume pada data penting. |
| `Unsupported cipher or incorrect key length` | `APP_KEY` tidak lengkap atau tidak valid. Buat ulang melalui `php artisan key:generate --show`, simpan nilai lengkap, lalu jalankan `docker compose up -d --force-recreate`. |
| `web` restart dan Funnel memberi `502` | Periksa `SIKOMPEN_TRUSTED_PROXY_CIDR`; nilainya harus satu CIDR. Lalu jalankan `docker compose up -d --force-recreate web`. |
| Funnel `502` | Periksa dulu `curl -i http://127.0.0.1:8083/up`. Jika lokal tidak sehat, Funnel tidak perlu dibuat ulang. Perbaiki container dan log lokal terlebih dahulu. |
| Halaman hitam | Cek `APP_URL`, `DOCKER_APP_URL`, respons HTML lokal dan publik, lalu lihat `docker compose logs --tail=150 web app`. |
| Worker restart berulang | Cek error pertama melalui `docker compose logs --tail=150 queue imports exports api-sync scheduler`. Biasanya penyebabnya koneksi database atau `APP_KEY`. |

## Reset database untuk demo baru

Jalankan hanya bila semua data aplikasi boleh dihapus. Perintah ini tidak menghapus volume import, export, dan artifact API.

```bash
docker compose down --remove-orphans
docker volume rm kompen-respon-hub_sikompen_database_data
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
```

Setelah reset, buat admin pertama lagi melalui `/admin/setup` dan seed akun darurat bila diperlukan.

## Setelah presentasi

Tutup Mini App publik lebih dahulu:

```bash
tailscale funnel --https=8443 off
```

Bila web utama juga tidak lagi dipakai untuk demo, tutup Funnel utama:

```bash
tailscale funnel --https=443 off
```

Jangan memakai `docker compose down -v` kecuali seluruh database dan file pada named volume memang ingin dihapus.
