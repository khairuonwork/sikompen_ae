# Backend files explained

Dokumen ini mencakup file aplikasi yang ditulis atau dipakai langsung oleh fitur Sikompen. File Laravel/vendor generik tidak dijabarkan satu per satu.

## Actions: logika domain

| File | Isi dan tanggung jawab |
| --- | --- |
| `app/Actions/KompenResponHub/ParseKompenResponHubWorkbook.php` | Membaca XLSX memakai PhpSpreadsheet, menemukan blok kelas, membaca sel metadata template, memvalidasi ringkasan serta detail, lalu menghasilkan payload terstruktur dan daftar error. Tidak menulis database. |
| `app/Actions/KompenResponHub/ImportKompenResponHubWorkbook.php` | Menyimpan payload parser dalam satu transaksi: membuat import batch, mengganti mahasiswa pada periode + kelas terkait, memasukkan detail, membuat audit log upload, lalu mengosongkan cache filter. |
| `app/Actions/KompenResponHub/KompenResponHubDataQuery.php` | Builder bersama untuk filter opsi, tabel mahasiswa, tabel detail, dan audit log. Menentukan eager loading, urutan, pencarian, dan cache opsi filter. |

## Controllers: HTTP boundary

| File | Isi dan tanggung jawab |
| --- | --- |
| `app/Http/Controllers/KompenResponHubLandingController.php` | Halaman `/`; menentukan apakah setup admin pertama atau pendaftaran admin tambahan sedang tersedia. |
| `app/Http/Controllers/KompenResponHubController.php` | Merender halaman mahasiswa/admin Inertia dan menyediakan API filter, mahasiswa, detail, serta detail lengkap satu mahasiswa. |
| `app/Http/Controllers/KompenResponHubImportController.php` | Mengunduh template, menerima upload, menyimpan XLSX private, membuat import task, dispatch queue job, dan mengunduh ulang file dari log. |
| `app/Http/Controllers/KompenResponHubImportTaskController.php` | Mengembalikan status satu task impor sebagai JSON untuk polling progress bar. |
| `app/Http/Controllers/KompenResponHubImportRollbackController.php` | Melakukan rollback impor terbaru, menulis audit log rollback, menghapus file, dan membersihkan cache filter. |
| `app/Http/Controllers/KompenResponHubDownloadController.php` | Membangun XLSX dan PDF landscape dari builder yang sudah difilter. Mengatur judul, header, orientasi cetak, tipe angka, serta batas jumlah baris. |
| `app/Http/Controllers/AdminAuthenticationController.php` | Form login/logout guard admin, pembatasan percobaan, dan rotasi session setelah login. |
| `app/Http/Controllers/KompenResponHubAdminSetupController.php` | Halaman setup admin pertama, pendaftaran admin tambahan dengan kode sekali pakai, serta halaman pengaturan pembukaan/penutupan setup. |

## Requests: validasi input

| File | Isi dan tanggung jawab |
| --- | --- |
| `KompenResponHubTableRequest.php` | Menormalisasi dan memvalidasi filter tabel/API: kelas, tingkat, NIM, pencarian, periode, pagination, dan tab. |
| `DownloadKompenResponHubDataRequest.php` | Turunan request tabel yang mewajibkan `periode_semester` untuk ekspor. |
| `StoreKompenResponHubImportRequest.php` | Mewajibkan nama pengunggah dan file XLSX maksimum 20 MB. |
| `StoreAdminLoginRequest.php` | Memvalidasi email dan password ketika login. |
| `StoreKompenResponHubAdminSetupRequest.php` | Memvalidasi email unik, password kuat + konfirmasi, serta kode aktivasi bila diperlukan. |

## Models dan relasi

| File | Tabel | Peran |
| --- | --- | --- |
| `KompenResponHubAdmin.php` | `sikompen_admins` | User autentikasi admin; password memakai cast `hashed`. |
| `KompenResponHubAdminSetupWindow.php` | `sikompen_admin_setup_windows` | Satu jendela pembukaan pendaftaran; menyimpan hash kode dan kedaluwarsa. |
| `KompenResponHubImport.php` | `sikompen_imports` | Metadata impor dan lokasi file; memiliki mahasiswa serta admin pengunggah. |
| `KompenResponHubStudent.php` | `sikompen_mahasiswa` | Ringkasan kompen/respon seorang mahasiswa; memiliki banyak detail. |
| `KompenResponHubDetail.php` | `sikompen_detail_kompen` | Satu baris kejadian/pertemuan yang menghasilkan kompen atau responsi. |
| `KompenResponHubImportTask.php` | `sikompen_import_tasks` | State queue dan progress impor: queued, processing, completed, atau failed. |
| `KompenResponHubImportAuditLog.php` | `sikompen_import_audit_logs` | Jejak tidak langsung dari aksi upload dan rollback. |

Setiap model domain mengembalikan koneksi dari `config('kompen-respon-hub.database_connection')`, sehingga tidak tergantung default database Laravel.

## Resources, job, dan command

| File | Isi dan tanggung jawab |
| --- | --- |
| `app/Http/Resources/KompenResponHubStudentResource.php` | Kontrak JSON ringkasan mahasiswa. |
| `app/Http/Resources/KompenResponHubDetailResource.php` | Kontrak JSON detail dengan atribut mahasiswa yang sudah diratakan. |
| `app/Http/Resources/KompenResponHubImportResource.php` | Metadata impor untuk halaman/admin bila diperlukan. |
| `app/Http/Resources/KompenResponHubImportTaskResource.php` | Data aman untuk polling status task. |
| `app/Http/Resources/KompenResponHubImportAuditLogResource.php` | Data log upload/rollback, termasuk izin menampilkan tombol unduh file. |
| `app/Jobs/ProcessKompenResponHubImport.php` | Worker queue 10 menit: parse file, merespons error validasi, memanggil importer, dan memperbarui progres. |
| `app/Console/Commands/CreateKompenResponHubAdmin.php` | Alternatif CLI untuk membuat admin dengan password interaktif. UI `/admin/setup` adalah jalur yang lebih nyaman untuk penggunaan normal. |

## Views Blade

| File | Isi dan tanggung jawab |
| --- | --- |
| `resources/views/app.blade.php` | Shell Inertia dan entry point Vite. |
| `resources/views/exports/kompen-respon-hub.blade.php` | Template cetak Dompdf untuk PDF A4 landscape, header berulang, dan format angka ekspor. |

## Database dan tests

| Lokasi | Isi dan tanggung jawab |
| --- | --- |
| `database/migrations/` | Membuat tabel domain, session, queue, audit, task, dan migrasi rename ke `sikompen_*`. Migration lama tetap disimpan agar instalasi baru dapat berjalan berurutan. |
| `database/factories/` | Data test untuk admin, setup window, import task, dan audit log. |
| `tests/Feature/KompenResponHubTest.php` | Pengujian alur publik, API, login/setup, upload, queue, rollback, audit, template, dan ekspor. |
