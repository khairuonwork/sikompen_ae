# Panduan Fungsionalitas

Hak akses selalu diputuskan backend; menyembunyikan tombol di frontend bukan perlindungan.

## Matriks akses

| Fitur | Admin reguler | Admin emergency | Mahasiswa |
| --- | --- | --- | --- |
| Tabel, detail, filter, dan ekspor scope sendiri | Ya | Tidak | Ya, read-only dan NIM sendiri |
| Upload/kelola workbook Kompen/Respon | Ya | Tidak | Tidak |
| Upload/kelola roster mahasiswa aktif | Ya | Tidak | Tidak |
| Progress, koreksi, cutoff, dan SP | Ya | Tidak | Tidak |
| Reset password mahasiswa | Ya | Tidak | Meminta reset sendiri |
| Recovery password admin | Meminta reset sendiri | Terbitkan/batalkan kode | Tidak |
| Retensi, Log Upload, Riwayat Aktivitas | Ya | Tidak | Tidak |

## 1. Dua jenis impor

**Workbook Kompen/Respon** berisi ringkasan dan detail pekerjaan. Sistem memeriksa template, periode, NIM, detail, total jam, archive, dan formula sebelum menyimpan versi.

**Daftar mahasiswa aktif** berisi NIM, nama, dan kelas dalam blok kelas untuk satu periode. Sistem memeriksa struktur template, periode tunggal, NIM duplikat, dan formula.

Keduanya diproses di worker dan menghasilkan versi file; keduanya belum dipakai sampai admin memilih **Aktifkan** pada List File.

## 2. List File, konsistensi, dan recycle bin

- Workbook dan roster memiliki daftar versi serta pointer aktif yang independen per periode.
- Aktivasi workbook memperbarui data Kompen/Respon operasional. Aktivasi roster mengatur master roster periode itu tanpa menimpa data Kompen/Respon.
- Laporan konsistensi membandingkan NIM antara keduanya; laporan ini tidak mengganti data secara otomatis.
- Menghapus versi inactive memindahkannya ke recycle bin. Admin dapat memulihkannya sebelum retensi berakhir.
- Versi yang masih dipakai tidak dapat dihapus. Workbook aktif tidak dapat dilepas bila periode masih memiliki SP aktif outstanding.

## 3. Kompen, Respon, dan Detail

Tabel dapat difilter berdasarkan nama/NIM, tingkat, kelas, periode, dan jumlah baris. Admin dapat memasukkan progress serta override ringkasan; nilai efektif tidak boleh bertentangan dengan batas jam bisnis. Detail Kompen dapat dikoreksi melalui override, sementara data asli workbook tetap menjadi referensi audit.

Pencarian global admin membuka overview mahasiswa yang memuat sumber impor, detail, progres, SP, dan aktivitas terbaru.

## 4. Cutoff dan Surat Peringatan

1. Pilih periode dan simpan cutoff dalam `Asia/Jakarta` (minimal lima menit ke depan).
2. Setelah cutoff lewat, periksa kandidat yang masih memiliki sisa hutang.
3. Finalisasi menerbitkan SP aktif untuk kandidat outstanding.
4. Koreksi data/progress dapat menyelesaikan SP; rollback SP atau finalisasi harus disertai alasan dan meninggalkan riwayat.

Periode yang dikelola dapat berbeda dari periode yang sedang dilihat pada daftar SP untuk kebutuhan histori.

## 5. Log dan retensi

**Log Upload** mencatat lifecycle workbook dan roster—upload, aktivasi, release, rename, delete, restore, dan purge. **Riwayat Aktivitas** mencatat perubahan domain seperti progress, cutoff, SP, pengaturan, atau export.

Admin mengatur tiga retensi terpisah: Riwayat Aktivitas, Log Upload, dan recycle bin workbook/roster. Setelah scheduler memurge data yang melewati batas, data tersebut tidak dapat dikembalikan.

## 6. Ekspor

Ekspor menggunakan filter resource saat ini. XLSX memiliki batas baris konfigurasi; PDF landscape tidak dibatasi oleh batas aplikasi yang sama. Task diproses asynchronous, dan hasil hanya dapat diunduh oleh session pembuatnya hingga retensi berakhir.

## 7. Akun dan pemulihan

- Mahasiswa login dengan NIM dan password awal NIM, lalu wajib menggantinya. Mereka dapat melihat profil, data sendiri, mengganti password, atau mengajukan reset.
- Reset mahasiswa menghasilkan kode yang dikelola admin, berlaku 24 jam dan memiliki batas percobaan. Admin dapat refresh atau membatalkan permintaan.
- Admin reguler dapat mengganti username/password; perubahan kredensial membatalkan session admin lain. Admin dapat mengajukan reset password tanpa membocorkan keberadaan akun.
- Admin emergency memiliki area recovery terbatas. Ia dapat menerbitkan kode reset enam digit untuk admin reguler, berlaku 15 menit dan dibatasi percobaan; ia tidak memiliki akses ke data operasional.
