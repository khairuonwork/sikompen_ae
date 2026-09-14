# Contract gateway Si-Admin → Sikompen

Dokumen ini adalah kontrak integrasi untuk tim Si-Admin. Implementasi Sikompen berada pada endpoint internal `GET /si-admin/access`.

> Endpoint ini tidak dimaksudkan untuk browser publik. Saat integrasi production aktif, service Nginx Sikompen tidak memiliki `ports:` publik dan hanya dapat dicapai melalui Docker network internal dari gateway Si-Admin.

## Hasil role

| Role dari Si-Admin | Hasil Sikompen |
| --- | --- |
| `admin` | Session Sikompen dibuat, lalu redirect ke panel `/admin`. |
| `superuser` | Session Sikompen dibuat, lalu redirect ke halaman pemilih akses `/`. Ia dapat memilih Admin atau Mahasiswa. |
| `mahasiswa` | Session Sikompen dibuat, lalu redirect ke `/mahasiswa`. |
| selain tiga nilai di atas | Ditolak `403`. |

`superuser` adalah role gateway untuk testing/operasional; Sikompen tidak membuat akun database lokal bernama superuser. Saat memilih Admin, ia memiliki hak setara `admin`.

## Security model

Gateway Si-Admin dan Sikompen berbagi satu secret acak minimal 32 karakter melalui secret manager/environment variable, **bukan** `APP_KEY` Laravel. Secret dipakai untuk HMAC SHA-256; tidak ada payload rahasia yang dienkripsi atau dikirim ke browser.

Sikompen memverifikasi:

1. Integrasi memang diaktifkan (`SI_ADMIN_PROXY_ENABLED=true`).
2. Semua header wajib valid dan role termasuk allow-list.
3. Timestamp masih berada dalam batas 60 detik.
4. HMAC cocok menggunakan `hash_equals`.
5. Nonce/signature belum pernah dipakai. Replay dalam periode berlaku ditolak.

Ketika integrasi aktif, seluruh route web dan API Sikompen membutuhkan session
proxy yang valid. Login, setup akun lokal, pengaturan akun lokal, dan logout admin
lokal ditutup. Session admin lokal tidak dapat menaikkan akses session `mahasiswa`
yang datang dari Si-Admin.

Jangan kirim secret pada header, query string, cookie, JavaScript, atau repository Git.

## Konfigurasi Sikompen

Tambahkan secret yang sama di `.env` Sikompen:

```dotenv
SI_ADMIN_PROXY_ENABLED=true
SI_ADMIN_PROXY_SHARED_SECRET=<secret-hex-64-atau-lebih>
SI_ADMIN_PROXY_SIGNATURE_TTL_SECONDS=60
SI_ADMIN_PROXY_SESSION_MAX_AGE_SECONDS=7200
SI_ADMIN_PROXY_PUBLIC_PATH=/sikompen

# Domain publik milik Si-Admin.
APP_URL=https://domain-mereka/sikompen
DOCKER_APP_URL=https://domain-mereka/sikompen
SESSION_COOKIE=sikompen_session
SESSION_PATH=/sikompen
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_ENCRYPT=true
```

Buat secret sekali di environment aman:

```bash
openssl rand -hex 32
```

Setelah mengubah environment container, rebuild/restart lalu cache ulang konfigurasi:

```bash
docker compose up -d --build app web queue
docker compose exec app php artisan optimize
```

## URL publik yang dipakai pengguna

Sikompen tidak memiliki domain publik sendiri. Pengguna selalu melihat domain
Si-Admin berikut:

| Halaman | URL publik |
| --- | --- |
| Titik masuk Sikompen | `https://domain-mereka/sikompen/` |
| Panel admin | `https://domain-mereka/sikompen/admin` |
| Halaman mahasiswa | `https://domain-mereka/sikompen/mahasiswa` |

Gateway Si-Admin menghapus prefix `/sikompen` saat meneruskan ke network internal:
`/sikompen/admin` menjadi `http://sikompen-web/admin`, sedangkan
`/sikompen/mahasiswa` menjadi `http://sikompen-web/mahasiswa`. `APP_URL` membuat
redirect, pagination, asset Vite, dan navigasi Inertia kembali ke URL publik ini.

