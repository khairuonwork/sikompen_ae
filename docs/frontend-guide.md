# Panduan Perubahan UI Sikompen

## Peta perubahan

| Jika mengubah… | Periksa… |
| --- | --- |
| Tabel Kompen/Respon | `features/.../kompen-respon/`, `shared/types.ts`, resource/backend filter. |
| Detail Kompen | `features/.../detail-kompen/`, resource detail, dan export descriptor. |
| Upload | `upload-workspace.tsx`, panel workbook/roster, template menu, serta polling task. |
| List File | Daftar versi workbook/roster, consistency panel, recycle bin, dan query paginator terkait. |
| Log Upload | Filter/table dan panel retensi audit. |
| Akun/profil | `pages/auth/`, `pages/mahasiswa/profile.tsx`, settings, reset mahasiswa, atau recovery emergency. |
| Route/controller | Wayfinder generated code dan type yang mengonsumsinya. |
| Primitive UI/CSS | Semua halaman yang memakai `components/ui/` atau `resources/css/app.css`. |

## Menambah tab

1. Tambahkan value pada `features/kompen-respon-hub/shared/constants.ts`.
2. Tambahkan validasi tab di `KompenResponHubTableRequest`.
3. Muat prop backend hanya untuk tab tersebut di controller.
4. Tambahkan prop/type di `shared/types.ts`.
5. Buat feature module mandiri lalu rangkai pada shell `index.tsx`.
6. Pertahankan query dan state pagination yang relevan; jangan mengganggu paginator tab List File lainnya.
7. Tambahkan test feature untuk akses dan data prop baru.

## Menambah atau mengubah kolom

1. Perbarui JSON Resource backend dan type TypeScript.
2. Perbarui tabel feature yang memakai data tersebut.
3. Gunakan formatter bersama untuk jam, angka, dan tanggal.
4. Jika kolom diekspor, perbarui `BuildKompenResponHubExport`.
5. Tambahkan/update test kontrak resource atau export yang terdampak.

## Konvensi UX

- Panel koreksi harus dapat dibuka/ditutup dan tidak memenuhi semua baris tabel.
- Filter tinggal dekat tabel yang dipengaruhinya.
- Empty state menjelaskan langkah berikutnya dan perbedaan akses admin/mahasiswa.
- Error dan sukses berasal dari respons server serta ditampilkan sebagai flash/toast.
- Jangan mengubah `resources/js/actions/` atau `resources/js/routes/` secara manual.

## Verifikasi

```bash
npm run check
npm run build
php artisan wayfinder:generate --with-form --no-interaction
```

Jalankan test feature backend yang melindungi route, prop, atau mutasi yang ikut berubah.
