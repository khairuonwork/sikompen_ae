# Panduan Perubahan UI Sikompen

Panduan ini untuk perubahan UI tanpa melanggar kontrak backend. Sebelum mengubah layout, baca [frontend-explained.md](frontend-explained.md) agar perubahan ditempatkan di feature yang tepat.

## Peta perubahan

| Kebutuhan | Lokasi utama | Catatan |
| --- | --- | --- |
| Landing/pemilih akses | `resources/js/pages/welcome.tsx` | Pertahankan navigasi Wayfinder serta context proxy/standalone. |
| Header, navigasi tab, state lintas panel | `pages/kompen-respon-hub/index.tsx` | Jangan memindahkan tabel/form domain besar kembali ke sini. |
| Ringkasan mahasiswa | `features/.../kompen-respon/student-table.tsx` | Gunakan type `Student` dan formatter jam yang ada. |
| Detail Kompen | `features/.../detail-kompen/` | Pisahkan tabel dan correction panel. |
| Upload/progress import | `features/.../upload/` | Jangan hilangkan polling task atau guard drop file. |
| List File | `features/.../list-file/import-version-list.tsx` | Pastikan tindakan rename/delete/activate memakai Wayfinder dan feedback server. |
| Log Upload | `features/.../log-upload/` | Pertahankan filter periode serta bulan/tahun aktivitas. |
| Cutoff/SP | `features/.../surat-peringatan/warning-panel.tsx` | Jangan menyatukan periode SP dikelola dengan periode list tanpa label yang jelas. |
| Riwayat aktivitas | `features/.../riwayat-aktivitas/activity-panel.tsx` | Subject/jenis aktivitas harus tetap interpretabel. |
| Export | `features/.../export/` | Jangan mengganti queue/polling menjadi request sinkron. |
| Tema global | `resources/css/app.css` | Perubahan akan memengaruhi semua feature. |
| Primitive UI | `resources/js/components/ui/` | Dampak global; lakukan visual check seluruh aplikasi. |

## Menambah tab baru

1. Tambahkan nilai tab di `features/kompen-respon-hub/shared/constants.ts`.
2. Tambahkan nilai yang sama pada rule `tab` di `KompenResponHubTableRequest`.
3. Sediakan prop backend hanya pada tab tersebut di `KompenResponHubController::index()`.
4. Tambahkan type prop di `shared/types.ts`.
5. Buat feature folder/file bila tanggung jawabnya mandiri, lalu rangkai di `index.tsx`.
6. Gunakan route/aksi Wayfinder dan tambahkan test feature untuk akses/data.

Jangan hanya menambah JSX tab. Backend harus mengenali tab supaya URL, validasi, keamanan, dan ukuran response tetap benar.

## Menambah atau mengubah kolom

1. Ubah JSON Resource backend yang relevan.
2. Ubah type di `shared/types.ts`.
3. Ubah komponen tabel feature terkait.
4. Untuk jam/angka, gunakan formatter yang ada agar locale dan presisi konsisten.
5. Jika harus diekspor, ubah descriptor di `app/Actions/KompenResponHub/BuildKompenResponHubExport.php`—bukan controller lama atau file generated.
6. Tambahkan test resource/export yang terdampak.

## Konvensi UX

- Form perbaikan muncul sebagai panel yang bisa ditutup, tidak terus-menerus memenuhi halaman.
- Button biru gelap harus memiliki ikon dan teks putih.
- Dropdown/select memakai permukaan putih dan icon arrow konsisten.
- Filter melekat pada tabel yang dipengaruhinya; jangan menyebarkan filter yang sama ke card tidak terkait.
- Gunakan empty state yang menjelaskan langkah selanjutnya untuk admin, dan penjelasan read-only yang sederhana untuk mahasiswa.
- Pesan keberhasilan/kegagalan datang dari server dan ditampilkan melalui flash alert/toast; jangan menyembunyikan error backend.

## Verifikasi

```bash
npm run check
npm run build
```

Jika route/controller berubah:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

Untuk Docker, asset dibangun saat image dibangun ulang:

```bash
docker compose up -d --build app web queue
```

Jangan mengedit `resources/js/actions/` atau `resources/js/routes/` secara manual karena keduanya output Wayfinder.
