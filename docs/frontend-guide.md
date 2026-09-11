# Frontend guide

Panduan ini menjelaskan lokasi perubahan bila UI Sikompen ingin didesain ulang tanpa mengubah kontrak backend.

## Peta perubahan UI

| Kebutuhan | File utama | Catatan |
| --- | --- | --- |
| Mengubah halaman pemilih akses | `resources/js/pages/welcome.tsx` | Pertahankan tautan Wayfinder untuk Admin, Mahasiswa, dan setup. |
| Mengubah layout tabel/filter/tab Sikompen | `resources/js/pages/kompen-respon-hub/index.tsx` | Ini halaman inti bersama untuk mode admin dan mahasiswa. Jangan menghapus pembatasan `isAdmin`. |
| Mengubah tampilan login | `resources/js/pages/auth/admin-login.tsx` | Pertahankan field `email`, `password`, form action, dan tampilan error server. |
| Mengubah tampilan setup admin | `resources/js/pages/auth/admin-setup.tsx` | Pertahankan `email`, `password`, `password_confirmation`, dan `activation_code` kondisional. |
| Mengubah layar pengaturan admin | `resources/js/pages/admin/settings.tsx` | Jangan tampilkan ulang kode aktivasi dari state permanen; kode memang hanya flash sekali. |
| Mengubah warna, font, radius, mode gelap | `resources/css/app.css` | Ubah CSS variable dalam `:root` dan `.dark`, atau `@theme` bila menambah token Tailwind. |
| Mengubah bentuk tombol/card/input/select | `resources/js/components/ui/*.tsx` | Dampaknya global; cek seluruh halaman setelah mengubahnya. |
| Menambah icon | File halaman terkait | Import icon dari `lucide-react`. |

## Menambah section atau tab baru

Contoh jika menambah tampilan statistik untuk admin:

1. Tambahkan nilai tab di `adminTabs` pada `index.tsx`.
2. Tambahkan nilai yang sama ke rule `tab` pada `app/Http/Requests/KompenResponHubTableRequest.php`.
3. Tambahkan data prop yang diperlukan pada `KompenResponHubController::index()`.
4. Tambahkan type prop dan section JSX pada `index.tsx`.
5. Jika butuh route baru, daftarkan di `routes/web.php`, jalankan generator Wayfinder, lalu import fungsi route hasil generate ke React.
6. Tambahkan feature test untuk akses admin dan perilaku data.

Jangan hanya menambah tab di JSX: server perlu mengenali tab tersebut agar URL, validasi, dan data awal tetap konsisten.

## Menambah atau mengubah kolom tabel

1. Ubah kontrak JSON pada Resource backend yang relevan.
2. Tambahkan properti di type `Student` atau `Detail` pada `index.tsx`.
3. Tambahkan header dan cell tabel di komponen `StudentTable` atau `DetailTable`.
4. Bila kolom adalah jam, pakai helper `number()` agar format Indonesia konsisten.
5. Bila kolom harus muncul di ekspor, perbarui descriptor kolom pada `KompenResponHubDownloadController` dan template PDF.

## Menambah komponen UI domain

- Untuk komponen yang hanya dipakai satu halaman, buat di dekat halaman tersebut atau di folder domain yang eksplisit, misalnya `resources/js/components/kompen-respon-hub/`.
- Beri props TypeScript yang kecil dan eksplisit; jangan mengoper seluruh page props jika komponen hanya butuh sebagian data.
- Gunakan `cn()` dari `@/lib/utils` untuk class kondisional.
- Utamakan `Button`, `Card`, `Input`, `Select`, dan `Alert` yang sudah tersedia daripada menyalin markup.

## Build dan verifikasi

Untuk pengembangan lokal:

```bash
npm run dev
```

Untuk pengecekan dan produksi:

```bash
npm run check
npm run types:check
npm run build
```

Pada Docker Compose, asset dibangun saat image dibuat. Setelah UI berubah, rebuild service aplikasi dan web:

```bash
docker compose up -d --build app web queue
```

## Jangan edit file generate

`resources/js/actions/` dan `resources/js/routes/` merupakan output Wayfinder. Setelah perubahan route/controller, jalankan:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

Lalu commit output generate bersama perubahan route tersebut.
