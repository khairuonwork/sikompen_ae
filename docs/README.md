# Dokumentasi Sikompen

Dokumen di folder ini menjelaskan implementasi Sikompen pada branch saat ini. Gunakan dokumen berikut sebagai titik mulai, lalu masuk ke dokumen yang sesuai dengan kebutuhan.

| Dokumen | Isi |
| --- | --- |
| [Arsitektur dan logika sistem](system-logic.md) | Batas sistem, state data, alur impor–aktivasi–SP–ekspor, dan data flow. |
| [Panduan fungsionalitas](functionality-guide.md) | Perilaku setiap fitur dari sudut pandang admin dan mahasiswa. |
| [Backend](backend-explained.md) | Struktur HTTP, domain action, model, keamanan, serta API. |
| [Worker dan scheduler](worker-and-scheduler.md) | Queue impor/ekspor, task state, pemulihan task macet, dan retensi. |
| [Frontend](frontend-explained.md) | Inertia, React, page shell, feature module, state, dan UX. |
| [File backend](backend-files-explained.md) | Peta tanggung jawab file PHP yang penting. |
| [File frontend](frontend-files-explained.md) | Peta folder dan file React/TypeScript yang penting. |
| [ERD](erd.md) | Entitas database dan hubungan utamanya. |
| [Deployment](deployment-guide.md) | Docker Compose, storage volume, service, dan operasi deployment. |
| [Framework yang dikustomisasi](framework-files.md) | Konfigurasi Laravel yang diubah untuk Sikompen. |

## Istilah yang dipakai konsisten

- **Workbook / versi file**: satu XLSX yang sudah lolos impor. Mengunggahnya tidak otomatis mengganti data tampil.
- **Data aktif**: satu versi workbook yang dipilih untuk satu periode. Hanya data aktif yang menjadi sumber tabel mahasiswa, detail, kandidat SP, API, dan ekspor.
- **Periode**: string akademik dari template, misalnya `2026/2027 Gasal`.
- **Cutoff**: batas waktu pengerjaan Kompen/Responsi untuk satu periode, selalu dihitung dalam `Asia/Jakarta`.
- **Kandidat SP**: mahasiswa dengan sisa hutang setelah cutoff lewat, sebelum finalisasi SP dilakukan.
- **SP aktif**: kandidat yang sudah diterbitkan saat finalisasi, belum di-rollback, dan belum selesai seluruh hutangnya.
- **Riwayat aktivitas**: audit trail perubahan penting; ini berbeda dari Log Upload yang khusus mencatat versi workbook.

## Sumber kebenaran

1. Kode dan test adalah sumber teknis utama.
2. `system-logic.md` menjelaskan hubungan antarfitur dan state yang seharusnya terlihat oleh operator.
3. `functionality-guide.md` menjelaskan perilaku yang diharapkan oleh pengguna.
4. Dokumen deployment tidak menggantikan konfigurasi rahasia di `.env`.
