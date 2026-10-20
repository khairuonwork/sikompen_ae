# Dokumentasi Sikompen

Dokumen dalam folder ini menjelaskan aplikasi pada branch saat ini. Kode dan test tetap menjadi sumber kebenaran teknis; dokumen ini menjelaskan cara bagian-bagiannya bekerja bersama.

| Dokumen | Isi |
| --- | --- |
| [System overview](system-overview.md) | Tujuan, aktor, komponen, dan alur informasi utama. |
| [System design](system-design.md) | Topologi container, queue, consistency, keamanan, dan operasi. |
| [Arsitektur dan logika sistem](system-logic.md) | Batas sistem, sumber data, lifecycle impor/aktivasi, SP, dan retensi. |
| [Panduan fungsionalitas](functionality-guide.md) | Alur yang tersedia untuk admin, mahasiswa, dan pengelola reset akun. |
| [Backend](backend-explained.md) | Surface HTTP, domain action, keamanan, job, API outbound, dan akses. |
| [Peta file backend](backend-files-explained.md) | Lokasi controller, request, action, job, model, command, dan test penting. |
| [Worker dan scheduler](worker-and-scheduler.md) | Queue impor, ekspor, API sync, rekonsiliasi task, dan retensi. |
| [Frontend](frontend-explained.md) | Arsitektur Inertia/React, feature module, tabs, dan perilaku UI. |
| [Peta file frontend](frontend-files-explained.md) | Halaman, feature, shared code, dan output Wayfinder. |
| [Panduan perubahan UI](frontend-guide.md) | Aturan menambah tab atau kolom dan verifikasi frontend. |
| [ERD](erd.md) | Entitas utama dan hubungan data aplikasi. |
| [Framework dan konfigurasi](framework-files.md) | File framework yang dikustomisasi dan variabel environment domain. |
| [Docker lokal](docker-local-guide.md) | Menjalankan, memeriksa, dan mendiagnosis stack lokal. |
| [Kontrak API outbound](api-outbound-integration-proposal.md) | API v1 yang telah diimplementasikan untuk sistem eksternal. |
| [Deployment](deployment-guide.md) | Referensi teknis deployment dan operasi Docker. |

## Panduan PDF per peran

| Dokumen | Untuk siapa |
| --- | --- |
| [Deploy setelah clone](panduan-deploy-setelah-clone.pdf) | Admin server/operator. |
| [Penggunaan API](panduan-penggunaan-api.pdf) | Tim aplikasi yang mengonsumsi API Sikompen. |
| [Panduan Admin](panduan-admin.pdf) | Admin reguler Sikompen. |
| [Panduan Mahasiswa](panduan-mahasiswa.pdf) | Mahasiswa pengguna portal. |
| [Panduan Admin Darurat](panduan-admin-darurat.pdf) | Pemegang akun emergency recovery. |

## Istilah

- **Workbook Kompen/Respon**: XLSX sumber ringkasan dan detail Kompen/Responsi.
- **Roster mahasiswa aktif**: XLSX daftar mahasiswa aktif; dipilih independen untuk setiap periode.
- **Versi aktif**: workbook atau roster yang dipilih sebagai sumber pada periodenya.
- **Data operasional**: mahasiswa/detail yang dipublikasikan dari versi workbook aktif.
- **Data consistency**: perbandingan NIM workbook aktif terhadap roster aktif untuk periode sama.
- **Cutoff**: batas pengerjaan per periode dalam zona waktu `Asia/Jakarta`.
- **Kandidat SP**: mahasiswa dengan hutang efektif tersisa setelah cutoff lewat.
- **SP aktif**: SP terbit yang belum rollback dan masih outstanding.
- **Recycle bin**: versi workbook/roster yang dihapus secara lunak sebelum retensinya berakhir.

## Peta alur cepat

1. Admin mengimpor dan memilih roster aktif bila diperlukan.
2. Admin mengimpor workbook Kompen/Respon; worker memvalidasi lalu menyimpan versi.
3. Admin mengaktifkan versi workbook per periode; tabel, API lokal, ekspor, dan SP menggunakan data operasional itu.
4. Admin mencatat progres/koreksi atau menetapkan cutoff dan menerbitkan SP.
5. Worker membuat ekspor dan artifact API massal; scheduler membersihkan data sementara sesuai retensi.
