# Kontrak API Sikompen v1

Base URL provider: `https://<domain-sikompen>/api/sikompen/v1`.

API ini hanya menyediakan identitas akademik serta ringkasan Kompen dan Respon. Data detail pertemuan dan status Surat Peringatan tidak termasuk. Semua waktu memakai `Asia/Jakarta`.

## 1. Autentikasi dan aturan umum

Setiap request wajib membawa satu header:

```http
Authorization: Bearer <token-rahasia>
Accept: application/json
```

Tidak ada `X-Client-Id`, tidak ada idempotency key, dan tidak ada pilihan format pada request. Provider mengenali client dari token; token, IP/CIDR asal, serta rate limit dikelola pada environment provider. Untuk client dengan IP egress statis, request dari IP di luar allowlist selalu ditolak walaupun token benar. Untuk client tanpa IP egress statis, operator dapat menetapkan `allowed_ips` sebagai daftar kosong (`[]`); client tersebut diautentikasi oleh Bearer token saja.

Token harus disimpan sebagai secret aplikasi Laravel peminta, misalnya pada `.env`:

```dotenv
SIKOMPEN_API_BASE_URL=https://sikompen.example.ac.id
SIKOMPEN_API_TOKEN=isi-token-rahasia-yang-diberikan-tim-sikompen
```

Jangan pernah menyimpan token pada kode frontend, query string, log HTTP, atau payload browser.

Semua respons JSON memiliki struktur dasar berikut:

```json
{
  "status": "success",
  "request_id": "01J...",
  "data": {}
}
```

Jika gagal:

```json
{
  "status": "error",
  "request_id": "01J...",
  "error": {
    "code": "not_found",
    "message": "Data mahasiswa tidak ditemukan untuk periode yang diminta."
  }
}
```

Simpan `request_id` saat meminta bantuan ke tim Sikompen. Respons API memakai `Cache-Control: no-store`.

| HTTP | Kode umum | Tindakan peminta |
| --- | --- | --- |
| 200 | sukses | Gunakan `data`. |
| 202 | sync run baru masuk antrean | Poll endpoint status. |
| 401 | `unauthenticated` | Periksa token. Jangan retry berulang. |
| 403 | `ip_not_allowed` / `forbidden` | Bila client memakai allowlist, minta IP/CIDR diperbarui; selain itu periksa scope client. |
| 404 | `not_found` | Periksa NIM/periode/run ID. |
| 409 | `artifact_not_ready` | Poll status terlebih dahulu. |
| 410 | `artifact_expired` | Buat sync run baru. |
| 422 | `validation_error` | Perbaiki parameter/payload. |
| 429 | rate limit / `sync_request_busy` | Hormati `Retry-After`, lalu retry dengan jeda. |
| 503 | `service_unavailable` | Provider belum terkonfigurasi/siap; hubungi operator. |

## 2. Ambil satu mahasiswa

Gunakan saat halaman atau proses Anda membutuhkan satu NIM.

```http
GET /mahasiswa/{nim}?periode=2026%2F2027%20Gasal
```

Contoh Laravel HTTP client:

```php
$response = Http::acceptJson()
    ->withToken(config('services.sikompen.token'))
    ->get(config('services.sikompen.base_url')."/api/sikompen/v1/mahasiswa/{$nim}", [
        'periode' => '2026/2027 Gasal',
    ])
    ->throw()
    ->json('data');
```

`nim` berupa 6–32 digit. `periode` wajib persis `YYYY/YYYY Gasal` atau `YYYY/YYYY Genap`.

Respons `200`:

```json
{
  "status": "success",
  "request_id": "01J...",
  "data": {
    "schema_version": "v1",
    "identitas": {"nim": "261000001", "nama": "Aditya Pratama"},
    "akademik": {"tingkat": 1, "kelas": "1AEA1", "periode": "2026/2027 Gasal"},
    "kompen": {"total_jam": 12, "jam_selesai": 8, "sisa_jam": 4, "status": "berjalan"},
    "respon": {"total_jam": 2, "jam_selesai": 2, "sisa_jam": 0},
    "sinkronisasi": {
      "record_version": "rec_...",
      "updated_at": "2026-10-19T09:00:00+07:00",
      "timezone": "Asia/Jakarta"
    }
  }
}
```

Gunakan `record_version` atau `updated_at` untuk menentukan apakah data lokal perlu diperbarui.

## 3. Sinkronisasi satu kelas atau satu periode

Gunakan saat perlu menyalin banyak mahasiswa. Provider membangun artifact di worker khusus agar request HTTP cepat dan tidak membebani aplikasi.

```http
POST /sync-runs
Content-Type: application/json

{
  "selector": {
    "type": "kelas",
    "periode": "2026/2027 Gasal",
    "kelas": "1AEA1"
  }
}
```

Untuk semua kelas pada satu periode:

```json
{
  "selector": {
    "type": "periode",
    "periode": "2026/2027 Gasal"
  }
}
```

`kelas` hanya wajib saat `type` adalah `kelas`. Jangan mengirim `format`, `compression`, `X-Client-Id`, atau `Idempotency-Key`.

Jika selector identik dikirim ulang oleh token yang sama saat run masih antre/diproses atau artifact 24 jamnya masih tersedia, provider mengembalikan run yang sama (`200`). Jika belum ada, provider membuat run baru (`202`). Ini membuat retry aman untuk pemakaian normal tanpa idempotency key dari peminta.

Contoh respons `202`:

```json
{
  "status": "success",
  "request_id": "01J...",
  "data": {
    "sync_run_id": "01JH2A4N1K...",
    "status": "queued",
    "selector": {"type": "kelas", "periode": "2026/2027 Gasal", "kelas": "1AEA1"},
    "snapshot_at": null,
    "record_count": null,
    "artifact": {"format": "ndjson", "compression": "gzip", "sha256": null, "size_bytes": null},
    "download_url": null,
    "expires_at": null,
    "failure_code": null
  }
}
```

## 4. Poll status dan unduh hasil

Poll setiap 3–10 detik, dengan maksimal 30 request per menit per token secara default:

```http
GET /sync-runs/{sync_run_id}
```

Saat `status` menjadi `ready`, respons memuat `download_url`, `record_count`, checksum SHA-256, ukuran file, `snapshot_at`, dan `expires_at`.

```http
GET /sync-runs/{sync_run_id}/download
Accept: application/gzip
```

Download adalah file `application/gzip` bernama `.ndjson.gz`. Setelah didekompresi, setiap baris adalah satu objek JSON mahasiswa dengan schema yang sama seperti endpoint NIM tunggal. Baca satu baris demi satu baris (streaming), jangan memuat seluruh file ke memori.

Contoh Laravel untuk mendownload secara streaming:

```php
$response = Http::withToken(config('services.sikompen.token'))
    ->withOptions(['stream' => true])
    ->get($downloadUrl);

abort_unless($response->successful(), $response->status());

// Simpan stream ke file private, verifikasi hash SHA-256, lalu baca NDJSON.GZ
// satu per satu sebelum melakukan upsert pada database aplikasi Anda.
```

Artifact berlaku 24 jam sejak selesai dibuat. Yang dihapus hanya file NDJSON.GZ; data Kompen/Respon utama Sikompen tidak ikut dihapus. Setelah menerima `410 artifact_expired`, buat POST sync run baru dengan selector yang sama.

## 5. Keandalan dan pola integrasi

1. Untuk kebutuhan per mahasiswa, panggil endpoint NIM langsung.
2. Untuk sinkronisasi besar, buat sync run, simpan `sync_run_id`, dan poll hingga `ready` atau `failed`.
3. Saat `ready`, download sekali ke storage private aplikasi peminta, verifikasi checksum `artifact.sha256`, lalu parse NDJSON.GZ secara streaming dan upsert idempoten berdasarkan NIM + periode.
4. Tandai sync lokal sukses hanya setelah seluruh file selesai diproses. Jika parsing lokal gagal, gunakan artifact yang sama selama belum 24 jam; jangan langsung membuat run baru.
5. Jika status `failed`, buat request selector ulang setelah menyelesaikan penyebabnya. Jika `failure_code` adalah `artifact_too_large`, hubungi operator Sikompen untuk menentukan pemecahan per kelas atau menaikkan batas terukur.

Worker provider memiliki retry terbatas dan membuat snapshot transaksi yang konsisten saat artifact dibangun. Artifact diberi batas ukuran compressed dan uncompressed untuk mencegah disk server penuh.

## 6. Konfigurasi deployment untuk operator Sikompen

Client produksi didefinisikan dalam `.env`, sehingga operator cukup mengubah konfigurasi deployment lalu melakukan recreate container; tidak ada command untuk membuat client pada database.

```dotenv
SIKOMPEN_API_CLIENTS_JSON='[{"key":"akademik-production","token":"TOKEN_MINIMAL_32_KARAKTER","allowed_ips":[],"rate_limit_per_minute":30}]'
SIKOMPEN_TRUSTED_PROXY_CIDRS=172.30.0.0/24
SIKOMPEN_TRUSTED_PROXY_CIDR=172.31.0.0/24
```

`allowed_ips` adalah daftar IP/CIDR egress peminta dan bersifat opsional. Gunakan `[]` jika peminta memakai Cloudflare Tunnel atau jaringan lain tanpa IP egress stabil; autentikasi tetap wajib memakai Bearer token unik minimal 32 karakter dan rate limit per token tetap berlaku. Jangan membuat atau mempercayai header IP buatan untuk menggantikan allowlist. Bila peminta memiliki IP egress stabil, isi IP/CIDR untuk lapis pembatas tambahan. `SIKOMPEN_TRUSTED_PROXY_CIDRS` adalah network internal antara Nginx dan PHP-FPM (`172.30.0.0/24`), sedangkan `SIKOMPEN_TRUSTED_PROXY_CIDR` adalah network proxy khusus Cloudflare Tunnel (`172.31.0.0/24`). Jangan menyamakan keduanya dengan allowlist client API.

## Prompt untuk LLM tim integrasi

Lampirkan PDF/Markdown ini pada LLM pilihan tim Anda, lalu gunakan prompt berikut:

```text
Anda adalah software engineer Laravel senior. Implementasikan integrasi production-ready
ke Sikompen API berdasarkan kontrak terlampir, tanpa mengubah kontrak provider.

Kebutuhan:
1. Simpan base URL dan bearer token dalam config/services.php yang membaca .env; token tidak boleh muncul di frontend atau log.
2. Buat service class yang menangani lookup satu NIM dan sync kelas/periode.
3. Untuk sync bulk: POST selector, simpan sync_run_id, poll 3–10 detik dengan backoff, download NDJSON.GZ saat ready, verifikasi SHA-256, lalu stream-decompress dan upsert idempoten dengan kunci NIM + periode.
4. Tangani 401/403/404/409/410/422/429/503 sesuai kontrak; jangan retry credential atau validation error. Hormati Retry-After.
5. Buat migration, job/command jika diperlukan, feature test dengan Http::fake(), dan jangan gunakan package baru tanpa alasan.
6. Tunjukkan file yang dibuat/diubah serta jelaskan bagaimana token dan IP egress dikonfigurasi.
```
