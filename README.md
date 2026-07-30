# Sewantara Backend

Sewantara adalah backend SaaS multi-tenant untuk pengelolaan berbagai jenis bisnis rental. Aplikasi ini sedang berada pada tahap pembangunan fondasi tenancy, subscription, onboarding tenant, dan modul operasional MVP.

## Status Proyek

| Area | Status | Keterangan |
|---|---|---|
| Central dan tenant routing | Selesai | Mendukung central domain, tenant domain, dan tenant ID melalui path API |
| Tenant isolation | Selesai | Model operasional memakai global scope `BelongsToTenant` dari Stancl |
| Tenant middleware | Selesai | Validasi context, user tenant, status tenant, dan subscription aktif |
| Plan dan feature | Selesai | Seeder Starter, Growth, dan Scale menggunakan LaravelCM Subscriptions |
| Registrasi tenant | Selesai | Tenant, schema, migration, dan owner langsung disiapkan untuk masa trial |
| Guided onboarding | Selesai | Business, template, rental engine, inventory, pricing, booking, payment, dan Go Live |
| Rental engine | Selesai | Per-hour, per-day, session, strategy booking, dan allocation dibaca dari konfigurasi tenant |
| Trial subscription | Selesai | Subscription `main` memperoleh trial 14 hari dari konfigurasi plan |
| Midtrans Snap adapter | Fondasi selesai | Sudah memakai SDK resmi `midtrans/midtrans-php` |
| Billing subscription | Sebagian selesai | Webhook pembayaran terverifikasi; checkout, reconciliation, dan renewal belum dibuat |
| Authentication token | Selesai | Login tenant menggunakan Laravel Sanctum Bearer token |
| Master produk | Selesai | CRUD kategori dan produk, hierarchy, filter, soft delete, dan tenant isolation |
| Inventory lifecycle | Selesai | Reservasi booking, check-out, return, cancel, movement stok/unit, dan maintenance dasar |
| Role dan permission | Belum selesai | Authorization berbasis permission masih dalam roadmap |
| Migration modul rental | Selesai | Migration central dan schema tenant dipisahkan |

## Teknologi

- PHP 8.3+
- Laravel 13
- PostgreSQL
- `stancl/tenancy` 3.x
- `laravelcm/laravel-subscriptions` 1.x
- `midtrans/midtrans-php` 2.x
- Pest 4

## Struktur Modul

Feature aplikasi berada di `app/Modules`:

```text
Bookings/             # Booking dan availability
Customers/            # Customer rental
Inventory/            # Product dan product unit
Organization/         # Branch
Payments/             # Pembayaran booking
Reporting/            # Dashboard dan laporan
SubscriptionBilling/  # Contract billing dan adapter Midtrans
SubscriptionCatalog/  # Katalog plan
Tenancy/              # Tenant management dan middleware isolation
TenantOnboarding/     # Registrasi tenant dan trial subscription
```

Route merupakan composition layer dan tetap mengarah ke HTTP controller konvensional di `app/Http/Controllers/Api`. Controller menangani validasi dan JSON response, lalu mendelegasikan query, transaksi, kalkulasi, serta aturan bisnis ke use case di `app/Modules`. Model Eloquent tetap berada di `App\Models` sebagai shared persistence kernel agar identity class untuk relation dan morph subscription stabil.

```text
app/Http/
├── Controllers/Api/  # HTTP adapter dan JSON response
├── Middleware/       # HTTP/tenant request guard
└── Requests/         # Validasi input kompleks

app/Modules/{Feature}/
├── Application/      # Use case dan business workflow
├── Contracts/        # Port/interface bila dibutuhkan
└── Infrastructure/   # Adapter framework/provider eksternal
```

## Arsitektur Multi-Tenant

Sewantara menggunakan satu database PostgreSQL dengan schema terpisah untuk setiap tenant.

- `tenants`, `domains`, plan, dan subscription merupakan data central.
- User platform/super-admin berada di tabel `users` central.
- User dan data operasional berada pada schema PostgreSQL milik tenant.
- `DatabaseTenancyBootstrapper` mengalihkan koneksi setelah tenant diidentifikasi.
- Schema tenant dibuat dan dimigrasikan otomatis saat registrasi agar trial dapat langsung digunakan.
- Master data memakai `BIGINT` auto-increment; user, tenant, dan transaksi memakai UUID.
- Tenant merupakan subscriber subscription, bukan User.
- Subscription utama selalu menggunakan nama `main`.

Urutan middleware tenant API:

```text
Initialize tenant
→ Authenticate Sanctum Bearer token
→ Validate authenticated user tenant
→ Validate tenant status
→ Validate active subscription
→ Controller
```

## Instalasi Lokal

