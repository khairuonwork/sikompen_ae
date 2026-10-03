# Frontend files explained

## Entry point dan halaman

| File | Isi dan tanggung jawab |
| --- | --- |
| `resources/js/app.tsx` | Membuat aplikasi Inertia React, judul dokumen, progress navigation, `TooltipProvider`, dan toast global. |
| `resources/js/pages/welcome.tsx` | Halaman pemilih akses Admin/Mahasiswa. |
| `resources/js/pages/kompen-respon-hub/index.tsx` | Page shell Sikompen: menerima props Inertia, menyimpan state lintas-tab, menyusun header/navigasi, lalu memasang panel tab yang sesuai. Tidak menyimpan implementasi tabel atau form domain. |
| `resources/js/pages/auth/admin-login.tsx` | Form login admin dan kontrol tampil/sembunyikan password. |
| `resources/js/pages/auth/admin-setup.tsx` | Form setup admin pertama atau pendaftaran admin baru memakai kode aktivasi. |
| `resources/js/pages/admin/settings.tsx` | Pengaturan pembukaan jendela pendaftaran admin dan penyalinan kode aktivasi. |

## Styling dan komponen bersama

| Lokasi | Isi dan tanggung jawab |
| --- | --- |
| `resources/css/app.css` | Entry Tailwind, token warna/radius, source scanning, dan varian dark mode. Ini tempat utama untuk mengubah tema global. |
| `resources/js/components/ui/` | Komponen presentasi generik seperti `button.tsx`, `card.tsx`, `input.tsx`, `select.tsx`, `alert.tsx`, `dialog.tsx`, `sonner.tsx`, dan `tooltip.tsx`. Gunakan ulang sebelum membuat komponen baru. |
| `resources/js/lib/utils.ts` | Helper `cn()` untuk menggabungkan class Tailwind dengan aman. |
| `resources/js/hooks/use-appearance.tsx` | Penyimpanan dan penerapan preferensi tampilan terang/gelap. |
| `resources/js/hooks/use-flash-toast.ts` | Helper pembacaan flash message untuk toast. |
| `resources/js/hooks/use-mobile*.tsx` | Helper responsif/mobil yang disediakan starter kit. |

## Modul halaman Sikompen

Halaman Sikompen dipecah berdasarkan batas tanggung jawab. Semua file di bawah tetap private untuk domain Sikompen dan tidak dipakai sebagai komponen UI generik lintas aplikasi.

| Lokasi | Isi dan tanggung jawab |
| --- | --- |
| `resources/js/pages/kompen-respon-hub/types.ts` | Kontrak TypeScript untuk props halaman, paginator, mahasiswa, detail, SP, task, audit impor, filter, dan dashboard. |
| `resources/js/pages/kompen-respon-hub/constants.ts` | Definisi tab admin dan mahasiswa. |
| `resources/js/pages/kompen-respon-hub/lib/formatters.ts` | Format angka jam dan nilai `datetime-local` berbasis zona waktu. |
| `…/components/tables.tsx` | Pager serta tabel Kompen/Respon, Detail Kompen, dan Log Upload. |
| `…/components/tasks.tsx` | Progress bar, polling antrean impor/ekspor, dan helper antrekan ekspor. |
| `…/components/upload-panel.tsx` | Form upload workbook, tampilan nama file, dan pencegahan drop file ke kolom nama admin. |
| `…/components/records.tsx` | Filter data, sakelar mode perbaikan, serta panel koreksi mahasiswa/detail. |
| `…/components/warnings.tsx` | Tabel kandidat/SP, pengaturan cutoff, serta form penerbitan, edit, atau pembatalan SP. |
| `…/components/activity.tsx` | Kamus label aktivitas, filter riwayat, dan tabel riwayat aktivitas. |
| `…/components/dashboard.tsx` | Ringkasan dashboard, cutoff terdekat, worklist, dan profil mahasiswa. |
| `…/components/feedback.tsx` | Panduan tabel yang dapat dibuka/tutup dan empty state. |

## Routing type-safe

| Lokasi | Isi dan aturan |
| --- | --- |
| `resources/js/actions/` | Hasil generate Wayfinder untuk controller actions, termasuk `.form()` untuk POST/DELETE dan `.url()` untuk tautan. Jangan edit manual; jalankan `php artisan wayfinder:generate --with-form --no-interaction` setelah route/controller berubah. |
| `resources/js/routes/` | Hasil generate Wayfinder untuk named routes. Jangan edit manual. |
| `vite.config.ts` | Mengaktifkan plugin Wayfinder, Inertia, React, Tailwind, dan aturan build/lint. |

## TypeScript

| File | Isi dan tanggung jawab |
| --- | --- |
| `resources/js/types/index.ts` | Re-export type bersama. |
| `resources/js/types/ui.ts` | Type UI starter-kit yang dipakai komponen umum. |
| `resources/js/types/global.d.ts` | Deklarasi global/props Inertia. |
| `resources/js/types/vite-env.d.ts` | Type environment Vite. |

## Catatan perubahan aman

- Ubah halaman domain di `resources/js/pages/…`, bukan output Wayfinder.
- Jika sebuah komponen dipakai hanya di halaman Sikompen, letakkan komponen baru dekat halaman atau buat folder komponen domain yang jelas setelah benar-benar diperlukan.
- Bila mengubah JSX atau kelas Tailwind, jalankan `npm run check` dan `npm run build` sebelum deployment.
- Tambahkan komponen baru ke folder domain yang paling dekat dengan tanggung jawabnya; jangan mengembalikan implementasi tabel/form besar ke `index.tsx`.
