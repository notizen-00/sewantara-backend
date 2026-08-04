# CI/CD dengan GitHub Actions

Dokumen ini menjelaskan pipeline CI/CD Sewantara yang berjalan di GitHub
Actions: workflow `CI` untuk lint dan test, serta workflow `Deploy` untuk
deployment otomatis ke server production melalui SSH.

## Ringkasan Alur

```text
push / pull request ke master
        │
        ▼
  Workflow "CI" (.github/workflows/ci.yml)
  - Pint (code style)
  - Pest/PHPUnit (test suite)
  - Build aset frontend (Vite)
        │
        │ hanya saat push ke master DAN CI sukses
        ▼
  Workflow "Deploy" (.github/workflows/deploy.yml)
  - SSH ke server production
  - git fetch + reset ke origin/master
  - docker compose build && up -d
  - php artisan tenants:migrate --force
```

Workflow `Deploy` dipicu oleh event `workflow_run` dari workflow `CI`,
sehingga deployment hanya berjalan setelah seluruh test dan lint dinyatakan
lulus pada branch `master`. Pull request tidak pernah memicu deployment.

## Workflow CI

File: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)

Berjalan pada setiap push dan pull request ke branch `master`. Langkah
utama:

1. Checkout kode dan setup PHP 8.3 beserta ekstensi yang dibutuhkan
   (`pdo_sqlite`, `pdo_pgsql`, `redis`, dll).
2. Install dependency Composer dan npm (dengan cache).
3. Salin `.env.example` ke `.env` dan generate `APP_KEY`.
4. Build aset frontend (`npm run build`) untuk memastikan konfigurasi Vite
   tetap valid.
5. Jalankan `vendor/bin/pint --test` untuk memeriksa code style.
6. Jalankan `php artisan test`. Test suite memakai SQLite in-memory sesuai
   konfigurasi `phpunit.xml`, sehingga tidak memerlukan service database
   tambahan di CI.

## Workflow Deploy

File: [`.github/workflows/deploy.yml`](../.github/workflows/deploy.yml)

Menjalankan ulang langkah manual pada
[`docs/15_DOCKER_DEPLOYMENT.md`](15_DOCKER_DEPLOYMENT.md) (bagian
"Deployment Versi Baru") secara otomatis melalui SSH ke server:

```bash
git fetch origin master
git reset --hard origin/master
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d --remove-orphans
docker compose --env-file .env.production exec -T app php artisan tenants:migrate --force
docker image prune -f
```

`git reset --hard` dipakai (bukan `git pull`) agar working directory di
server selalu identik dengan `origin/master`, menghindari konflik akibat
perubahan lokal yang tidak sengaja tertinggal di server.

### Prasyarat Server

- Repository sudah pernah di-clone ke server pada path yang akan dipakai
  sebagai `DEPLOY_PATH` (lihat secret di bawah), dan sudah pernah dijalankan
  minimal sekali secara manual mengikuti
  [`docs/15_DOCKER_DEPLOYMENT.md`](15_DOCKER_DEPLOYMENT.md).
- `.env.production` sudah tersedia di server (file ini diabaikan Git dan
  tidak pernah dikirim oleh workflow).
- User SSH deploy memiliki izin menjalankan `docker compose` (anggota grup
  `docker`) tanpa `sudo` interaktif.
- Public key dari key pair deploy sudah ditambahkan ke
  `~/.ssh/authorized_keys` milik user tersebut di server.

### Secrets yang Dibutuhkan

Tambahkan secrets berikut pada GitHub repository (Settings → Secrets and
variables → Actions), idealnya diikat ke environment `production`:

| Secret | Contoh nilai | Keterangan |
| --- | --- | --- |
| `SSH_HOST` | `203.0.113.10` atau `api.example.com` | Alamat server production |
| `SSH_USER` | `deploy` | User SSH dengan akses `docker compose` |
| `SSH_PORT` | `22` | Opsional, default `22` jika tidak diisi |
| `SSH_PRIVATE_KEY` | isi file private key (PEM) | Private key pasangan dari public key di server |
| `DEPLOY_PATH` | `/var/www/sewantara-backend` | Path absolut repository di server |

### Environment `production`

Workflow deploy mereferensikan GitHub Environment bernama `production`
(Settings → Environments). Ini opsional tapi disarankan karena
memungkinkan:

- Required reviewers, sehingga deployment butuh approval manual sebelum
  jalan meski CI sukses.
- Secrets khusus environment, terpisah dari secrets repository biasa.

Jika environment `production` belum dibuat, buat terlebih dahulu lalu
masukkan kelima secret di atas ke dalamnya (atau ke secrets repository jika
tidak butuh approval gate).

## Menjalankan Migration Tenant Baru

Workflow deploy hanya menjalankan `tenants:migrate` untuk tenant yang sudah
ada. Migration schema tenant baru tetap berjalan otomatis melalui proses
onboarding tenant, sesuai penjelasan di
[`docs/15_DOCKER_DEPLOYMENT.md`](15_DOCKER_DEPLOYMENT.md).

## Troubleshooting

- **Deploy tidak jalan setelah push ke master**: periksa tab Actions pada
  workflow `CI` terlebih dahulu — `Deploy` hanya terpicu jika `CI` berstatus
  sukses (`workflow_run` dengan `conclusion == success`).
- **SSH gagal terkoneksi**: pastikan `SSH_HOST`/`SSH_PORT` dapat dijangkau
  dari internet (runner GitHub Actions memakai IP publik dinamis, sehingga
  firewall harus mengizinkan SSH dari luar, bukan hanya IP tertentu).
- **`docker compose` gagal karena permission**: pastikan `SSH_USER` adalah
  anggota grup `docker` di server.