Pastikan extension PHP untuk PostgreSQL, cURL, JSON, dan OpenSSL sudah aktif.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

Seeder akan membuat plan:

| Plan | Harga/bulan | Cabang | User | Produk |
|---|---:|---:|---:|---:|
| Starter | Rp199.000 | 1 | 3 | 100 |
| Growth | Rp499.000 | 3 | 10 | 1.000 |
| Scale | Rp999.000 | 10 | 50 | 5.000 |

Semua plan memiliki trial 14 hari dan grace period 3 hari.

### Demo Data

Pada environment local, `php artisan db:seed` akan menawarkan pembuatan demo
tenant lengkap. Seeder dapat dijalankan langsung tanpa prompt:

```bash
php artisan db:seed --class=DemoDataSeeder
```

Demo tenant:

```text
URL      : http://demo-rental.localhost
Email    : owner@demo.localhost
Password : DemoTenant123!
```

Data dibuat melalui seeder per feature dan aman dijalankan berulang:

- `DemoTenantRegistrationSeeder`: central user, tenant, domain, trial, schema, migration, dan owner.
- `TenantOrganizationSeeder`: pengaturan bisnis dan cabang.
- `TenantAccessControlSeeder`: role owner dan permission.
- `TenantCustomerSeeder`: pelanggan, alamat, dan dokumen.
- `TenantInventorySeeder`: hierarchy kategori, produk, harga, gambar, unit, dan stok.
- `TenantBookingSeeder`: booking, item, alokasi unit, serta histori status.
- `TenantBillingSeeder`: pembayaran, invoice, invoice item, dan deposit.

Registration seeder juga membersihkan schema demo orphan yang tertinggal setelah
`migrate:fresh`. Cleanup hanya berlaku pada schema tanpa row tenant central yang
memiliki owner demo, sehingga schema tenant lain tidak ikut terhapus.

Seeder feature dapat dijalankan sendiri setelah demo tenant tersedia, misalnya:

```bash
php artisan db:seed --class=TenantInventorySeeder
```

## Konfigurasi Environment

Konfigurasi minimum:

```env
APP_NAME=Sewantara
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=sewantara_app
DB_USERNAME=postgres
DB_PASSWORD=

TENANT_BASE_DOMAIN=localhost
```

Konfigurasi Midtrans Sandbox:

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxx
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_3DS=true
```

Jangan menyimpan Server Key asli di repository.

## API yang Tersedia

### Central API

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/` | Informasi backend Sewantara |
| GET | `/api/shared/health` | Health check bersama |
| GET | `/api/central/plans` | Daftar plan aktif beserta feature |
| POST | `/api/central/auth/register` | Registrasi tenant dengan trial |
| GET | `/api/central/business-templates` | Preset template rental yang aktif |
| GET | `/api/central/tenants` | Daftar tenant sementara |
| POST | `/api/central/tenants` | Membuat tenant sementara |
| GET | `/api/central/tenants/{tenant}` | Detail tenant |

Endpoint registrasi dibatasi `5 request/menit/IP`.

### Tenant API

Endpoint operasional tenant memakai prefix `/api/tenant/{tenant}`. Khusus
login, tenant dikenali otomatis melalui akun pusat berdasarkan alamat email
sehingga ID tenant tidak perlu dikirim melalui URL:

- `POST http://localhost/api/tenant/auth/login`
- `POST /auth/logout`
- `GET /me`
- `GET /onboarding`
- `PATCH /onboarding/business`
- `PATCH /onboarding/rental`
- `POST /onboarding/inventory/complete`
- `POST /onboarding/pricing/complete`
- `PATCH /onboarding/booking`
- `PATCH /onboarding/payments`
- `POST /onboarding/go-live`
- `/branches`
- `GET|POST /categories`
- `GET|PATCH|DELETE /categories/{category}`
- `GET|POST /products`
- `GET|PATCH|DELETE /products/{product}`
- `/product-units`
- `/product-prices`
- `GET /inventory/stocks`
- `POST /inventory/stocks/adjust`
- `GET /inventory/movements/stocks`
- `GET /inventory/movements/units`
- `/customers`
- `/bookings`
- `POST /bookings/{booking}/check-out`
- `POST /bookings/{booking}/return`
- `POST /bookings/{booking}/cancel`
- `/bookings/{booking}/payments`
- `GET|POST /maintenance`
- `GET /maintenance/{maintenance}`
- `POST /maintenance/{maintenance}/start`
- `POST /maintenance/{maintenance}/complete`
- `POST /maintenance/{maintenance}/cancel`
- `/availability/check`
- `/reports/dashboard`

Endpoint selain login wajib mengirim header:

```http
Authorization: Bearer {access_token}
Accept: application/json
```

Contoh login:

