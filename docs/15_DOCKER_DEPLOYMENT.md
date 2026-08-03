# Deployment Docker Sewantara

Dokumen ini menjelaskan deployment production Sewantara menggunakan Docker
Compose, PostgreSQL, Redis, Nginx, queue worker, scheduler, dan Laravel Reverb.

## Arsitektur Container

| Service | Fungsi |
| --- | --- |
| `nginx` | Pintu masuk HTTP serta reverse proxy WebSocket |
| `app` | Laravel melalui PHP-FPM |
| `migrate` | Menjalankan migration central sebelum aplikasi dimulai |
| `queue` | Memproses queue Redis |
| `scheduler` | Menjalankan Laravel scheduler |
| `reverb` | Server WebSocket Laravel Reverb |
| `postgres` | Database central dan schema setiap tenant |
| `redis` | Cache, session, queue, dan scaling Reverb |

PostgreSQL, Redis, PHP-FPM, dan Reverb hanya tersedia di jaringan internal
Docker. Port internal `9000` dan `8080` tidak menimbulkan konflik dengan
container lain karena tidak dipublikasikan ke host. Nginx Sewantara secara
default diikat ke `0.0.0.0:8090` agar dapat dijangkau Nginx Proxy Manager.

## Persiapan Server

Server harus menyediakan Docker Engine dan Docker Compose plugin. Siapkan DNS:

- `api.example.com` menuju alamat IP server.
- `*.example.com` menuju alamat IP server untuk domain tenant.

Gunakan Nginx Proxy Manager atau load balancer yang menangani TLS di depan
port Nginx server `8090`. Sertifikat harus mencakup domain API dan wildcard
domain tenant.

## Konfigurasi Environment

Repository menyediakan tiga profil environment:

| Profil | Penggunaan | Cara memuat |
| --- | --- | --- |
| `.env.local` | Laragon atau PHP lokal tanpa Docker | `php artisan --env=local ...` |
| `.env.development` | Stack Docker development | `docker compose --env-file .env.development ...` |
| `.env.production` | Stack Docker server production | `docker compose --env-file .env.production ...` |

File aktual tersebut diabaikan Git. File dengan akhiran `.example` merupakan
template yang aman disimpan di repository.

Jika file aktual belum tersedia, salin template yang sesuai:

```bash
cp .env.local.example .env.local
cp .env.development.example .env.development
cp .env.production.example .env.production
```

Untuk deployment server, isi `.env.production` dengan seluruh domain,
password, kredensial Midtrans, SMTP, serta secret. Buat
nilai acak menggunakan:

```bash
openssl rand -base64 32
openssl rand -hex 16
openssl rand -hex 32
```

Gunakan hasilnya untuk:

- `APP_KEY` dengan awalan `base64:`.
- `REVERB_APP_KEY`.
- `REVERB_APP_SECRET`.
- `DB_PASSWORD` dan `POSTGRES_PASSWORD` dengan nilai yang sama.

Nilai penting untuk koneksi internal container:

```dotenv
DB_HOST=postgres
REDIS_HOST=redis
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
```

Port host Nginx dapat diubah tanpa menyentuh port internal container:

```dotenv
APP_BIND_IP=0.0.0.0
APP_HTTP_PORT=8090
```

Jika port `8090` juga digunakan aplikasi lain, pilih port host kosong seperti
`8092` atau `8093`, kemudian arahkan reverse proxy ke port tersebut.
Batasi akses port tersebut melalui firewall agar hanya Nginx Proxy Manager
atau jaringan tepercaya yang dapat mengaksesnya.

## Akses PostgreSQL dari pgAdmin

Service PostgreSQL dapat dipublikasikan ke host melalui konfigurasi berikut:

```dotenv
POSTGRES_BIND_IP=0.0.0.0
POSTGRES_PUBLIC_PORT=5432
```

`0.0.0.0` membuat port dapat dijangkau melalui seluruh interface server.
Untuk akses lokal atau SSH tunnel saja, gunakan `127.0.0.1`.

Terapkan perubahan dengan membuat ulang container PostgreSQL:

