# Sikompen - Kompen Respon Hub

Sikompen mengelola data Kompen dan Responsi per periode dari workbook XLSX, roster mahasiswa aktif, progres pekerjaan, koreksi ter-audit, Surat Peringatan (SP), ekspor, serta API integrasi keluar.

## Mulai cepat

```bash
git clone <URL_REPOSITORY> sikompen
cd sikompen
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan migrate --force
```

Buka aplikasi pada `http://localhost:8080`. Buat akun admin reguler pertama melalui `/admin/setup` bila setup dibuka. Untuk instruksi server lengkap, gunakan [Panduan Deploy](docs/panduan-deploy-setelah-clone.pdf).

## Layanan Docker

| Layanan | Tanggung jawab |
| --- | --- |
| `web` | Nginx dan aset frontend. |
| `app` | Laravel PHP-FPM dan HTTP backend. |
| `database` | MariaDB dan database queue/cache/session. |
| `queue`, `imports`, `exports`, `api-sync` | Worker umum serta worker terpisah untuk impor, ekspor, dan artifact API. |
| `scheduler` | Rekonsiliasi task dan retensi terjadwal. |
| `api-mini-app` | Panel internal untuk menguji API provider; bukan layanan publik. |

## Dokumentasi

### Panduan PDF

- [Deploy setelah clone](docs/panduan-deploy-setelah-clone.pdf)
- [Menggunakan API setelah container aktif](docs/panduan-penggunaan-api.pdf)
- [Panduan lengkap admin](docs/panduan-admin.pdf)
- [Panduan lengkap mahasiswa](docs/panduan-mahasiswa.pdf)
- [Panduan admin darurat](docs/panduan-admin-darurat.pdf)

### Referensi teknis

- [Indeks dokumentasi](docs/README.md)
- [System overview](docs/system-overview.md)
- [System design](docs/system-design.md)
- [ERD dan penjelasan data](docs/erd.md)
- [Kontrak API v1](docs/api-outbound-integration-proposal.md)
- [Deployment teknis](docs/deployment-guide.md)
- [Worker dan scheduler](docs/worker-and-scheduler.md)

## Batas keamanan penting

- Workbook, roster, ekspor, dan artifact API berada di private storage; database menyimpan metadata dan data operasional.
- Mahasiswa hanya dapat membaca profil serta data NIM yang sedang masuk sesi mereka.
- API `/api/sikompen/v1` menggunakan Bearer token unik per client, rate limit, dan IP/CIDR allowlist opsional. Jangan mengirim token ke frontend atau log.
- Admin emergency hanya dapat memulihkan akses admin reguler; ia tidak mendapat akses data operasional.

Kode, migration, dan test di repository adalah sumber kebenaran apabila dokumentasi perlu diverifikasi.
