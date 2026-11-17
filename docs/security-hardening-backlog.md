# Backlog hardening keamanan

## Status dan batas audit

- Basis kode: branch `staging`, commit `0a19f2c`.
- Audit terakhir: 17 November 2026.
- Status rilis publik: **tahan sampai seluruh P0 selesai**. Aplikasi masih dapat dipakai untuk pengujian privat melalui localhost atau Tailscale.
- Cakupan: autentikasi, otorisasi, upload XLSX, API outbound, worker dan scheduler, Docker/Nginx, proxy, dependensi, serta batas data sementara.

Validasi kode pada basis audit sebelumnya lulus: 152 test dengan 1.193 assertion, PHPStan, Pint, TypeScript, build frontend, dan `docker compose config`. Hasil ini membuktikan kontrak yang diuji masih berjalan, tetapi tidak menggantikan patch keamanan vendor atau hardening konfigurasi produksi.

## P0: blocker rilis publik

### P0-01: bootstrap admin reguler pertama masih anonim

**Kondisi saat ini**

Route `GET/POST /admin/setup` hanya menggunakan middleware `web`. Saat belum ada akun admin reguler, aplikasi membuka pembuatan admin tanpa activation code. Akun emergency sengaja tidak dihitung sebagai admin reguler, sehingga seed akun emergency tidak menutup celah ini.

**Dampak**

Deployment baru yang langsung dipublikasikan dapat diambil alih oleh pengunjung pertama yang membuat akun admin reguler. Akun tersebut memperoleh akses penuh terhadap file, data mahasiswa, SP, ekspor, serta pengaturan sistem.

**Target perbaikan**

1. Hapus bootstrap admin reguler anonim dari jalur publik.
2. Buat admin reguler pertama melalui seeder privat atau proses setup yang membutuhkan secret satu kali dari environment.
3. Setelah admin reguler tersedia, pendaftaran admin tetap tertutup secara default dan hanya dapat dibuka oleh aktor yang sudah terautentikasi sesuai kebijakan recovery.
4. Tambahkan test untuk menolak setup tanpa otorisasi pada database kosong dan memastikan setup code hanya dapat digunakan satu kali.

**Mitigasi sampai patch tersedia**

Pada deployment baru, jangan hidupkan Cloudflare Tunnel atau binding publik sebelum admin reguler sah selesai dibuat dari jaringan privat. Jalankan setup pertama melalui localhost atau Tailscale, lalu verifikasi login admin sebelum membuka akses umum.

**Referensi kode**

- `app/Http/Controllers/KompenResponHubAdminSetupController.php`
- `routes/web.php`
- `tests/Feature/AdminSetupTest.php`

### P0-02: dependency dengan advisory keamanan masih aktif

**Kondisi saat ini**

`composer audit` pada 17 November 2026 melaporkan 13 advisory pada 7 package. Versi yang dipakai meliputi Laravel `13.35.0`, PhpSpreadsheet `5.9.0`, Symfony HTTP Foundation/Kernel `8.1.8`, Symfony Mime `8.1.7`, Symfony Routing `8.1.8`, dan Symfony YAML `8.1.6`.

