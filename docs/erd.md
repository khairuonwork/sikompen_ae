# ERD Sikompen

Seluruh tabel domain Sikompen berada pada koneksi `kompen_db` dan memakai awalan `sikompen_`. Tabel `jobs`, `job_batches`, dan `failed_jobs` adalah infrastruktur Laravel Queue pada koneksi yang sama.

```mermaid
erDiagram
    sikompen_admins {
        bigint id PK
        varchar email UK
        varchar password
        timestamp created_at
        timestamp updated_at
    }

    sikompen_admin_setup_windows {
        bigint id PK
        varchar activation_code_hash
        timestamp expires_at
        bigint opened_by_admin_id FK
    }

    sikompen_imports {
        bigint id PK
        bigint uploaded_by_admin_id FK
        varchar uploader_name
        varchar uploader_email
        varchar periode_semester
        varchar original_filename
        varchar stored_path
        char file_hash
        int class_count
        int student_count
        int detail_count
        timestamp imported_at
    }

    sikompen_mahasiswa {
        bigint id PK
        bigint kompen_respon_hub_import_id FK
        varchar nim
        varchar periode_semester
        varchar nama_mahasiswa
        varchar kelas
        int tingkat
        decimal total_jam_terlambat
        decimal total_jam_sakit
        decimal total_jam_izin
        decimal total_jam_bolos
        decimal total_kompensasi_jam
        decimal total_responsi_jam
        decimal total_hutang_jam
        decimal kompensasi_dikerjakan_jam
        decimal sisa_hutang_jam
    }

    sikompen_detail_kompen {
        bigint id PK
        bigint kompen_respon_hub_student_id FK
        date tanggal
        varchar mata_kuliah
        varchar nama_dosen
        varchar jenis_pertemuan
        varchar presensi
        int menit_keterlambatan
        text keterangan
        decimal jam_kompensasi
        decimal jam_responsi
    }

    sikompen_import_tasks {
        bigint id PK
        bigint uploaded_by_admin_id FK
        bigint kompen_respon_hub_import_id FK
        varchar original_filename
        varchar stored_path
        char file_hash
        varchar status
        int progress
        text progress_message
        text error_message
        timestamp queued_at
        timestamp started_at
        timestamp completed_at
        timestamp failed_at
    }

    sikompen_import_audit_logs {
        bigint id PK
        bigint source_import_id FK
        varchar event_type
        varchar actor_name
        varchar actor_email
        varchar periode_semester
        varchar original_filename
        int class_count
        int student_count
        int detail_count
        timestamp occurred_at
    }

    sikompen_sessions {
        varchar id PK
        bigint user_id
        varchar ip_address
        text user_agent
        text payload
        int last_activity
    }

    sikompen_admins o|--o{ sikompen_imports : "mengunggah"
    sikompen_admins o|--o{ sikompen_import_tasks : "memulai"
    sikompen_admins o|--o{ sikompen_admin_setup_windows : "membuka"
    sikompen_imports ||--o{ sikompen_mahasiswa : "asal impor"
    sikompen_mahasiswa ||--o{ sikompen_detail_kompen : "memiliki"
    sikompen_imports o|--o{ sikompen_import_tasks : "hasil tugas"
    sikompen_imports o|--o{ sikompen_import_audit_logs : "direferensikan"
```

## Relasi dan perilaku penghapusan

- Menghapus satu `sikompen_imports` menghapus mahasiswa asal impor tersebut, lalu seluruh detailnya (`cascade`). Ini yang dipakai oleh rollback unggahan terakhir.
- Admin yang dihapus tidak menghapus data impor. Kolom admin pada impor, tugas, dan jendela setup menjadi `NULL` (`nullOnDelete`).
- Audit log mempertahankan catatan rollback walaupun impor asal sudah dihapus. Karena itu `source_import_id` dapat bernilai `NULL` atau tidak lagi merujuk berkas yang bisa diunduh.
- Berkas XLSX tidak disimpan sebagai BLOB di MariaDB. Metadata dan hash-nya berada pada `sikompen_imports`/`sikompen_import_tasks`; berkas berada di volume Docker `sikompen_import_storage`.

## Index penting

- Mahasiswa unik pada `(nim, periode_semester, kelas)`.
- Index mahasiswa untuk filter dan urutan tabel: periode, tingkat, kelas, nama, dan NIM.
- Detail memiliki index mahasiswa dan index `(tanggal, id)` untuk daftar detail terbaru.
- Impor diurutkan oleh `(imported_at, id)`; audit log oleh `occurred_at`; import task oleh `(status, created_at)`.

Nama PHP masih menggunakan `KompenResponHub…` agar namespace aplikasi stabil, sedangkan nama tabel fisiknya sudah menggunakan `sikompen_…`.
