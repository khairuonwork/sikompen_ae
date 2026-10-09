# Panduan Fungsionalitas

Dokumen ini menjelaskan perilaku aplikasi dari sudut pandang pengguna. Hak akses backend tetap menjadi sumber otorisasi; menyembunyikan tombol di frontend bukan satu-satunya perlindungan.

## Matriks akses

| Fitur | Admin | Mahasiswa |
| --- | --- | --- |
| Lihat Kompen dan Respon / Detail Kompen | Ya | Ya, read-only |
| Filter dan pagination | Ya | Ya |
| Download template | Ya | Tidak |
| Upload, rename, delete, atau aktifkan versi XLSX | Ya | Tidak |
| Input progres, koreksi ringkasan/detail | Ya | Tidak |
| Kelola cutoff dan SP | Ya | Tidak |
| Lihat Log Upload dan Riwayat Aktivitas | Ya | Tidak |
| Buat ekspor XLSX/PDF | Ya | Ya, sesuai scope aksesnya |

Saat proxy Si-Admin aktif, scope mahasiswa dapat dipaksa berdasarkan NIM dari session gateway. Jangan mengandalkan filter URL untuk membatasi data mahasiswa.

## 1. Upload Dokumen

1. Admin mengisi nama pengunggah dan memilih `.xlsx` dari template Sikompen.
2. Sistem memvalidasi ukuran, tipe file, archive XLSX, formula, struktur template, periode, NIM, detail, dan total jam.
3. Browser menampilkan progres pengiriman; setelah diterima, panel task menunjukkan proses queue sampai selesai atau gagal.
4. Upload berhasil menghasilkan **versi file**, bukan langsung mengganti data tabel.

Jika gagal, baca alasan pada task. Unggah ulang workbook yang telah dibetulkan. Jangan mengubah workbook langsung saat task masih `queued`/`processing`.

## 2. List File

List File adalah pusat pengelolaan versi workbook.

- Admin dapat memfilter, mengubah nama tampilan, mengunduh ulang, dan menghapus versi.
- Admin memilih **Aktifkan** untuk menjadikan satu versi sebagai sumber data periode itu.
- Hanya boleh ada satu versi aktif pada satu periode. Mengaktifkan versi lain pada periode yang sama menggantikan data aktif periode tersebut saja.
- File periode lain tetap aktif secara mandiri; tidak ada konflik lintas periode.
- File aktif sebaiknya tidak dihapus sebelum versi pengganti siap dipilih.

## 3. Kompen dan Respon

Tabel ini menunjukkan ringkasan total kompen/responsi, jam yang sudah dikerjakan, dan sisa hutang efektif. Filter dapat memakai nama/NIM, tingkat, kelas, periode, serta jumlah baris per halaman (15, 25, 50, atau 100).

**Mode perbaikan data** membuka form manual tanpa membuat tombol edit memenuhi setiap baris:

- **Progress mahasiswa**: jam kompen/responsi yang telah dikerjakan, waktu terakhir mengerjakan, dan alasan perubahan.
- **Koreksi ringkasan**: total kompen/responsi efektif dan alasan perubahan.

Setiap nilai dicek agar konsisten dengan batas bisnis lalu dicatat ke Riwayat Aktivitas. Pencarian global admin dapat membuka profil mahasiswa untuk melihat sumber impor, detail, progres, SP, dan aktivitas terakhir.

## 4. Detail Kompen

Menampilkan detail kejadian akademik yang membentuk total Kompen/Responsi. Dalam mode perbaikan, admin dapat mengoreksi atribut seperti mata kuliah, dosen, tanggal, presensi, keterlambatan, jam, dan keterangan. Perubahan disimpan sebagai override; baris asal workbook tetap menjadi referensi audit.

## 5. Surat Peringatan

Panel ini sengaja memisahkan dua pilihan periode:

| Pilihan | Fungsi |
| --- | --- |
| **Periode SP dikelola** | Periode yang cutoff-nya dibuat/diubah dan dapat difinalisasi. |
| **Periode list mahasiswa** | Periode yang sedang diperiksa dalam daftar kandidat/SP; boleh berbeda untuk melihat histori. |

Alur yang dianjurkan:

1. Pilih periode SP dan simpan cutoff dalam waktu Asia/Jakarta.
2. Setelah cutoff lewat, periksa daftar mahasiswa yang masih memiliki sisa hutang sebagai kandidat.
3. Bila data sudah benar, tekan **Finalisasi SP**. Kandidat outstanding diterbitkan sebagai SP aktif.
4. Bila ditemukan kesalahan, perbaiki data/cutoff atau rollback SP/finalisasi dengan alasan. Riwayat tetap ada, tetapi status rollback tidak dihitung sebagai SP aktif dan tidak boleh dikirim sebagai data SP integrasi.

SP aktif dapat diberi catatan; perubahan progress dapat mengubah resolusi menjadi selesai apabila seluruh hutang telah lunas. Riwayat Surat Peringatan mempertahankan SP terbit dan rollback untuk audit.

## 6. Log Upload

Log Upload adalah jejak lifecycle **file**: upload, aktivasi, rename, restore/penggantian, rollback, dan delete. Gunakan filter periode atau bulan/tahun aktivitas untuk mengurangi kepadatan log. Kolom periode menunjukkan periode yang dibawa workbook, bukan sekadar waktu upload.

Gunakan **Riwayat Aktivitas** bila yang dicari adalah aksi domain lebih luas—misalnya koreksi jam, perubahan cutoff, finalisasi, pembatalan SP, atau request ekspor.

## 7. Riwayat Aktivitas

Setiap aktivitas memiliki subject yang dapat dibaca manusia (misalnya `Mahasiswa: ...`, `Workbook: ...`, `Periode: ...`, atau `Ekspor: ...`), actor, konteks periode/kelas/NIM bila relevan, alasan, dan snapshot before/after bila ada perubahan nilai.

Admin dapat memfilter jenis aktivitas, actor, periode, dan pagination. Kebijakan retensi hari juga diatur dari panel ini. Penghapusan retensi bersifat otomatis dan permanen, sehingga tentukan durasi sesuai kebutuhan audit institusi.

## 8. Ekspor XLSX dan PDF

Ekspor memakai filter tabel saat ini, tetapi filter tidak wajib: tanpa filter berarti semua data pada resource tersebut.

- **XLSX**: dibatasi konfigurasi jumlah baris sebagai pelindung memori.
- **PDF**: landscape dan tidak dibatasi jumlah baris oleh aplikasi.
- Task diproses satu per satu di worker. Notifikasi task dapat ditutup setelah terminal atau dibatalkan saat masih berjalan.
- Nama file mencantumkan resource dan filter efektif agar mudah dibedakan saat diunduh.

Jika ekspor gagal, gunakan pesan task sebagai petunjuk; periksa filter dan ulangi. Jika worker/scheduler bermasalah, administrator perlu mengecek container queue.

## 9. Pengaturan Admin

Pada standalone mode, setup admin pertama terbuka otomatis ketika belum ada akun admin. Admin aktif dapat membuka jendela setup tambahan dengan kode sekali pakai dan masa berlaku terbatas. Login, setup, upload, dan aksi sensitif dilindungi throttle serta validasi server.

Dalam mode gateway Si-Admin, akses admin datang dari session proxy yang telah diverifikasi HMAC; halaman setup/login lokal dapat dinonaktifkan sesuai konfigurasi deployment.
