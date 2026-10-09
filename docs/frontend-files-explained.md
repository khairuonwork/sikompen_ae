# Peta File Frontend

## Entry page dan halaman

| File | Tanggung jawab |
| --- | --- |
| `resources/js/app.tsx` | Bootstrap Inertia React, title, navigation progress, tooltip, dan Sonner toast. |
| `pages/kompen-respon-hub/index.tsx` | Shell admin/mahasiswa; header, tab, global search, panel task, dan perakitan feature. |
| `pages/welcome.tsx` | Landing/pemilih akses. |
| `pages/auth/admin-login.tsx` | Login admin dan kontrol tampilkan password. |
| `pages/auth/admin-setup.tsx` | Setup akun admin pertama/tambahan. |
| `pages/admin/settings.tsx` | Pembukaan/penutupan pendaftaran admin. |

## Feature Sikompen

| Lokasi | File penting | Tanggung jawab |
| --- | --- | --- |
| `dashboard/` | `global-student-search.tsx`, `dashboard-panel.tsx`, `student-activity-history.tsx` | Pencarian global admin, profil mahasiswa, dan timeline perubahan. |
| `upload/` | `upload-panel.tsx`, `upload-progress-panel.tsx`, `import-progress-panel.tsx` | Input file/nama pengunggah, proteksi drag drop ke field teks, progres browser, dan polling worker. |
| `kompen-respon/` | `student-table.tsx`, `edit-mode-control.tsx`, `student-correction-panel.tsx` | Tabel ringkasan, badge status, sakelar mode perbaikan, progress, dan override ringkasan. |
| `detail-kompen/` | `detail-table.tsx`, `detail-correction-panel.tsx` | Tabel kejadian Detail Kompen dan override manual. |
| `list-file/` | `import-version-list.tsx` | Versi workbook; rename, download, delete, dan aktivasi versi per periode. |
| `log-upload/` | `import-audit-filter-panel.tsx`, `import-audit-log-table.tsx` | Filter periode/bulan/tahun serta lifecycle audit workbook. |
| `surat-peringatan/` | `warning-panel.tsx` | Periode SP dikelola, cutoff, kandidat/list, finalisasi, edit/rollback SP, dan riwayat surat. |
| `riwayat-aktivitas/` | `activity-panel.tsx` | Label kegiatan yang dapat dibaca, filter actor/event/periode, pagination, dan retensi. |
| `export/` | `export-panel.tsx`, `export-progress-panel.tsx` | Memulai ekspor serta polling/download/cancel/dismiss task. |

## Shared domain dan UI

| Lokasi | Tanggung jawab |
| --- | --- |
| `features/.../shared/types.ts` | Kontrak semua data domain: props Inertia, paginator, mahasiswa, detail, import, cutoff/SP, activity, dan export task. |
| `shared/constants.ts` | Daftar tab berdasarkan peran. |
| `shared/lib/formatters.ts` | Format jam/angka dan tanggal berbasis zona waktu. |
| `shared/components/data-filter-panel.tsx` | Form filter tabel reusable. |
| `shared/components/pagination.tsx` | Pager 15/25/50/100 dan nomor halaman. |
| `shared/components/feedback.tsx` | Guide/collapsible note dan empty state yang kontekstual. |
| `shared/components/progress-bar.tsx` | Visual progres yang dipakai task. |
| `components/ui/` | Primitive aksesibel berbasis Radix/CVA: button, input, select, dialog, card, alert, dan lain-lain. |
| `lib/utils.ts` | `cn()` untuk menggabungkan Tailwind class. |
| `lib/sikompen-url.ts` | Helper URL ketika Sikompen berada di public path gateway. |
| `resources/css/app.css` | Token theme, Tailwind source, palette, dan style global. |

## Generated code dan aturan edit

| Lokasi | Aturan |
| --- | --- |
| `resources/js/actions/` | Generated Wayfinder controller actions. Jangan edit manual. |
| `resources/js/routes/` | Generated Wayfinder named routes. Jangan edit manual. |
| `resources/js/types/` | Type umum starter/app, bukan tempat kontrak domain utama. |

Setelah perubahan route/controller, jalankan:

```bash
php artisan wayfinder:generate --with-form --no-interaction
npm run check
npm run build
```

Saat menambah UI baru, tempatkan di folder feature yang paling dekat. Jangan mengumpulkan tabel/form panjang ke `pages/kompen-respon-hub/index.tsx`.
