# Rancangan integrasi API keluar

> Status: rancangan yang belum diimplementasikan. Dokumen ini adalah kontrak yang perlu disepakati bersama peminta API sebelum route, tabel, worker, dan deployment dibuat.

## Tujuan

API ini memberi sistem eksternal data mahasiswa Sikompen melalui HTTPS. Satu objek mahasiswa memuat identitas, akademik, ringkasan Kompen, ringkasan Respon, dan metadata sinkronisasi untuk satu periode yang wajib ditentukan peminta.

API tidak mengirim daftar besar sebagai respons JSON biasa. Permintaan berdasarkan kelas atau periode dibuat sebagai pekerjaan sinkronisasi (*sync run*) yang menghasilkan satu berkas lengkap. Backend tetap memprosesnya bertahap di worker agar tidak membebani aplikasi utama.

## Aturan transport dan autentikasi

- Produksi hanya menerima HTTPS. HTTP hanya boleh dipakai pada development lokal.
- Peminta mengirim token melalui header `Authorization: Bearer ...`, tidak pernah pada URL atau body JSON.
- Server menyimpan hash SHA-256 token, bukan token mentah.
- Setiap peminta memiliki `client_id`, token sendiri, scope, status, limit request, serta IP allowlist wajib untuk production.
- `ip_asal` tidak boleh ada pada payload. Sistem membaca IP koneksi aktual setelah konfigurasi *trusted proxy* selesai. Allowlist memakai IPv4/IPv6 atau CIDR egress statis milik peminta, bukan domain, DNS, maupun header `Origin`.
- `Idempotency-Key` wajib pada setiap pembuatan *sync run*. Pemakaian ulang key yang sama mengembalikan run lama, sehingga retry tidak membuat beban ganda.
- Tidak ada cookie, session browser, atau CORS terbuka untuk API ini.

Contoh header:

```http
Authorization: Bearer sk_live_CONTOH_TOKEN_ACAK
X-Client-Id: si-admin-production
Idempotency-Key: 4f99a54d-aaaa-bbbb-cccc-3a9f59f3d6f0
Accept: application/json
```

## Format data

### JSON biasa

Dipakai hanya untuk permintaan satu NIM. Satu respons memuat tepat satu objek mahasiswa, sehingga cukup kecil untuk dilayani langsung.

### NDJSON gzip

Dipakai untuk permintaan kelas atau periode. Berkas memakai `application/x-ndjson` yang dikompresi gzip (`.ndjson.gz`): satu baris adalah satu objek mahasiswa JSON. Peminta tetap memperoleh satu berkas lengkap, tetapi dapat memprosesnya baris demi baris tanpa menaruh seluruh isi berkas di RAM.

Format JSON biasa untuk kelas/periode ditolak agar tidak menghasilkan respons besar, timeout, atau penggunaan memori berlebihan.

## Data objek mahasiswa

Struktur standar selalu memakai versi schema. Fitur yang tidak diizinkan token atau tidak tersedia bernilai `null`, bukan menghasilkan struktur respons yang berubah-ubah.

```json
{
  "schema_version": "v1",
  "identitas": {
    "nim": "261000001",
    "nama": "Aditya Pratama"
  },
  "akademik": {
    "tingkat": 1,
    "kelas": "1AEA1",
    "periode": "2026/2027 Gasal"
  },
  "kompen": {
    "total_jam": 12,
    "jam_selesai": 8,
    "sisa_jam": 4,
    "status": "berjalan"
  },
  "respon": {
    "total_jam": 2,
    "jam_selesai": 2,
    "sisa_jam": 0
  },
  "sinkronisasi": {
    "record_version": "rec_01J...",
    "updated_at": "2026-10-14T16:30:00+07:00",
    "timezone": "Asia/Jakarta"
  }
}
```

Fitur API awal bersifat tetap: `profil`, `kompen`, dan `respon`. Detail Kompen per pertemuan/presensi serta status Surat Peringatan tidak termasuk dalam API ini.

## Ringkasan semua kasus

