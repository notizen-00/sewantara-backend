# Sewantara Backend

Sewantara adalah backend SaaS multi-tenant untuk pengelolaan berbagai jenis bisnis rental. Aplikasi ini sedang berada pada tahap pembangunan fondasi tenancy, subscription, onboarding tenant, dan modul operasional MVP.

## Status Proyek

| Area | Status | Keterangan |
|---|---|---|
| Central dan tenant routing | Selesai | Mendukung central domain, tenant domain, dan tenant ID melalui path API |
| Tenant isolation | Selesai | Model operasional memakai global scope `BelongsToTenant` dari Stancl |
| Tenant middleware | Selesai | Validasi context, user tenant, status tenant, dan subscription aktif |
| Plan dan feature | Selesai | Seeder Starter, Growth, dan Scale menggunakan LaravelCM Subscriptions |
| Registrasi tenant | Selesai | Tenant, database, migration, dan owner langsung disiapkan untuk masa trial |
| Trial subscription | Selesai | Subscription `main` memperoleh trial 14 hari dari konfigurasi plan |
| Midtrans Snap adapter | Fondasi selesai | Sudah memakai SDK resmi `midtrans/midtrans-php` |
| Billing subscription | Sebagian selesai | Webhook pembayaran terverifikasi; checkout, reconciliation, dan renewal belum dibuat |
| Authentication token | Belum selesai | Laravel Sanctum dan penerbitan access token belum dipasang |
| Role dan permission | Belum selesai | Authorization berbasis permission masih dalam roadmap |
| Migration modul rental | Selesai | Central dan tenant database migrations dipisahkan |

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

Sewantara menggunakan central database dan database terpisah untuk setiap tenant.

- `tenants`, `domains`, plan, dan subscription merupakan data central.
- User dan data operasional berada pada database tenant.
- `DatabaseTenancyBootstrapper` mengalihkan koneksi setelah tenant diidentifikasi.
- Database tenant dibuat dan dimigrasikan otomatis saat registrasi agar trial dapat langsung digunakan.
- Tenant merupakan subscriber subscription, bukan User.
- Subscription utama selalu menggunakan nama `main`.

Urutan middleware tenant API:

```text
Initialize tenant
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
| GET | `/api/central/tenants` | Daftar tenant sementara |
| POST | `/api/central/tenants` | Membuat tenant sementara |
| GET | `/api/central/tenants/{tenant}` | Detail tenant |

Endpoint registrasi dibatasi `5 request/menit/IP`.

### Tenant API

Semua endpoint berikut memakai prefix `/api/tenant/{tenant}` dan middleware tenant lengkap:

- `/me`
- `/branches`
- `/products`
- `/product-units`
- `/customers`
- `/bookings`
- `/bookings/{booking}/payments`
- `/availability/check`
- `/reports/dashboard`

Endpoint tenant belum siap untuk production sebelum authentication token, permission, policy, dan seluruh migration operasional selesai.

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
    "email": "owner@example.com",
    "phone": "081234567890",
    "password": "StrongPassword123!",
    "password_confirmation": "StrongPassword123!"
  },
  "plan_id": 1,
  "billing_interval": "month",
  "terms_accepted": true
}
```

Proses registrasi:

1. Memastikan plan aktif dan subdomain tersedia.
2. Membuat Tenant dengan UUID dan slug unik dari nama bisnis.
3. Membuat domain utama.
4. Menyimpan data owner terenkripsi/hash untuk proses provisioning.
5. Membuat subscription package bernama `main`.
6. Mengaktifkan trial sesuai plan.
7. Langsung membuat database tenant, menjalankan tenant migration, lalu membuat owner tanpa menunggu pembayaran.
8. Tenant berstatus aktif selama trial setelah seluruh provisioning berhasil.

Frontend tidak mengirim `slug`, `timezone`, `currency`, atau `status`. Backend membuat
slug secara otomatis, menggunakan timezone `Asia/Jakarta` dan currency `IDR`, serta
mengelola status tenant berdasarkan proses pembayaran dan provisioning.

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
31 test passed
210 assertions
```

Test mencakup tenant middleware, cross-tenant protection, subscription status, central domain routing, plan seeder, onboarding use case, boundary controller/module, Midtrans Snap adapter, dan webhook signature.

## Pekerjaan Berikutnya

Prioritas implementasi:

1. Pasang Laravel Sanctum dan implementasikan login/access token.
2. Buat subscription invoice dan snapshot harga plan.
3. Buat endpoint checkout Midtrans.
4. Renew subscription hanya setelah pembayaran tervalidasi.
5. Buat scheduler trial reminder, invoice, grace period, dan suspension.
6. Implementasikan role, permission, policy, dan audit log.
7. Tambahkan integration test provisioning database PostgreSQL.

## Dokumentasi

Dokumentasi lengkap berada di folder [`docs`](docs), termasuk:

- Product Requirements
- Database Design
- Architecture
- API Specification
- Security Guide
- Testing Guide
- Deployment dan Roadmap