```bash
docker compose --env-file .env.production up -d --force-recreate postgres
docker compose --env-file .env.production ps postgres
```

Konfigurasi koneksi pgAdmin:

| Field | Nilai |
|---|---|
| Host | IP publik atau domain server |
| Port | Nilai `POSTGRES_PUBLIC_PORT` |
| Maintenance database | Nilai `POSTGRES_DB` |
| Username | Nilai `POSTGRES_USER` |
| Password | Nilai `POSTGRES_PASSWORD` |

Jangan membuka port PostgreSQL untuk seluruh internet tanpa pembatasan. Batasi
inbound TCP `5432` pada firewall provider atau firewall server hanya ke IP
publik perangkat yang menjalankan pgAdmin. Contoh UFW:

```bash
sudo ufw allow from <IP_PUBLIK_PGADMIN>/32 to any port 5432 proto tcp
sudo ufw deny 5432/tcp
sudo ufw status
```

Jika IP administrator sering berubah, gunakan VPN atau SSH tunnel dan set
`POSTGRES_BIND_IP=127.0.0.1`.

Nilai `REVERB_PUBLIC_*` dipakai saat build aset frontend dan harus menunjuk
domain publik yang dilindungi TLS:

```dotenv
REVERB_PUBLIC_HOST=api.example.com
REVERB_PUBLIC_PORT=443
REVERB_PUBLIC_SCHEME=https
```

Jangan commit `.env.local`, `.env.development`, atau `.env.production` ke
repository.

Untuk menjalankan stack development:

```bash
docker compose --env-file .env.development up -d --build
```

## Build dan Menjalankan Aplikasi

Jalankan dari root repository:

```bash
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d
```

Service `migrate` otomatis menjalankan migration central sebelum `app`,
`queue`, `scheduler`, dan `reverb` dimulai.

Untuk tenant yang sudah ada, jalankan migration tenant setelah deployment:

```bash
docker compose --env-file .env.production exec app \
  php artisan tenants:migrate --force
```

Registrasi tenant baru tetap membuat dan memigrasikan schema tenant secara
otomatis melalui proses onboarding.

## Pemeriksaan Deployment

Periksa kondisi container:

```bash
docker compose --env-file .env.production ps
docker compose --env-file .env.production logs --tail=100 app
docker compose --env-file .env.production logs --tail=100 queue
docker compose --env-file .env.production logs --tail=100 reverb
```

Health endpoint:

```text
https://api.example.com/up
```

Uji koneksi WebSocket melalui domain publik yang sama. Nginx meneruskan jalur
`/app/*` dan `/apps/*` ke container Reverb.

## Deployment Versi Baru

```bash
git pull
docker compose --env-file .env.production build
docker compose --env-file .env.production up -d --remove-orphans
docker compose --env-file .env.production exec app \
  php artisan tenants:migrate --force
```

Image baru menjalankan `config:cache` dan `view:cache` pada startup. Queue
worker ikut dibuat ulang agar menggunakan source code terbaru.

## Backup PostgreSQL

Contoh backup manual:

```bash
docker compose --env-file .env.production exec -T postgres \
  pg_dump -U sewantara -d sewantara_app -Fc > sewantara.dump
```

Simpan backup di storage terpisah dari server aplikasi dan lakukan pengujian
restore secara berkala. Volume `app_storage` juga harus dibackup karena memuat
berkas upload tenant.

## Catatan Keamanan

- Jangan membuka PostgreSQL `5432` untuk semua sumber; jika dipublikasikan,
  batasi hanya IP administrator atau jaringan VPN.
- Jangan mempublikasikan Redis `6379`, Reverb `8080`, atau PHP-FPM `9000`.
- Ganti seluruh nilai contoh pada `.env.production`.
- Batasi `REVERB_ALLOWED_ORIGINS` dan `CORS_ALLOWED_ORIGINS` ke domain resmi.
- Aktifkan firewall server dan hanya buka port yang digunakan reverse proxy.
- Gunakan TLS untuk seluruh trafik publik dan cookie production.
