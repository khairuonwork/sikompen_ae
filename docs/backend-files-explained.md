# Peta File Backend

Dokumen ini mencakup file aplikasi yang langsung membentuk fitur Sikompen. File framework/vendor generik tidak dicantumkan satu per satu.

## HTTP: controller dan request

| Lokasi | Tanggung jawab |
| --- | --- |
| `routes/web.php` | Halaman, aksi admin, import/version, lifecycle SP, export task, setup, dan gateway. |
| `routes/api.php` | API filter/mahasiswa/detail dengan limiter dan proxy session. |
| `app/Http/Controllers/KompenResponHubController.php` | Props halaman admin/mahasiswa, endpoint data, global search, profil mahasiswa, filter periode SP. |
| `KompenResponHubImportController.php` | Template, upload, download workbook, rename tampilan, dan delete versi. |
| `KompenResponHubImportActivationController.php` | Memilih satu versi data aktif per periode. |
| `KompenResponHubImportTaskController.php` | JSON polling progress import. |
| `KompenResponHubLifecycleController.php` | Cutoff, finalisasi/rollback, progress, override ringkasan/detail, catatan dan rollback SP. |
| `KompenResponHubExportController.php` | Membuat, menampilkan status, mendownload, membatalkan, dan dismiss task export. |
| `KompenResponHubActivityRetentionController.php` | Menyimpan kebijakan retensi aktivitas. |
| `AdminAuthenticationController.php` | Login/logout admin standalone. |
| `KompenResponHubAdminSetupController.php` | Setup admin pertama, setup tambahan, dan pengaturan jendela setup. |
| `KompenResponHubLandingController.php` | Landing akses standalone/proxy. |
| `SiAdminProxyAccessController.php` | Membentuk session Sikompen setelah HMAC gateway lolos. |
| `app/Http/Requests/*.php` | Validasi khusus untuk tiap operasi. `KompenResponHubTableRequest` adalah filter utama; request lain mengamankan mutasi import, lifecycle, export, dan autentikasi. |

## Actions domain

| File | Tanggung jawab |
| --- | --- |
| `Actions/KompenResponHub/ParseKompenResponHubWorkbook.php` | Parser template XLSX dan validasi data bisnis. |
| `…/ValidateKompenResponHubWorkbookArchive.php` | Proteksi archive XLSX sebelum parsing. |
| `…/ImportKompenResponHubWorkbook.php` | Menyimpan versi import, quality report, audit upload, dan mengganti rows data aktif saat diminta aktivator. |
| `…/ActivateKompenResponHubImportVersion.php` | Transaksi pemilihan versi aktif dan penggantian data live per periode. |
| `…/KompenResponHubDataQuery.php` | Query reusable untuk semua daftar/filter/export. |
| `…/FinalizeKompenResponHubCutoff.php` | Menerbitkan/menyelaraskan SP saat finalisasi periode. |
| `…/BuildKompenResponHubExport.php` | Data query dan rendering XLSX/PDF landscape. |
| `…/RecordKompenResponHubActivity.php` | Satu pintu pencatatan activity log. |
| `Actions/SiAdminProxy/SiAdminProxySignature.php` | Canonical payload + HMAC SHA-256. |
| `Actions/SiAdminProxy/SiAdminProxyAccess.php` | Actor, role, dan scope NIM session proxy. |
| `Actions/SiAdminProxy/RecordSiAdminProxyAccess.php` | Audit akses gateway/proxy. |

## Jobs, command, dan scheduler

| File | Tanggung jawab |
| --- | --- |
| `Jobs/ProcessKompenResponHubImport.php` | Worker impor, state/progres task, validasi, storage, audit, dan kegagalan aman. |
| `Jobs/GenerateKompenResponHubExport.php` | Worker ekspor serial dengan `WithoutOverlapping`, progress, cancel-safe cleanup, dan error logging. |
| `Console/Commands/ReconcileKompenResponHubTasks.php` | Menandai task import/export macet sebagai gagal setiap lima menit. |
| `…/PurgeKompenResponHubExports.php` | Menghapus hasil ekspor kedaluwarsa tiap jam. |
| `…/PurgeKompenResponHubImportTasks.php` | Menghapus record task impor terminal yang kedaluwarsa tiap hari. |
| `…/PurgeKompenResponHubActivityLogs.php` | Menjalankan retensi Riwayat Aktivitas tiap hari. |
| `routes/console.php` | Sumber definisi schedule dan `withoutOverlapping`. |

## Model dan resource

| Kelompok | File utama | Fungsi |
| --- | --- | --- |
| Akses | `KompenResponHubAdmin`, `KompenResponHubAdminSetupWindow` | Account admin dan setup window. |
| Import | `Import`, `ActiveImport`, `ImportTask`, `ImportAuditLog` | Versi file, pointer aktif, progres worker, dan lifecycle audit. |
| Data live | `Student`, `Detail` | Data Kompen/Responsi yang aktif. |
| Koreksi | `StudentProgress`, `StudentSummaryOverride`, `DetailOverride` | Nilai efektif/override manual. |
| Lifecycle | `PeriodCutoff`, `WarningLetter` | Cutoff, kandidat, SP issued/cancelled, snapshot dan resolusi. |
| Operasi | `ExportTask`, `ActivityLog`, `SystemSetting` | Task export, audit luas, dan retensi. |
| JSON | `app/Http/Resources/KompenResponHub*Resource.php` | Bentuk aman untuk Inertia/API/polling tanpa membocorkan kolom internal. |

## Middleware, rule, dan konfigurasi

| Lokasi | Fungsi |
| --- | --- |
| `Middleware/EnsureSikompenAdminAccess.php` | Otorisasi admin lokal/proxy. |
| `Middleware/EnsureSiAdminProxySession.php` | Memastikan proxy session tersedia bila mode proxy aktif. |
| `Middleware/ValidateSiAdminProxyRequest.php` | HMAC, timestamp, nonce, role gateway. |
| `Middleware/EnsureSikompenStandalone.php` | Mengizinkan route lokal hanya di standalone mode. |
| `Rules/SafeSearchTerm.php` | Validasi search/text input yang aman. |
| `config/kompen-respon-hub.php` | Koneksi domain, limit import/archive, limit XLSX export, retensi, dan ambang task macet. |
| `config/auth.php`, `database.php`, `session.php`, `queue.php`, `filesystems.php` | Integrasi guard admin, koneksi `kompen_db`, session, queue, dan private storage. |

## Test yang paling relevan

- `tests/Feature/KompenResponHubTest.php`: alur aplikasi utama dari auth, import, aktivasi, lifecycle, audit, export, dan keamanan.
- `tests/Feature/SiAdminProxyAccessTest.php`: kontrak access gateway/HMAC.
- Factory domain dalam `database/factories/` mendukung fixture yang eksplisit dan tidak bergantung pada database lokal.

Untuk hubungan antarkomponen, baca [system-logic.md](system-logic.md). Untuk daftar fitur pengguna, baca [functionality-guide.md](functionality-guide.md).