| Selector | Field wajib | Hasil | Format | HTTP awal |
| --- | --- | --- | --- | --- |
| Satu NIM | `nim`, `periode` | Satu mahasiswa | JSON | `200 OK` |
| Satu kelas | `kelas`, `periode` | Semua mahasiswa kelas pada periode itu | NDJSON gzip artifact | `202 Accepted` |
| Satu periode | `periode` | Semua mahasiswa seluruh kelas pada periode itu | NDJSON gzip artifact | `202 Accepted` |
| Selector tidak valid | sesuai kasus | Tidak ada data | JSON error | `422 Unprocessable Content` |
| NIM/kelas/periode tidak ditemukan | sesuai kasus | Tidak ada data | JSON error | `404 Not Found` |
| Token invalid | - | Tidak ada data | JSON error | `401 Unauthorized` |
| Scope/IP tidak diizinkan | - | Tidak ada data | JSON error | `403 Forbidden` |
| Limit terlampaui | - | Tidak ada data | JSON error + `Retry-After` | `429 Too Many Requests` |
| Worker/pipeline sementara sibuk | - | Run masih diproses | JSON status | `202 Accepted` atau `503 Service Unavailable` |

`periode` wajib bahkan pada NIM agar hasil tidak ambigu jika mahasiswa memiliki riwayat di lebih dari satu periode.

## Endpoint yang diusulkan

### 1. Satu mahasiswa berdasarkan NIM

```http
GET /api/v1/mahasiswa/{nim}?periode={periode}
```

Contoh:

```bash
curl --fail --location \
  -H "Authorization: Bearer sk_live_CONTOH_TOKEN_ACAK" \
  -H "X-Client-Id: si-admin-production" \
  "https://sikompen.example.ac.id/api/v1/mahasiswa/261000001?periode=2026%2F2027%20Gasal"
```

Respons sukses `200 OK`:

```json
{
  "status": "success",
  "request_id": "req_01J...",
  "data": {
    "schema_version": "v1",
    "identitas": {
      "nim": "261000001",
      "nama": "Aditya Pratama"
    },
    "akademik": {
      "tingkat": 1,
      "kelas": "1AEA1",
      "periode": "2026/2027 Gasal"
    },
    "kompen": {
      "total_jam": 12,
      "jam_selesai": 8,
      "sisa_jam": 4,
      "status": "berjalan"
    },
    "respon": {
      "total_jam": 2,
      "jam_selesai": 2,
      "sisa_jam": 0
    },
    "sinkronisasi": {
      "record_version": "rec_01J...",
      "updated_at": "2026-10-14T16:30:00+07:00",
      "timezone": "Asia/Jakarta"
    }
  }
}
```

Jika mahasiswa tidak ada pada periode yang diminta, responsnya `404 Not Found`:

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "STUDENT_NOT_FOUND",
    "message": "Mahasiswa tidak ditemukan pada periode yang diminta."
  }
}
```

### 2. Berkas lengkap satu kelas

```http
POST /api/v1/sync-runs
```

Body:

```json
{
  "selector": {
    "type": "kelas",
    "kelas": "1AEA1",
    "periode": "2026/2027 Gasal"
  },
  "format": "ndjson",
  "compression": "gzip"
}
```

Contoh `curl`:

```bash
curl --fail --location -X POST \
  -H "Authorization: Bearer sk_live_CONTOH_TOKEN_ACAK" \
  -H "X-Client-Id: si-admin-production" \
  -H "Idempotency-Key: 4f99a54d-aaaa-bbbb-cccc-3a9f59f3d6f0" \
  -H "Content-Type: application/json" \
  -d '{
    "selector":{"type":"kelas","kelas":"1AEA1","periode":"2026/2027 Gasal"},
    "format":"ndjson",
    "compression":"gzip"
  }' \
  "https://sikompen.example.ac.id/api/v1/sync-runs"
