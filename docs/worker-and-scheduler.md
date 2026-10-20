# Worker, Queue, dan Scheduler

## Service lokal yang berjalan

Docker Compose saat ini memiliki sembilan service: `web`, `app`, `database`, `api-mini-app`, `queue`, `imports`, `exports`, `api-sync`, dan `scheduler`.

| Service | Tanggung jawab |
| --- | --- |
| `queue` | Worker queue default Laravel. |
| `imports` | Impor workbook Kompen/Respon dan roster mahasiswa aktif pada queue `imports`. |
| `exports` | Ekspor XLSX/PDF pada queue `exports`. |
| `api-sync` | Pembuatan artifact NDJSON gzip pada queue `api-sync`. |
| `scheduler` | Menjalankan schedule pemulihan dan retensi. |

Keempat worker tidak saling menggantikan. Upload atau ekspor dapat diterima oleh web, tetapi tidak selesai jika worker queue yang relevan tidak hidup. Volume private untuk import, export, dan API sync dipisah agar path pekerjaan tersedia bagi producer/worker yang membutuhkannya.

## Task impor

Workbook Kompen/Respon dan roster mahasiswa aktif sama-sama memiliki state `queued`, `processing`, `completed`, atau `failed`.

| State | Makna |
| --- | --- |
| `queued` | File telah tersimpan dan menunggu worker `imports`. |
| `processing` | Archive/template sedang divalidasi dan data sedang disimpan. |
| `completed` | Versi file tersedia untuk dipilih admin. |
| `failed` | Validasi atau proses gagal; pesan aman tersedia pada task. |

Task gagal membersihkan source file private-nya. Task berhasil tidak otomatis memilih versi sebagai aktif. Parser roster memastikan blok kelas konsisten dalam satu periode; parser workbook memeriksa struktur template, detail, dan total jam.

## Task ekspor

| State | Makna | Aksi pengguna |
| --- | --- | --- |
| `queued` | Permintaan menunggu worker `exports`. | Menunggu atau batalkan. |
| `processing` | Data dan file sedang dibangun. | Menunggu atau batalkan. |
| `completed` | File tersedia sampai retensi berakhir. | Download atau dismiss notifikasi. |
| `failed` | Worker/validasi gagal. | Baca pesan dan buat ulang task. |
| `cancelled` | Dibatalkan sebelum selesai. | Buat task baru bila perlu. |

`GenerateKompenResponHubExport` menggunakan `WithoutOverlapping('sikompen:exports')`, sehingga ekspor berjalan satu per satu. Poll/download mengharuskan `access_token` acak dan session pembuat task.

## API sync run

API `/api/sikompen/v1` membuat `sync run` dengan state `queued → processing → ready|failed|expired`. Job `GenerateKompenResponHubApiSyncArtifact` memiliki tiga percobaan (backoff 30/120 detik) dan timeout 600 detik. Artifact NDJSON gzip dibangun dari snapshot transaksi database, dibatasi ukuran, dan hanya dapat dibaca/download oleh client pembuat run.

## Schedule pemeliharaan

| Command | Jadwal | Dampak |
| --- | --- | --- |
| `sikompen:reconcile-tasks` | tiap 5 menit | Menandai task impor, ekspor, atau API sync yang macet sebagai gagal. |
| `sikompen:purge-exports` | tiap jam | Menghapus hasil/record ekspor kedaluwarsa. |
| `sikompen:purge-api-sync-artifacts` | tiap jam | Menghapus artifact dan run API sync terminal setelah retensi. |
| `sikompen:purge-import-tasks` | tiap hari | Menghapus record task impor terminal setelah retensi. |
| `sikompen:purge-activity-logs` | 02:15 `Asia/Jakarta` | Menerapkan retensi Riwayat Aktivitas. |
| `sikompen:purge-import-audit-logs` | 02:30 `Asia/Jakarta` | Menerapkan retensi Log Upload. |
| `sikompen:purge-recycled-workbooks` | tiap hari | Menghapus record dan file recycle bin yang melewati retensi. |

Semua schedule menggunakan `withoutOverlapping()`. Ambang `processing` default 15 menit dan grace period `queued` default 60 menit; keduanya dapat diubah melalui environment.

## Triage singkat

| Gejala | Pemeriksaan | Tindakan aman |
| --- | --- | --- |
| Upload berhenti | `docker compose logs imports --tail=100` | Pastikan worker `imports` hidup; jangan hapus task. |
| Ekspor berhenti | `docker compose logs exports --tail=100` | Tunggu reconciler atau buat task baru setelah status terminal. |
| API run gagal | `docker compose logs api-sync --tail=100` | Periksa selector, kapasitas artifact, dan konfigurasi client; kirim ulang selector setelah status `failed`. |
| Status tak berubah | Periksa `scheduler` | Reconciler akan memberi state gagal setelah ambang terlewati. |
| File recycle bin hilang | Periksa retensi | File yang sudah dipurge tidak dapat dipulihkan. |
