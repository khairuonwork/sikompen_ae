# Peta File Backend

Dokumen ini memetakan file aplikasi yang membentuk fitur Sikompen; framework dan vendor generik tidak dirinci satu per satu.

## HTTP dan akses

| Lokasi/kelompok | Tanggung jawab |
| --- | --- |
| `routes/web.php` | Halaman admin/mahasiswa, import, lifecycle SP, export, pengaturan, dan recovery. |
| `routes/api.php` | API internal berbasis session serta API outbound `/api/sikompen/v1`. |
| `KompenResponHubController` | Props Inertia, tabel/API internal, pencarian global, dan overview mahasiswa. |
| `KompenResponHubImport*Controller` | Upload, activate/release, download, rename, recycle bin, dan polling workbook. |
| `KompenResponHubActiveStudentImportController` | Template, upload, activate, restore, download, dan polling roster. |
| `KompenResponHubLifecycleController` | Cutoff, progress, override, finalisasi/rollback, dan SP. |
| `KompenResponHubExportController` | Pembuatan, status, unduh, batal, dan dismiss ekspor. |
| `KompenResponHubOutboundApiController` | Mahasiswa tunggal, sync run, status, dan unduh artifact API. |
| `Student*Controller`, `Admin*Controller`, `EmergencyAdminRecoveryController` | Login, ganti/reset password, setup, kredensial, dan recovery. |
| `app/Http/Requests/` | Validasi untuk seluruh mutasi, filter, autentikasi, dan kontrak API. |

## Domain, job, dan command

| Kelompok | File penting |
| --- | --- |
| Workbook | `ParseKompenResponHubWorkbook`, `ValidateKompenResponHubWorkbookArchive`, `ImportKompenResponHubWorkbook`, `ActivateKompenResponHubImportVersion`, `ReleaseKompenResponHubImportVersion`. |
| Roster | `ParseKompenResponHubActiveStudentWorkbook`, `ImportKompenResponHubActiveStudents`, `ActivateKompenResponHubActiveStudentImport`. |
| Data/lifecycle | `KompenResponHubDataQuery`, `BuildKompenResponHubDataConsistencyReport`, `FinalizeKompenResponHubCutoff`, `RecordKompenResponHubActivity`. |
| Job | `ProcessKompenResponHubImport`, `ProcessKompenResponHubActiveStudentImport`, `GenerateKompenResponHubExport`, `GenerateKompenResponHubApiSyncArtifact`. |
| Command | `Reconcile...Tasks` dan seluruh `Purge...`. Client API dikelola melalui environment deployment. |

## Model dan resource

| Kelompok | Contoh file |
| --- | --- |
| Sumber file | `KompenResponHubImport`, `ActiveImport`, `ActiveStudentImport`, `ActiveStudentImportReference`, `ActiveStudent`, dan `ImportAuditLog`. |
| Data operasional | `Student`, `Detail`, `StudentProgress`, `StudentSummaryOverride`, `DetailOverride`, `PeriodCutoff`, dan `WarningLetter`. |
| Akun | `Admin`, `AdminSetupWindow`, `StudentAccount`, serta model reset admin/mahasiswa. |
| Task/integrasi | `ImportTask`, `ActiveStudentImportTask`, `ExportTask`, `ApiClient`, dan `ApiSyncRun`. |
| Serialisasi | `app/Http/Resources/` menyediakan resource tabel, file/task, roster, activity, dan payload API outbound. |

## Middleware dan konfigurasi

- `EnsureSikompenApiClient`, `EnsureSikompenDataAccess`, dan `EnsureSikompenStudentPasswordChanged` menjaga batas API/session.
- Middleware admin standar dan emergency memisahkan panel operasional dari area recovery.
- `config/kompen-respon-hub.php` adalah konfigurasi import, queue, retention, API sync, dan emergency admin.
- `bootstrap/app.php` mendaftarkan alias middleware; `AppServiceProvider` mendaftarkan rate limiter.

## Test feature utama

| Test | Cakupan |
| --- | --- |
| `KompenResponHubTest` | Workbook, tabel, export, audit, retensi, dan hardening. |
| `ActiveStudentImportTest` | Roster, pointer aktif per periode, dan lifecycle file. |
| `RecycleBinAndTaskDiagnosticsTest` | Restore, file gagal, konsistensi data, dan diagnostic. |
| `KompenResponHubLifecycleTest` | Cutoff, progress, override, dan SP. |
| `KompenResponHubOutboundApiTest` | API client dan artifact sync. |
| `StudentAuthenticationTest`, `PasswordResetTest`, `AdminAccessTest`, `AdminSetupTest` | Akses dan recovery akun. |