```

Respons awal `202 Accepted`:

```json
{
  "status": "accepted",
  "request_id": "req_01J...",
  "data": {
    "sync_run_id": "run_01J...",
    "state": "queued",
    "selector": {
      "type": "kelas",
      "kelas": "1AEA1",
      "periode": "2026/2027 Gasal"
    }
  },
  "meta": {
    "retry_after_seconds": 5
  }
}
```

### 3. Berkas lengkap satu periode

Endpoint dan cara kerja sama dengan kelas, tetapi selector-nya berbeda. Satu artifact berisi seluruh kelas pada periode tersebut.

```json
{
  "selector": {
    "type": "periode",
    "periode": "2026/2027 Gasal"
  },
  "format": "ndjson",
  "compression": "gzip"
}
```

Respons awal `202 Accepted`:

```json
{
  "status": "accepted",
  "request_id": "req_01J...",
  "data": {
    "sync_run_id": "run_01J...",
    "state": "queued",
    "selector": {
      "type": "periode",
      "periode": "2026/2027 Gasal"
    }
  },
  "meta": {
    "retry_after_seconds": 5
  }
}
```

## Status dan unduhan artifact

```http
GET /api/v1/sync-runs/{sync_run_id}
```

Masih diproses:

```json
{
  "status": "accepted",
  "request_id": "req_01J...",
  "data": {
    "sync_run_id": "run_01J...",
    "state": "processing",
    "progress": {
      "processed_students": 640,
      "total_students": 1600
    }
  },
  "meta": {
    "retry_after_seconds": 5
  }
}
```

Sudah siap:

```json
{
  "status": "success",
  "request_id": "req_01J...",
  "data": {
    "sync_run_id": "run_01J...",
    "state": "ready",
    "artifact": {
      "format": "ndjson",
      "compression": "gzip",
      "content_type": "application/x-ndjson",
      "record_count": 1600,
      "size_bytes": 482103,
      "sha256": "abc123...",
      "expires_at": "2026-10-15T16:30:00+07:00",
      "download_url": "/api/v1/sync-runs/run_01J.../download"
    }
  }
}
```

Unduh artifact. Endpoint unduhan tetap membutuhkan token dan hanya dapat diakses client pembuat run.

```bash
curl --fail --location --retry 5 --retry-all-errors --continue-at - \
  -H "Authorization: Bearer sk_live_CONTOH_TOKEN_ACAK" \
  -H "X-Client-Id: si-admin-production" \
  -o "2026-2027-gasal.ndjson.gz" \
  "https://sikompen.example.ac.id/api/v1/sync-runs/run_01J.../download"
```

Verifikasi checksum setelah unduhan selesai:

```bash
# macOS
shasum -a 256 2026-2027-gasal.ndjson.gz

# Linux
sha256sum 2026-2027-gasal.ndjson.gz
```

Proses satu mahasiswa per baris tanpa mengekstrak berkas penuh ke disk:

```bash
gunzip -c 2026-2027-gasal.ndjson.gz | while IFS= read -r mahasiswa; do
  printf '%s\n' "$mahasiswa"
done
```

## Respons kesalahan

### Selector atau format salah — `422 Unprocessable Content`

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "INVALID_SELECTOR",
    "message": "Selector harus berupa NIM dengan periode, kelas dengan periode, atau periode."
  }
}
```

Permintaan kelas/periode dengan `format: "json"` juga ditolak:

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "BULK_JSON_NOT_SUPPORTED",
    "message": "Data kelas dan periode hanya tersedia sebagai artifact NDJSON gzip."
  }
}
```

### Token salah atau dicabut — `401 Unauthorized`

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Token API tidak valid atau telah dicabut."
  }
}
```

### Scope atau IP tidak diizinkan — `403 Forbidden`

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "FORBIDDEN",
    "message": "Client tidak memiliki akses untuk data ini."
  }
}
```

### Rate limit — `429 Too Many Requests`

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "RATE_LIMITED",
    "message": "Batas request tercapai. Coba kembali setelah 30 detik."
  },
  "meta": {
    "retry_after_seconds": 30
  }
}
```