Yang paling relevan dengan aplikasi adalah PhpSpreadsheet karena worker memanggil `IOFactory::load()` terhadap XLSX yang diunggah. Advisory aktif mencakup XXE/DoS dan bypass SSRF pada reader. Lihat [GHSA-rxj4-h86g-25gc](https://github.com/advisories/GHSA-rxj4-h86g-25gc), [GHSA-3x94-m842-937c](https://github.com/advisories/GHSA-3x94-m842-937c), dan [GHSA-2phh-27fw-q4gc](https://github.com/advisories/GHSA-2phh-27fw-q4gc).

`npm audit --omit=dev` juga melaporkan satu advisory high pada Vite `8.2.0` sampai `8.2.2`. Vite tidak menjadi runtime web production karena aset dilayani Nginx, tetapi tetap memengaruhi mesin build dan CI.

**Target perbaikan**

1. Upgrade Laravel, PhpSpreadsheet, dan dependency Symfony transitif ke versi patch yang tidak terpengaruh.
2. Upgrade Vite ke versi yang telah dipatch dan validasikan lockfile baru.
3. Jalankan test suite, PHPStan, build frontend, `composer audit`, serta `npm audit --omit=dev` setelah upgrade.
4. Tambahkan kedua audit dependency ke workflow CI agar advisory baru tidak tertinggal tanpa terlihat.

**Kriteria selesai**

Tidak ada advisory high atau critical yang masih terpakai oleh runtime maupun pipeline build. Setiap advisory tersisa harus mempunyai alasan non-exploitable yang terdokumentasi dan tanggal review ulang.

**Referensi kode**

- `composer.lock`
- `package-lock.json`
- `app/Actions/KompenResponHub/ParseKompenResponHubWorkbook.php`
- `app/Actions/KompenResponHub/ParseKompenResponHubActiveStudentWorkbook.php`

## P1: dikerjakan setelah P0, sebelum operasi publik rutin

| ID | Temuan | Risiko | Target perbaikan |
| --- | --- | --- | --- |
| P1-01 | Rate limit API dihitung per `client + IP`. Untuk client token-only, token bocor dapat digunakan dari banyak IP sehingga total kuota efektif menjadi jauh lebih tinggi. | Penyalahgunaan API dan tekanan ke database. | Tambahkan limit global per API client, pertahankan limit per IP sebagai lapisan tambahan, dan dokumentasikan rotasi token darurat. |
| P1-02 | Validasi XLSX sudah membatasi ukuran, zip bomb, traversal, formula, dan entry archive. Namun macro, external relationship, external link, serta OLE object belum ditolak secara eksplisit. | Permukaan serangan parser tetap lebih luas dari format template yang dibutuhkan. | Setelah dependency dipatch, terapkan allowlist struktur XLSX yang diperlukan dan tolak macro serta hubungan eksternal. |
| P1-03 | `api-mini-app` memuat seluruh `.env`, padahal hanya memerlukan base URL dan bearer token. | Password database, credential emergency, dan secret lain ikut tersedia dalam container panel uji. | Gunakan env khusus API Mini App dengan dua variabel minimum. Panel tetap hanya bind ke loopback dan tidak masuk Cloudflare Tunnel. |
| P1-04 | Nginx menerima semua host melalui `server_name _` dan meneruskan forwarded host ke Laravel. Proxy juga mempercayai CIDR network, bukan service proxy yang paling sempit. | Host header yang tidak dibatasi dapat memengaruhi URL absolut, logging, serta efektivitas batas IP. | Gunakan hostname eksplisit, default server penolak, network proxy terpisah, dan trusted proxy CIDR sesempit mungkin. |
| P1-05 | Password awal mahasiswa adalah NIM. | Orang yang mengetahui NIM mahasiswa aktif dapat mengklaim akun sebelum mahasiswa mengganti password. | Tetapkan batas waktu perubahan password atau ganti menjadi credential sementara unik/one-time code saat data sumber siap. Ini adalah trade-off bisnis yang harus disetujui tertulis bila tetap dipakai. |
| P1-06 | Token-only API tanpa allowlist IP merupakan keputusan untuk client melalui Cloudflare Tunnel tanpa egress statis. | Bearer token menjadi satu-satunya bukti akses dari internet. | Gunakan token acak panjang, satu token per integrasi, scope minimum, rotasi terjadwal, dan prosedur pencabutan yang me-restart service setelah `.env` diganti. |
| P1-07 | Generator artifact bulk menahan transaksi database selama serialisasi seluruh periode. | Pada data besar, snapshot konsisten dapat menekan database dan memengaruhi availability. | Rancang snapshot berbasis cutoff ID/waktu atau materialisasi data terlebih dahulu, lalu serialisasi di luar transaksi panjang. |

## Kontrol yang sudah tersedia

- Guard admin, mahasiswa, dan emergency admin dipisahkan.
- Mahasiswa dibatasi backend agar hanya dapat membaca data berdasarkan NIM sesi.
- Form request, query binding Eloquent, CSRF route web, dan rate limit login/reset tersedia.
- Kode reset mahasiswa disimpan dengan encrypted cast; kode reset admin disimpan sebagai hash.
- File sumber, ekspor, dan artifact API menggunakan private storage serta retensi terjadwal.
- API outbound memakai bearer token, scope, ownership sync run per client, checksum artifact, batas ukuran, dan worker `api-sync` terpisah.
- Scheduler menggunakan `withoutOverlapping()` untuk purge dan rekonsiliasi task.

## Urutan implementasi

1. Tutup bootstrap admin anonim dan tulis test regresinya.
2. Upgrade dependency serta jadikan audit dependency sebagai syarat CI.
3. Perketat API token-only, header/proxy, dan isolasi environment API Mini App.
4. Perketat struktur XLSX yang diterima.
5. Putuskan kebijakan password awal mahasiswa dan optimalkan snapshot bulk API.

## Bahan presentasi

### Tujuan presentasi

Menyepakati kondisi rilis, risiko yang diterima, dan urutan hardening. Presentasi tidak perlu mengklaim aplikasi sudah production-ready sebelum P0 selesai.

### Alur presentasi yang disarankan

1. **Peran sistem dan data yang dikelola**: admin, mahasiswa, admin emergency, dan client API.
2. **Alur operasional**: unggah XLSX, worker validasi, aktivasi per periode, tabel/progres/SP, ekspor, dan API outbound.
3. **Kontrol yang telah ada**: pemisahan role, private storage, queue terpisah, retensi, audit log, serta API artifact 24 jam.
4. **Dua blocker P0**: bootstrap admin pertama dan dependency security advisory.
5. **P1 yang memerlukan keputusan kebijakan**: token-only API, password awal NIM, dan batas upload XLSX.
6. **Rencana sebelum public deployment**: P0, pengujian ulang, staging privat, backup/restore drill, lalu pembukaan Cloudflare Tunnel.
7. **Keputusan yang diminta dari peserta**: metode admin pertama, pemilik token API, kebijakan rotasi token, dan penerimaan atau penggantian password awal NIM.

### Materi demo yang aman

- Gunakan data dummy dan akun demo, bukan data mahasiswa nyata.
- Tunjukkan satu alur upload sampai aktivasi versi, satu profil mahasiswa yang terscope, dan satu sync API artifact.
- Tunjukkan status queue atau audit activity tanpa membuka `.env`, password database, token API, emergency credential, IP internal, atau kode reset.
- Siapkan screenshot fallback untuk worker/queue bila demo asynchronous belum selesai saat presentasi.

### Bukti yang perlu dibawa

- Hasil test, PHPStan, build, dan audit dependency setelah P0 ditutup.
- Diagram container/worker dari `docs/system-design.md`.
- Dokumentasi kontrak untuk tim integrasi pada `docs/api-outbound-integration-proposal.md`.
