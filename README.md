# Sikompen — Kompen Respon Hub

Sikompen mengelola data Kompen dan Responsi dari workbook XLSX, roster mahasiswa aktif, progres pengerjaan, koreksi ter-audit, Surat Peringatan (SP), serta ekspor dan integrasi API keluar.

## Mulai dari sini

- [Indeks dokumentasi](docs/README.md) — peta seluruh dokumentasi aplikasi.
- [Panduan fungsionalitas](docs/functionality-guide.md) — alur untuk admin dan mahasiswa.
- [Arsitektur dan logika sistem](docs/system-logic.md) — sumber data, lifecycle, dan aturan bisnis.
- [Panduan Docker lokal](docs/docker-local-guide.md) — menjalankan lingkungan pengembangan lokal.

Dokumentasi deployment dipisahkan dan tidak dirangkum di README ini.

## Teknologi utama

Laravel 13, PHP 8.4, Inertia 3, React 19, Tailwind CSS 4, MariaDB, database queue, PhpSpreadsheet, dan Dompdf.

## Batas keamanan penting

- Workbook dan artifact disimpan pada storage private, bukan sebagai BLOB database.
- Data mahasiswa dibatasi oleh session dan roster aktif per periode.
- API outbound memakai client token, scope, IP allowlist, rate limit, dan artifact NDJSON gzip untuk sinkronisasi massal.
- Dokumen dan test di repository adalah sumber kebenaran teknis.
