# Frontend Sikompen

## Teknologi dan prinsip

Frontend menggunakan Inertia 3, React 19, TypeScript, Tailwind CSS 4, Radix UI, Lucide, dan Sonner. Halaman menerima props Inertia; fetch kecil hanya digunakan untuk overview mahasiswa dan polling task. Semua mutasi memakai route/action Wayfinder dan feedback server.

## Entry dan shell

`resources/js/app.tsx` memasang aplikasi Inertia. Halaman utama `pages/kompen-respon-hub/index.tsx` adalah shell/orchestrator: ia merangkai tab, panel progress, filter, dialog, serta overview tanpa menyimpan tabel/form domain besar di satu file.

Halaman lain mencakup landing, autentikasi/setup admin dan mahasiswa, profil mahasiswa, pengaturan admin, manajemen reset mahasiswa, serta recovery emergency.

## Struktur feature

| Folder | Tanggung jawab |
| --- | --- |
| `upload/` | Workspace dua unggahan: workbook Kompen/Respon dan roster mahasiswa aktif, template, serta progress. |
| `kompen-respon/`, `detail-kompen/` | Tabel ringkasan/detail dan panel koreksi. |
| `list-file/` | Versi workbook/roster, pointer aktif, laporan konsistensi, recycle bin, dan restore. |
| `log-upload/` | Tabel/filter Log Upload dan retensi audit. |
| `surat-peringatan/` | Cutoff, kandidat, SP, finalisasi, dan rollback. |
| `riwayat-aktivitas/` | Filter, tabel audit, dan retensi aktivitas. |
| `export/` | Pembuatan serta polling/dismiss/cancel task ekspor. |
| `dashboard/` | Pencarian global dan overview mahasiswa. |
| `shared/` | Type domain, constants, formatter, feedback, filter, pagination, dan primitive bersama. |

## Perilaku UI penting

- `ImportProgressPanel` memantau task workbook dan roster aktif, termasuk ketika pengguna pindah tab.
- `ExportProgressPanel` memantau task ekspor yang dimiliki session pengguna.
- Tab admin: Upload, Kompen dan Respon, Detail Kompen, List File, Log Upload, Surat Peringatan, dan Riwayat Aktivitas. Mahasiswa hanya mendapat tabel dan detail read-only.
- Halaman mahasiswa menyediakan Profil, ganti password, reset password, serta keluar. Profil menunjukkan ringkasan periode SP aktif atau informasi ketika data belum tersedia.
- Filter, pagination, dan pilihan tab dipertahankan melalui query route; tab List File memiliki beberapa paginator yang harus tetap terisolasi.

## Sistem visual dan checklist

Primitive `components/ui/` berdampak global. Gunakan komponen yang ada, responsifkan tampilan kecil, pertahankan empty state, dan tampilkan error/sukses server melalui flash/toast.

Sebelum merge/release: jalankan `npm run check`, `npm run build`, dan test backend yang memverifikasi perubahan kontrak.
