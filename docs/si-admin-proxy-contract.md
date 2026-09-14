# Contract gateway Si-Admin → Sikompen

Dokumen ini adalah kontrak integrasi untuk tim Si-Admin. Implementasi Sikompen berada pada endpoint internal `GET /si-admin/access`.

> Endpoint ini tidak dimaksudkan untuk browser publik. Saat integrasi production aktif, service Nginx Sikompen tidak memiliki `ports:` publik dan hanya dapat dicapai melalui Docker network internal dari gateway Si-Admin.

## Hasil role

| Role dari Si-Admin | Hasil Sikompen |
| --- | --- |
| `admin` | Session Sikompen dibuat, lalu redirect ke panel `/admin/kompen-respon`. |
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

Jangan kirim secret pada header, query string, cookie, JavaScript, atau repository Git.

## Konfigurasi Sikompen

Tambahkan secret yang sama di `.env` Sikompen:

```dotenv
SI_ADMIN_PROXY_ENABLED=true
SI_ADMIN_PROXY_SHARED_SECRET=<secret-hex-64-atau-lebih>
SI_ADMIN_PROXY_SIGNATURE_TTL_SECONDS=60
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

Nginx standar tidak dapat menghitung HMAC per pengguna sendiri. Karena itu pembuatan HMAC wajib berada di backend Si-Admin atau modul gateway yang setara. Konfigurasi `proxy_pass` saja tidak cukup.

Gateway harus menjaga cookie Sikompen tetap terisolasi, misalnya:

```dotenv
# Environment Sikompen pada Compose gabungan
SESSION_COOKIE=sikompen_session
SESSION_PATH=/sikompen
```

Jika Si-Admin mem-publish Sikompen dengan prefix `/sikompen`, gateway juga harus menerjemahkan redirect Sikompen seperti `/admin/kompen-respon` menjadi `/sikompen/admin/kompen-respon` dan meneruskan `Set-Cookie` response tanpa menghapusnya.

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

Respons sukses adalah `302` menuju `/admin/kompen-respon` dengan session cookie Sikompen. Mengirim request yang persis sama untuk kedua kali harus menghasilkan `403`.

## Batas implementasi saat ini

- Sikompen sudah menangani verifikasi, anti-replay, session proxy, dan mapping role.
- Local admin login Sikompen masih dibiarkan untuk mode standalone. Ketika deployment gabungan siap, Sikompen harus dihapus dari port publik dan gateway Si-Admin menjadi satu-satunya jalan masuk.
- Route mahasiswa tetap publik pada Compose standalone saat ini. Isolasi network/reverse proxy adalah kontrol yang menjadikan aplikasi benar-benar private di deployment gabungan.
