# Sikompen API Mini App

Panel FastAPI lokal untuk menguji API outbound Sikompen tanpa mengekspos bearer token ke browser. Aplikasi ini meneruskan request ke Laravel dan menjaga token pada environment container.

## Endpoint

- `/` — panel uji internal.
- `/health` — status konfigurasi provider (`ready` atau `missing`).
- `/tests/mahasiswa` — proxy mahasiswa tunggal.
- `/tests/sync-runs` dan `/tests/sync-runs/{run_id}` — proxy sync run.
- `/tests/sync-runs/{run_id}/download` — stream artifact gzip.

## Konfigurasi

Isi `.env` dengan `SIKOMPEN_API_BASE_URL`, `SIKOMPEN_API_CLIENT_ID`, dan `SIKOMPEN_API_BEARER_TOKEN`. Buat client melalui command Laravel, lalu masukkan IP container mini app ke allowlist client (default Compose `172.30.0.10`).

Panel tersedia secara lokal pada `http://localhost:8090` ketika Docker Compose berjalan. Ini adalah alat pengujian internal, bukan pengganti client integrasi produksi.