```json
{
  "email": "owner@example.test",
  "password": "<TENANT_PASSWORD>",
  "device_name": "web"
}
```

Endpoint tenant belum siap untuk production sebelum permission dan policy selesai.

## Alur Registrasi dan Trial

`POST /api/central/auth/register` menerima data bisnis, owner, subdomain, dan plan.

Registrasi diimplementasikan sebagai modul mandiri:

```text
app/Modules/TenantOnboarding/
├── Application/       # Use case, command, result, dan business exception
├── Contracts/         # Port yang tidak bergantung pada Laravel
├── Infrastructure/    # Adapter Eloquent, transaction, dan LaravelCM
└── TenantOnboardingServiceProvider.php
```

HTTP controller registrasi berada di `app/Http/Controllers/Api/Auth` dan Form Request berada di `app/Http/Requests/Auth`. Application layer onboarding tidak bergantung pada Eloquent, Laravel, Stancl, LaravelCM, atau Midtrans. Batas tersebut dijaga oleh architecture test.

```json
{
  "business_name": "Rental Kamera Jember",
  "business_type": "camera_rental",
  "subdomain": "rentalkamerajember",
  "owner": {
    "name": "Owner Rental",
    "email": "owner@example.test",
    "phone": "081234567890",
    "password": "<TENANT_PASSWORD>",
    "password_confirmation": "<TENANT_PASSWORD>"
  },
  "plan_id": 1,
  "billing_interval": "month",
  "terms_accepted": true
}
```

Proses registrasi:

1. Memastikan plan aktif dan subdomain tersedia.
2. Membuat Tenant dengan UUID dan slug unik dari nama bisnis.
3. Membuat domain utama dengan format hostname penuh, misalnya `kendokenceng.localhost`.
4. Membuat owner di database central dan menyimpan hash yang sama untuk provisioning user tenant.
5. Membuat subscription package bernama `main`.
6. Mengaktifkan trial sesuai plan.
7. Membuat schema tenant, owner, branch utama, business profile, rental configuration, progress onboarding, dan metode pembayaran awal.
8. Tenant berstatus `onboarding` dan dapat login untuk menyelesaikan setup.
9. Fitur operasional tetap terkunci sampai checklist Go Live lengkap.
10. Tenant berubah menjadi `active` setelah Go Live berhasil.

Frontend tidak mengirim `slug`, `timezone`, `currency`, atau `status`. Backend membuat
slug secara otomatis, menggunakan timezone `Asia/Jakarta` dan currency `IDR`, serta
mengelola status tenant berdasarkan provisioning dan progress onboarding.

Tidak ada transaksi Midtrans saat registrasi. Checkout baru akan dibuat saat invoice subscription perlu dibayar.

## Midtrans

Integrasi Midtrans dibungkus oleh `SubscriptionPaymentGateway`. Implementasi SDK berada di:

```text
app/Modules/SubscriptionBilling/Infrastructure/Midtrans/
├── MidtransSubscriptionPaymentGateway.php
├── MidtransSnapClient.php
└── MidtransSignatureVerifier.php
```

Adapter saat ini:

- Membuat transaksi Snap melalui SDK resmi.
- Mengaktifkan sanitization dan 3D Secure.
- Memakai order ID sebagai idempotency key.
- Memastikan total item sama dengan `gross_amount`.
- Menyediakan verifikasi signature webhook.
- Memastikan nominal webhook sama dengan tagihan.
- Mengonfirmasi pembayaran hanya untuk `settlement` atau `capture` yang diterima.
- Provisioning tenant terpisah dari webhook pembayaran dan tetap idempotent.
- Tidak memanggil Midtrans nyata selama automated test.

## Testing

Jalankan seluruh test:

```bash
php artisan test
```

Atau melalui Composer:

```bash
composer test
```

Status test saat README ini diperbarui:

```text
51 tests passed
522 assertions
```

Test mencakup Sanctum login/logout, hierarchy kategori, CRUD dan isolation
produk, tenant middleware, subscription, onboarding, Midtrans, dan webhook.

## Pekerjaan Berikutnya

Prioritas implementasi:

1. Buat subscription invoice dan snapshot harga plan.
2. Buat endpoint checkout Midtrans.
3. Renew subscription hanya setelah pembayaran tervalidasi.
4. Buat scheduler trial reminder, invoice, grace period, dan suspension.
5. Implementasikan role, permission, policy, dan audit log.
6. Tambahkan integration test provisioning schema PostgreSQL.

## Dokumentasi

Dokumentasi lengkap berada di folder [`docs`](docs), termasuk:

- Product Requirements
- Database Design
- Architecture
- API Specification
- Security Guide
- Testing Guide
- Deployment dan Roadmap
