# Sikompen API Mini App

Panel FastAPI lokal untuk menguji API outbound Sikompen tanpa mengekspos bearer token ke browser. Aplikasi ini meneruskan request ke Laravel dan menjaga token pada environment container.

## Endpoint

- `/` — panel uji internal.
- `/health` — status konfigurasi provider (`ready` atau `missing`).
- `/tests/mahasiswa` — proxy mahasiswa tunggal.
- `/tests/sync-runs` dan `/tests/sync-runs/{run_id}` — proxy sync run.
- `/tests/sync-runs/{run_id}/download` — stream artifact gzip.

## Konfigurasi

Isi `.env` dengan `SIKOMPEN_API_BASE_URL` dan `SIKOMPEN_API_BEARER_TOKEN`. Daftarkan token pada `SIKOMPEN_API_CLIENTS_JSON`. Secara lokal, Compose dapat memakai IP container mini app (`172.30.0.10/32`) sebagai allowlist tambahan. Untuk client tanpa IP egress stabil, gunakan `allowed_ips: []`; token unik dan rate limit tetap wajib.

Panel tersedia secara lokal pada `http://localhost:8090` ketika Docker Compose berjalan. Ini adalah alat pengujian internal, bukan pengganti client integrasi produksi.
