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

Halaman Sikompen dipecah berdasarkan batas tanggung jawab. Semua file domain berada di `resources/js/features/kompen-respon-hub/`, bukan di bawah `pages/`, sehingga resolver Inertia hanya memuat entry page yang sebenarnya.

| Lokasi | Isi dan tanggung jawab |
| --- | --- |
| `resources/js/features/kompen-respon-hub/shared/types.ts` | Kontrak TypeScript untuk props halaman, paginator, mahasiswa, detail, SP, task, audit impor, filter, dan dashboard. |
| `…/shared/constants.ts` | Definisi tab admin dan mahasiswa. |
| `…/shared/lib/formatters.ts` | Format angka jam dan nilai `datetime-local` berbasis zona waktu. |
| `…/shared/components/` | Komponen lintas-tab yang benar-benar generik bagi domain Sikompen: pagination, filter data, panduan/empty state, dan progress bar. |
| `…/dashboard/` | Ringkasan dashboard, cutoff terdekat, worklist, dan profil mahasiswa. |
| `…/upload/` | Form upload workbook, nama file, progres kirim, serta polling antrean impor. |
| `…/kompen-respon/` | Tabel Kompen/Respon, mode perbaikan, dan koreksi mahasiswa. |
| `…/detail-kompen/` | Tabel Detail Kompen serta koreksi detail manual. |
| `…/log-upload/` | Tabel audit upload, download workbook, status versi, dan pemulihan versi yang dipilih. |
| `…/surat-peringatan/` | Kandidat/SP, pengaturan cutoff, penerbitan, edit, dan pembatalan SP. |
| `…/riwayat-aktivitas/` | Kamus label aktivitas, filter riwayat, dan tabel riwayat aktivitas. |
| `…/export/` | Antrean, polling progres, dan download hasil ekspor. |

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