## Header wajib

| Header | Bentuk | Contoh |
| --- | --- | --- |
| `X-Si-Admin-User-Id` | 1–64 karakter: huruf, angka, `_`, atau `-` | `user_123` |
| `X-Si-Admin-Email` | email valid | `admin@polman.ac.id` |
| `X-Si-Admin-Role` | `admin`, `mahasiswa`, atau `superuser` | `superuser` |
| `X-Si-Admin-Timestamp` | Unix timestamp 10 digit | `1789272000` |
| `X-Si-Admin-Nonce` | 16–128 karakter: huruf, angka, `_`, atau `-` | nonce acak baru |
| `X-Si-Admin-Signature` | HMAC SHA-256 lowercase hex, 64 karakter | hasil perhitungan di bawah |

## Canonical string dan signature

Susun persis tujuh baris ini, dipisahkan **satu newline (`\n`)** tanpa newline tambahan di akhir:

```text
GET
/si-admin/access
<timestamp>
<nonce>
<user-id>
<email>
<role>
```

Kemudian:

```text
signature = HMAC-SHA256(canonical-string, shared-secret)
```

Contoh PHP di backend Si-Admin:

```php
$timestamp = time();
$nonce = bin2hex(random_bytes(16));
$role = 'superuser'; // Uji integrasi; produksi memakai role session Si-Admin.
$canonical = implode("\n", [
    'GET',
    '/si-admin/access',
    (string) $timestamp,
    $nonce,
    (string) $siAdminUser->id,
    $siAdminUser->email,
    $role,
]);

$headers = [
    'X-Si-Admin-User-Id' => (string) $siAdminUser->id,
    'X-Si-Admin-Email' => $siAdminUser->email,
    'X-Si-Admin-Role' => $role,
    'X-Si-Admin-Timestamp' => (string) $timestamp,
    'X-Si-Admin-Nonce' => $nonce,
    'X-Si-Admin-Signature' => hash_hmac('sha256', $canonical, config('services.sikompen.shared_secret')),
];
```

Gunakan role dari session/authorization Si-Admin yang sudah diverifikasi. Jangan pernah menerima role dari query parameter atau body browser lalu meneruskannya tanpa validasi server-side.

## Cara gateway bekerja

Tombol sidebar pengguna membuka route **Si-Admin**, bukan endpoint Sikompen langsung. Route Si-Admin tersebut:

1. Memastikan session Si-Admin valid.
2. Menentukan role dari server-side authorization Si-Admin.
3. Membuat timestamp, nonce, dan HMAC baru.
4. Meneruskan request internal ke Sikompen dengan enam header di atas.
5. Meneruskan response `302 Location` dan `Set-Cookie` Sikompen kembali ke browser melalui reverse proxy.

Secara konkret, handler Si-Admin melakukan dua mode request berikut:

| Request browser ke URL publik | Tindakan backend Si-Admin di Docker network | Hasil |
| --- | --- | --- |
| Klik menu Sikompen | Validasi session Si-Admin, lalu `GET http://sikompen-web/si-admin/access` dengan enam header HMAC baru | Teruskan `Set-Cookie` Sikompen dan redirect `Location` ke browser. |
| Request lanjutan `/sikompen/...` | Validasi ulang session Si-Admin, hilangkan prefix `/sikompen`, lalu proxy ke `http://sikompen-web/<path>` dengan cookie Sikompen | Response Sikompen kembali melalui URL publik yang sama. |

Jangan membuat `redirect()` browser menuju `/si-admin/access`: browser tidak boleh
memegang maupun mengirim header HMAC. Hanya HTTP client server-side Si-Admin yang
boleh memanggil endpoint itu. `Set-Cookie` dari respons Sikompen harus diteruskan
tanpa diubah agar browser menyimpan `sikompen_session` dengan `Path=/sikompen`.

Nginx standar tidak dapat menghitung HMAC per pengguna sendiri. Karena itu pembuatan HMAC wajib berada di backend Si-Admin atau modul gateway yang setara. Konfigurasi `proxy_pass` saja tidak cukup.

