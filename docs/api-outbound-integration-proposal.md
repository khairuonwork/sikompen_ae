# Kontrak API Outbound Sikompen v1

> Status: diimplementasikan. Nama file dipertahankan untuk kompatibilitas tautan lama.

API ini memberi sistem eksternal ringkasan Kompen/Respon mahasiswa. Ia terpisah dari API internal berbasis session pada `/api/kompen-respon/*`.

## Autentikasi dan akses

Setiap request memerlukan:

```http
Authorization: Bearer <token>
X-Client-Id: <client-id>
Accept: application/json
```

Client disimpan dengan hash SHA-256 token, scope tetap `kompen-respon:read`, status aktif, limit per menit, dan allowlist IP/CIDR. Server memeriksa alamat IP koneksi aktual. Token mentah hanya ditampilkan sekali ketika client dibuat.

```bash
php artisan sikompen:create-api-client si-admin-production "SI Admin" --ip=172.30.0.10 --rate=30
php artisan sikompen:revoke-api-client si-admin-production
```

## Respons umum

Respons JSON memiliki `status` dan `request_id`. Error selalu memakai HTTP status yang relevan dan struktur berikut:

```json
{
  "status": "error",
  "request_id": "01...",
  "error": {"code": "unauthenticated", "message": "Kredensial client API tidak valid."}
}
```

Server menggunakan `Cache-Control: no-store` dan `X-Content-Type-Options: nosniff` pada respons API. Rate limit menghasilkan `429`; akses token/client salah menghasilkan `401`; scope/IP salah menghasilkan `403`.

## Satu mahasiswa

```http
GET /api/v1/mahasiswa/{nim}?periode=2026%2F2027%20Gasal
```

`periode` harus berformat `YYYY/YYYY Gasal|Genap`. Respons `200` memuat objek berikut di `data`:

```json
{
  "schema_version": "v1",
  "identitas": {"nim": "261000001", "nama": "Aditya Pratama"},
  "akademik": {"tingkat": 1, "kelas": "1AEA1", "periode": "2026/2027 Gasal"},
  "kompen": {"total_jam": 12, "jam_selesai": 8, "sisa_jam": 4, "status": "berjalan"},
  "respon": {"total_jam": 2, "jam_selesai": 2, "sisa_jam": 0},
  "sinkronisasi": {"record_version": "rec_...", "updated_at": "2026-10-19T...+07:00", "timezone": "Asia/Jakarta"}
}
```

Jika NIM tidak ada pada periode yang diminta, server mengembalikan `404` dengan kode `not_found`.

## Sinkronisasi massal

Gunakan `POST /api/v1/sync-runs` untuk satu kelas atau seluruh periode. `Idempotency-Key` wajib (maksimal 128 karakter dapat cetak) dan unik untuk selector yang sama pada client yang sama.

```http
POST /api/v1/sync-runs
Idempotency-Key: 4f99a54d-aaaa-bbbb-cccc-3a9f59f3d6f0
Content-Type: application/json

{"selector":{"type":"kelas","kelas":"1AEA1","periode":"2026/2027 Gasal"},"format":"ndjson","compression":"gzip"}
```

Selector `periode` menghilangkan field `kelas`. Permintaan baru merespons `202`; pengulangan idempotent untuk selector sama merespons `200`; key yang dipakai selector lain merespons `409`.

```json
{
  "status": "success",
  "request_id": "01...",
  "data": {
    "sync_run_id": "01...",
    "status": "queued",
    "selector": {"type": "kelas", "periode": "2026/2027 Gasal", "kelas": "1AEA1"},
    "format": "ndjson",
    "compression": "gzip",
    "record_count": null,
    "artifact_sha256": null,
    "artifact_size_bytes": null,
    "download_url": null,
    "expires_at": null,
    "failure_code": null
  }
}
```

## Status dan download artifact

```http
GET /api/v1/sync-runs/{runId}
GET /api/v1/sync-runs/{runId}/download
```

Run mengikuti state `queued`, `processing`, `ready`, `failed`, atau `expired`. Saat `ready`, status menyediakan checksum, jumlah record, ukuran artifact, waktu kedaluwarsa, dan `download_url`. Download mengembalikan `application/gzip` dengan nama `.ndjson.gz`. Run/artifact hanya dapat diakses oleh client yang membuatnya; artifact belum siap memberi `409`, yang kedaluwarsa memberi `410`.

Setiap baris setelah gzip didekompresi adalah satu objek mahasiswa JSON. API tidak menjanjikan progress per-record, resume/Range download, retry otomatis, quota kapasitas, atau snapshot immutable di luar data yang dibaca job saat ia berjalan.

## Operasi server

Job artifact berjalan pada queue `api-sync`, satu percobaan, timeout 600 detik. Artifact default bertahan 24 jam dan terminal run default dipurge setelah 7 hari. Scheduler menjalankan `sikompen:purge-api-sync-artifacts` setiap jam; reconciler menangani run yang macet.
