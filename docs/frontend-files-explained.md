# Peta File Frontend

## Halaman dan entry

| Lokasi | Fungsi |
| --- | --- |
| `resources/js/app.tsx` | Bootstrap Inertia, CSS, dan resolver halaman. |
| `pages/welcome.tsx` | Landing pemilih akses. |
| `pages/kompen-respon-hub/index.tsx` | Shell panel Kompen/Respon untuk admin dan mahasiswa. |
| `pages/mahasiswa/profile.tsx` | Profil mahasiswa dan status data periode aktif. |
| `pages/admin/settings.tsx`, `student-password-resets.tsx`, `emergency-recovery.tsx` | Pengaturan, reset mahasiswa, dan recovery emergency. |
| `pages/auth/` | Login/setup/ganti password/reset untuk admin dan mahasiswa; form reset bersama. |

## Feature Sikompen

| Lokasi | Fungsi |
| --- | --- |
| `features/.../upload/` | `upload-workspace`, panel workbook/roster, template menu, dan progress impor. |
| `features/.../kompen-respon/` | Tabel mahasiswa serta mode koreksi progress/ringkasan. |
| `features/.../detail-kompen/` | Tabel detail dan correction panel. |
| `features/.../list-file/` | Versi workbook/roster, report konsistensi, dan recycle bin. |
| `features/.../log-upload/` | Tabel/filter Log Upload dan panel retensi audit. |
| `features/.../surat-peringatan/` | Panel cutoff dan SP. |
| `features/.../riwayat-aktivitas/` | Tabel/filter/retensi aktivitas. |
| `features/.../export/` | Panel request dan progress ekspor. |
| `features/.../dashboard/` | Pencarian global dan overview mahasiswa. |

## Shared dan generated code

- `features/.../shared/` berisi type domain, constants tab, formatter, filter/pagination, dan komponen feedback.
- `components/ui/` adalah primitive UI reusable; dampaknya lintas halaman.
- `hooks/`, `lib/`, dan `types/` menyediakan helper aplikasi, utilitas, dan deklarasi TypeScript.
- `actions/`, `routes/`, dan `wayfinder/` adalah output generated. Jangan ubah manual; generate ulang setelah kontrak route/controller berubah.