Gateway harus menjaga cookie Sikompen tetap terisolasi, misalnya:

```dotenv
# Environment Sikompen pada Compose gabungan
SESSION_COOKIE=sikompen_session
SESSION_PATH=/sikompen
```

Jika Si-Admin mem-publish Sikompen dengan prefix `/sikompen`, `APP_URL` Sikompen
sudah menghasilkan redirect publik seperti `/sikompen/admin`; gateway tetap wajib
meneruskan `Location` dan `Set-Cookie` response tanpa menghapusnya.

Kontrak URL sekarang menggunakan panel ringkas `/sikompen/admin`. Endpoint lama
`/sikompen/admin/kompen-respon` tetap mengarah ke panel yang sama untuk kompatibilitas.

Setelah sesi dibuat, request tetap harus melalui gateway Si-Admin. Gateway memvalidasi
session Si-Admin pada setiap request, meneruskan cookie `sikompen_session`, menghapus
prefix `/sikompen`, lalu memanggil `sikompen-web` di Docker network internal. Jangan
membuat rule Nginx publik yang langsung menuju `sikompen-web`.

Template Nginx publik tersedia di `nginx.si-admin-gateway.conf`. File ini hanya
memiliki upstream `si-admin-web` dan menghapus enam header HMAC dari request browser
untuk mencegah header spoofing.

## Database yang dibutuhkan

Tidak ada secret, payload HMAC, atau nonce mentah yang disimpan di database.
Migration Sikompen menambahkan tabel berikut pada koneksi `kompen_db`:

| Tabel | Fungsi |
| --- | --- |
| `sikompen_cache` | Cache persisten dan atomik untuk anti-replay HMAC di semua instance aplikasi. |
| `sikompen_cache_locks` | Lock table pendukung database cache Laravel. |
| `sikompen_proxy_access_logs` | Audit handoff HMAC yang diterima: user ID Si-Admin, email, role, hash nonce, IP gateway, user agent, dan waktu. |

`nonce_hash` memiliki unique index sebagai pertahanan replay tambahan. Cache tetap
menjadi kontrol utama ber-TTL 60 detik; log audit tidak menyimpan nonce atau signature
asli.

Setelah menarik perubahan, jalankan:

```bash
docker compose exec app php artisan migrate --force
```

## Contoh uji internal dengan curl

Gunakan hanya dari container/gateway internal setelah secret dikonfigurasi. Isi semua nilai sendiri; jangan masukkan secret ke shell history production.

```bash
timestamp=$(date +%s)
nonce=$(openssl rand -hex 16)
user_id=si-admin-test-1
email=superuser@si-admin.test
role=superuser
canonical=$(printf 'GET\n/si-admin/access\n%s\n%s\n%s\n%s\n%s' "$timestamp" "$nonce" "$user_id" "$email" "$role")
signature=$(printf %s "$canonical" | openssl dgst -sha256 -hmac "$SI_ADMIN_PROXY_SHARED_SECRET" -hex | awk '{print $2}')

curl -i http://sikompen-web/si-admin/access \
  -H "X-Si-Admin-User-Id: $user_id" \
  -H "X-Si-Admin-Email: $email" \
  -H "X-Si-Admin-Role: $role" \
  -H "X-Si-Admin-Timestamp: $timestamp" \
  -H "X-Si-Admin-Nonce: $nonce" \
  -H "X-Si-Admin-Signature: $signature"
```

Respons sukses adalah `302` menuju `/admin` dengan session cookie Sikompen. Mengirim request yang persis sama untuk kedua kali harus menghasilkan `403`.

## Batas implementasi saat ini

- Sikompen sudah menangani verifikasi, anti-replay, session proxy, dan mapping role.
- Local admin login Sikompen tersedia hanya untuk mode standalone (`SI_ADMIN_PROXY_ENABLED=false`).
- Saat gateway aktif, middleware Sikompen menolak web/API tanpa session proxy; isolasi network dan reverse proxy tetap wajib sebagai pertahanan jaringan kedua.