### Pipeline gagal — `500 Internal Server Error`

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "SYNC_BUILD_FAILED",
    "message": "Snapshot belum dapat dibuat. Coba kembali beberapa saat lagi."
  },
  "meta": {
    "retry_after_seconds": 30
  }
}
```

### Run atau artifact kedaluwarsa — `410 Gone`

```json
{
  "status": "error",
  "request_id": "req_01J...",
  "error": {
    "code": "SYNC_RUN_EXPIRED",
    "message": "Sesi sinkronisasi telah berakhir. Buat permintaan baru untuk memperoleh snapshot baru."
  }
}
```

## Pipeline server dan fail handling

1. API memvalidasi token, client, scope, IP aktual, selector, fitur, dan `Idempotency-Key`.
2. Untuk kelas/periode, API membuat `sync_run` lalu mengirim pekerjaan ke queue khusus `api-sync`.
3. Worker `api-sync` dibatasi satu worker khusus. Pekerjaan impor dan ekspor yang sudah ada tidak memakai queue ini.
4. Worker membaca data bertahap, membangun artifact sementara, dan menyimpan metadata snapshot (versi sumber, jumlah record, checksum, waktu pembuatan).
5. Artifact tidak dapat diunduh sampai berkas selesai, checksum sesuai, lalu dipublikasikan secara atomik. Berkas setengah jadi tidak pernah tersedia di endpoint unduh.
6. Bila worker mengalami kegagalan sementara, sistem melakukan retry terbatas dengan jeda bertambah. Bila gagal permanen, state run menjadi `failed` dan file sementara dibersihkan.
7. `GET` status dapat dipanggil berulang dengan aman. Peminta tidak perlu membuat run baru saat state masih `queued` atau `processing`.
8. Endpoint unduh mendukung HTTP Range agar `curl --continue-at -` dapat melanjutkan unduhan yang terputus.
9. Artifact hanya dapat diunduh oleh client yang membuatnya dan dibersihkan otomatis setelah 24 jam.
10. Semua permintaan dan perubahan state run dicatat ke audit log tanpa menulis token mentah atau isi data mahasiswa ke log aplikasi.

## Tutorial ringkas untuk peminta API

1. Terima `client_id` dan token melalui kanal aman. Simpan token di secret manager atau environment variable; jangan commit token ke Git, screenshot, atau log.
2. Untuk satu mahasiswa, gunakan endpoint NIM dan baca JSON langsung.
3. Untuk kelas/periode, buat *sync run* sekali menggunakan `POST /api/v1/sync-runs` dan `Idempotency-Key` UUID baru.
4. Bila responsnya `202`, tunggu sesuai `retry_after_seconds`, lalu cek `GET /api/v1/sync-runs/{id}`.
5. Setelah state `ready`, unduh file menggunakan `curl --continue-at -` agar unduhan yang putus dapat dilanjutkan.
6. Cocokkan SHA-256 lokal dengan nilai `sha256` pada respons status.
7. Proses NDJSON per baris. Jangan memuat seluruh file ke memori.
8. Jika menerima `429` atau `503`, tunggu durasi `retry_after_seconds`; jangan membuat banyak run baru.
9. Jika menerima `410`, artifact telah kedaluwarsa; buat run baru dengan selector yang sama dan `Idempotency-Key` baru.
10. Jika token diduga bocor, hentikan pemakaian dan minta admin Sikompen mencabut token tersebut sebelum token pengganti dibuat.

## Keputusan yang sudah disepakati

- Artifact disimpan selama 24 jam, lalu dihapus oleh kebijakan retensi.
- Satu worker khusus `api-sync` memproses artifact secara serial.
- IP allowlist diberlakukan sejak awal pada setiap client production dan harus memakai IP/CIDR egress statis, bukan domain.

## Kebijakan kapasitas

Tidak ada batas bisnis berdasarkan jumlah mahasiswa. Kapasitas dilindungi dengan pembuatan artifact secara streaming, pemecahan artifact besar otomatis, kuota penyimpanan, serta batas minimum ruang kosong server. Nilai operasionalnya ditetapkan saat deployment berdasarkan kapasitas disk host.

Jika satu artifact melewati target ukuran configurable (rekomendasi awal 64 MiB setelah kompresi), satu `sync_run` tetap dianggap sebagai satu pengiriman penuh tetapi menghasilkan manifest dan beberapa bagian berurutan, misalnya `part-0001.ndjson.gz`, `part-0002.ndjson.gz`, dan seterusnya. Manifest memuat jumlah record serta checksum SHA-256 setiap bagian dan checksum keseluruhan. Peminta mengunduh seluruh bagian dengan token yang sama, memverifikasi checksum, lalu memprosesnya berurutan. Ini bukan pagination API dan tidak mengubah isi data; pemecahan hanya melindungi koneksi, disk, serta mekanisme resume.

Sebelum job dimulai, server mengestimasi kebutuhan ruang dan memastikan ada ruang cadangan minimum. Jika kapasitas tidak cukup, run ditolak atau ditunda dengan respons `503` dan `retry_after_seconds`; sistem tidak pernah memulai artifact yang diketahui tidak dapat diselesaikan. Artifact dibuat sebagai file sementara, dipublikasikan atomically setelah lengkap, dan dapat dibangun ulang dari snapshot run bila worker gagal.
