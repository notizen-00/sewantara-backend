# Product Requirements Document

# Sewantara Tenant Public API

**Project:** Sewantara Backend API
**Module:** Public Tenant Website API
**Repository:** `sewantara-api`
**Version:** 1.0
**Status:** Proposed
**Backend:** Laravel 12
**Tenancy:** `stancl/tenancy`
**Subscription:** `laravelcm/laravel-subscriptions`
**Database:** PostgreSQL
**Cache and Queue:** Redis
**Primary consumer:** Sewantara Tenant Web — Nuxt SSR/Nitro BFF

---

# 1. Product overview

Sewantara Tenant Public API adalah backend resmi untuk website publik multi-tenant:

```text
https://{tenant}.sewantara.id
```

Contoh:

```text
https://kamerajember.sewantara.id
https://rentalmobilbali.sewantara.id
https://psarena.sewantara.id
```

API melayani kebutuhan:

- penyelesaian identitas tenant;
- validasi status tenant dan domain;
- branding dan konfigurasi website tenant;
- katalog dan detail produk;
- pencarian availability;
- pembuatan quote;
- booking;
- checkout;
- pembayaran;
- tracking;
- artikel SEO;
- kontak dan informasi bisnis.

Nuxt tidak menjadi sumber kebenaran bisnis. Harga, stok, availability, diskon, biaya, deposit, status pembayaran, dan status booking selalu dihitung dan divalidasi Laravel.

---

# 2. Background

Website tenant menggunakan arsitektur:

```text
Browser
  → {tenant}.sewantara.id
  → Nuxt SSR / Nitro BFF
  → api.sewantara.id
  → Laravel
  → tenant resolution
  → stancl/tenancy
  → tenant database
```

Browser tidak memanggil Laravel secara langsung.

Nuxt BFF akan mengirim identitas request melalui header server-to-server:

```http
X-Tenant: kamerajember
X-Tenant-Host: kamerajember.sewantara.id
X-Request-Id: 019...
```

Laravel wajib menganggap header tersebut sebagai kandidat identitas, bukan sebagai bukti bahwa tenant valid.

Laravel tetap harus:

1. memvalidasi format header;
2. mencari tenant pada central database;
3. mencocokkan slug dengan hostname;
4. memeriksa domain tenant;
5. memeriksa status tenant;
6. memeriksa status subscription;
7. memeriksa apakah website publik diaktifkan;
8. baru menginisialisasi tenancy.

Stancl Tenancy mendukung identifikasi melalui domain, subdomain, path, request data, maupun resolver khusus. Untuk arsitektur API terpusat seperti `api.sewantara.id`, Sewantara menggunakan resolver khusus berbasis request server-to-server.

---

# 3. Goals

## 3.1 Primary goals

- Menyediakan kontrak API stabil untuk Nuxt Tenant Web.
- Memastikan tenant yang diminta benar-benar ada dan aktif.
- Mencegah akses lintas tenant.
- Mencegah spoofing tenant melalui header.
- Menjaga Laravel sebagai sumber kebenaran bisnis.
- Mendukung guest booking secara aman.
- Menyediakan idempotency untuk mutation penting.
- Mendukung tenant subdomain dan custom domain.
- Menyediakan response error yang konsisten.
- Mendukung observability per request dan per tenant.
- Menyediakan fondasi payment gateway multi-provider.

## 3.2 Secondary goals

- Mendukung cache publik tanpa mencampur data tenant.
- Mendukung customer authentication di fase berikutnya.
- Mendukung artikel SEO.
- Mendukung tenant maintenance dan suspension.
- Mendukung ribuan tenant tanpa deployment aplikasi per tenant.

---

# 4. Non-goals

Versi pertama tidak mencakup:

- marketplace antar-tenant;
- cross-tenant search;
- rekomendasi AI;
- loyalty point;
- affiliate;
- customer wallet;
- split settlement kompleks;
- aplikasi dashboard tenant;
- pengelolaan katalog oleh customer;
- logic bisnis di Nuxt;
- kepercayaan terhadap status pembayaran dari return URL browser.

---

# 5. Actors

## 5.1 Guest customer

Dapat:

- melihat website tenant;
- melihat katalog;
- mencari ketersediaan;
- meminta quote;
- membuat booking;
- melakukan checkout;
- membayar;
- melacak booking dengan verifier.

## 5.2 Authenticated customer

Fase lanjutan:

- melihat riwayat booking;
- menyimpan profil;
- mengunduh invoice;
- memberikan review;
- membatalkan booking sesuai kebijakan.

## 5.3 Tenant

Mengelola data melalui dashboard terpisah:

- profil bisnis;
- branding;
- katalog;
- availability;
- booking rules;
- pembayaran;
- artikel;
- status website publik.

## 5.4 Nuxt BFF

Merupakan konsumen utama Public Tenant API.

Nuxt BFF bertanggung jawab untuk:

- membaca hostname request browser;
- menormalisasi hostname;
- menolak hostname yang tidak didukung;
- menghasilkan `X-Request-Id`;
- meneruskan identitas tenant;
- tidak menerima upstream path arbitrary dari browser;
- tidak mengekspos secret Laravel;
- tidak menghitung business truth.

## 5.5 Laravel API

Laravel bertanggung jawab untuk:

- tenant resolution;
- authorization;
- validation;
- pricing;
- availability;
- stock hold;
- booking;
- payment initiation;
- payment reconciliation;
- webhook;
- auditing;
- idempotency;
- isolation.

---

# 6. System architecture

```text
Customer browser
        │
        ▼
Cloudflare
        │
        ▼
Nginx Proxy Manager
        │
        ▼
Nuxt SSR / Nitro BFF
        │
        │ HTTPS
        │ X-Tenant
        │ X-Tenant-Host
        │ X-Request-Id
        ▼
api.sewantara.id
        │
        ▼
Laravel API
        │
        ├── Trusted Proxy Validation
        ├── BFF Authentication
        ├── Tenant Header Validation
        ├── Central Tenant Resolution
        ├── Tenant Eligibility Check
        ├── tenancy()->initialize()
        ├── Rate Limiting
        ├── Validation
        ├── Domain Service / Action
        └── JSON Response
                │
                ├── Central PostgreSQL
                ├── Tenant PostgreSQL
                ├── Redis
                ├── Queue Worker
                └── Payment Provider
```

---

# 7. Trust boundaries

## 7.1 Browser to Nuxt

Input dari browser dianggap tidak terpercaya.

Browser tidak boleh menentukan:

- tenant ID;
- tenant database;
- harga final;
- nominal pembayaran;
- status pembayaran;
- status booking;
- discount amount;
- tax amount;
- stock availability;
- payment provider callback result.

## 7.2 Nuxt to Laravel

Nuxt adalah server yang dipercaya secara terbatas.

Laravel tidak boleh langsung mempercayai:

```http
X-Tenant
X-Tenant-Host
X-Request-Id
```

Laravel harus memvalidasi semuanya.

Untuk memperkuat boundary, request Nuxt ke Laravel wajib menggunakan salah satu mekanisme berikut:

### Pilihan utama

```http
Authorization: Bearer {BFF_SERVICE_TOKEN}
```

### Alternatif lanjutan

HMAC request signature:

```http
X-Sewantara-Key-Id: tenant-web
X-Sewantara-Timestamp: 178...
X-Sewantara-Signature: sha256=...
```

Minimal versi pertama wajib memakai service token yang:

- disimpan pada private runtime config Nuxt;
- disimpan pada environment Laravel;
- tidak dikirim ke browser;
- dapat dirotasi;
- memiliki scope hanya untuk Public Tenant API.

## 7.3 Laravel to payment provider

Laravel wajib memverifikasi:

- webhook signature;
- provider event ID;
- payment reference;
- nominal;
- currency;
- merchant/account target;
- booking ownership;
- duplicate event.

Return URL browser tidak boleh mengubah status pembayaran.

---

# 8. Tenant data model

## 8.1 Central database

### Table: `tenants`

| Column                 | Type               | Requirement             |
| ---------------------- | ------------------ | ----------------------- |
| `id`                   | UUID/ULID          | Primary key             |
| `slug`                 | varchar(63)        | Unique, lowercase       |
| `name`                 | varchar            | Required                |
| `status`               | enum               | Required                |
| `public_web_enabled`   | boolean            | Default false           |
| `subscription_status`  | enum               | Required                |
| `subscription_ends_at` | timestamp nullable | Optional                |
| `timezone`             | varchar            | Default `Asia/Jakarta`  |
| `locale`               | varchar            | Default `id-ID`         |
| `currency`             | char(3)            | Default `IDR`           |
| `database_name`        | varchar            | Managed tenancy         |
| `data`                 | jsonb              | Package/custom metadata |
| `created_at`           | timestamp          | Required                |
| `updated_at`           | timestamp          | Required                |
| `deleted_at`           | timestamp nullable | Soft delete             |

### Tenant status enum

```text
pending
provisioning
active
maintenance
suspended
disabled
deleted
```

### Subscription status enum

```text
trial
active
grace_period
past_due
expired
cancelled
```

## 8.2 Central domains table

Stancl Tenancy menggunakan relasi tenant ke banyak domain. Subdomain dapat disimpan sebagai nilai tanpa titik, sedangkan custom domain disimpan sebagai hostname penuh.

### Table: `domains`

| Column        | Type               | Requirement   |
| ------------- | ------------------ | ------------- |
| `id`          | bigint             | Primary key   |
| `tenant_id`   | UUID/ULID          | Foreign key   |
| `domain`      | varchar(253)       | Unique        |
| `type`        | enum               | Recommended   |
| `status`      | enum               | Recommended   |
| `verified_at` | timestamp nullable | Custom domain |
| `is_primary`  | boolean            | Default false |
| `created_at`  | timestamp          | Required      |
| `updated_at`  | timestamp          | Required      |

### Domain type enum

```text
subdomain
custom_domain
```

### Domain status enum

```text
pending
verified
active
disabled
```

Contoh:

```text
tenant.slug: kamerajember

domains:
- domain: kamerajember
  type: subdomain
  status: active

- domain: kamerajember.com
  type: custom_domain
  status: verified
```

## 8.3 Reserved slugs

Slug berikut tidak boleh digunakan tenant:

```text
www
api
app
admin
dashboard
auth
login
register
support
help
status
static
assets
cdn
mail
email
billing
payment
payments
checkout
webhook
webhooks
docs
developer
developers
health
healthz
internal
system
root
null
undefined
```

Reserved slug wajib disimpan pada config dan divalidasi pada saat pembuatan tenant.

---

# 9. Tenant resolution requirements

## 9.1 Input

Laravel menerima:

```http
X-Tenant: kamerajember
X-Tenant-Host: kamerajember.sewantara.id
X-Request-Id: 01J...
```

## 9.2 Validation rules

### `X-Tenant`

- required;
- lowercase;
- panjang 2–63 karakter;
- diawali huruf atau angka;
- hanya berisi `a-z`, `0-9`, dan `-`;
- tidak diakhiri `-`;
- tidak mengandung `--`;
- bukan reserved slug.

Regex awal:

```regex
^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$
```

### `X-Tenant-Host`

- required;
- lowercase setelah normalisasi;
- port harus dihapus;
- trailing dot harus dihapus;
- tidak boleh mengandung scheme;
- tidak boleh mengandung path;
- harus merupakan hostname valid;
- maksimum 253 karakter.

Contoh valid:

```text
kamerajember.sewantara.id
kamerajember.com
```

Contoh tidak valid:

```text
https://kamerajember.sewantara.id
kamerajember.sewantara.id/path
kamerajember.sewantara.id:443
evil.com@kamerajember.sewantara.id
```

### `X-Request-Id`

- optional, tetapi direkomendasikan;
- UUID atau ULID;
- jika tidak valid, Laravel menghasilkan request ID baru;
- request ID dikembalikan pada response.

## 9.3 Resolution algorithm

Laravel wajib menjalankan urutan berikut:

```text
1. Verify request berasal dari BFF yang sah.
2. Normalize X-Tenant.
3. Normalize X-Tenant-Host.
4. Validate format slug dan hostname.
5. Cari tenant berdasarkan slug di central database.
6. Jika tidak ditemukan, return TENANT_NOT_FOUND.
7. Cari domain yang cocok dengan hostname.
8. Cocokkan domain tersebut dengan tenant yang ditemukan.
9. Jika tidak cocok, return TENANT_HOST_MISMATCH.
10. Periksa tenant status.
11. Periksa public_web_enabled.
12. Periksa subscription eligibility.
13. Periksa domain status.
14. Initialize tenancy.
15. Pastikan tenant database dapat diakses.
16. Simpan tenant context pada request.
17. Lanjutkan ke endpoint.
18. Akhiri tenancy setelah request selesai.
```

## 9.4 Resolusi subdomain

Untuk hostname:

```text
kamerajember.sewantara.id
```

Laravel harus mencocokkan:

```text
X-Tenant = kamerajember
domain = kamerajember
tenant_id = tenant yang sama
```

## 9.5 Resolusi custom domain

Untuk hostname:

```text
kamerajember.com
```

Laravel harus mencari exact hostname pada tabel `domains`:

```text
domain = kamerajember.com
type = custom_domain
status = verified atau active
```

Kemudian memastikan domain tersebut dimiliki oleh tenant dari `X-Tenant`.

## 9.6 Tenant eligibility matrix

| Condition                    |               Public read |        Quote |      Booking |                      Payment |
| ---------------------------- | ------------------------: | -----------: | -----------: | ---------------------------: |
| Active + subscription active |                       Yes |          Yes |          Yes |                          Yes |
| Active + trial               |                       Yes |          Yes |          Yes |                          Yes |
| Active + grace period        |                       Yes | Configurable | Configurable |                 Configurable |
| Maintenance                  |      Maintenance response |           No |           No |        Existing payment only |
| Suspended                    |        Suspended response |           No |           No | Existing reconciliation only |
| Expired                      | Configurable branded page |           No |           No | Existing reconciliation only |
| Disabled                     |                        No |           No |           No |                 Webhook only |
| Deleted                      |                        No |           No |           No |                           No |

Webhook payment tidak boleh bergantung pada website tenant aktif karena callback provider tetap harus dapat direkonsiliasi.

---

# 10. Tenant resolution errors

## Tenant not found

```http
HTTP/1.1 404 Not Found
```

```json
{
    "success": false,
    "error": {
        "code": "TENANT_NOT_FOUND",
        "message": "Tenant tidak ditemukan."
    },
    "meta": {
        "request_id": "01J..."
    }
}
```

## Tenant host mismatch

Gunakan response generik agar tidak menjadi tenant enumeration vector:

```http
HTTP/1.1 404 Not Found
```

```json
{
    "success": false,
    "error": {
        "code": "TENANT_NOT_FOUND",
        "message": "Tenant tidak ditemukan."
    },
    "meta": {
        "request_id": "01J..."
    }
}
```

Detail sebenarnya hanya dicatat di log:

```text
TENANT_HOST_MISMATCH
```

## Tenant suspended

```http
HTTP/1.1 403 Forbidden
```

```json
{
    "success": false,
    "error": {
        "code": "TENANT_SUSPENDED",
        "message": "Website tenant sementara tidak tersedia."
    },
    "meta": {
        "request_id": "01J..."
    }
}
```

## Tenant maintenance

```http
HTTP/1.1 503 Service Unavailable
Retry-After: 300
```

```json
{
    "success": false,
    "error": {
        "code": "TENANT_MAINTENANCE",
        "message": "Website sedang dalam pemeliharaan."
    },
    "meta": {
        "retry_after": 300,
        "request_id": "01J..."
    }
}
```

## Tenant database unavailable

```http
HTTP/1.1 503 Service Unavailable
```

```json
{
    "success": false,
    "error": {
        "code": "TENANT_SERVICE_UNAVAILABLE",
        "message": "Layanan sementara tidak tersedia."
    },
    "meta": {
        "request_id": "01J..."
    }
}
```

Jangan menampilkan nama database, hostname PostgreSQL, stack trace, atau exception internal.

---

# 11. Required middleware pipeline

Urutan middleware harus konsisten:

```text
TrustProxies
TrustHosts
RequestId
ForceJsonResponse
AuthenticateBffService
ValidateTenantHeaders
ResolveCentralTenant
ValidateTenantEligibility
InitializeTenancy
TenantDatabaseHealth
TenantRateLimit
SetTenantLocale
Controller
TerminateTenancy
```

Laravel menyediakan konfigurasi trusted proxies, trusted hosts, middleware alias, serta Redis-backed API throttling. Konfigurasi ini harus dibatasi ke proxy yang benar-benar dipercaya.

## 11.1 `AuthenticateBffService`

Tanggung jawab:

- memeriksa service token;
- menggunakan constant-time comparison;
- mendukung key rotation;
- menolak request tanpa kredensial;
- tidak mencatat token ke log.

Response:

```http
401 Unauthorized
```

## 11.2 `ValidateTenantHeaders`

Tanggung jawab:

- normalisasi;
- validasi format;
- reserved slug validation;
- request ID validation.

## 11.3 `ResolveCentralTenant`

Tanggung jawab:

- query hanya ke central connection;
- resolve slug dan domain;
- memastikan tenant-domain ownership;
- cache hasil resolution secara aman.

Cache key wajib mencakup:

```text
tenant-resolution:{slug}:{normalized_host}
```

Jangan menggunakan cache key hanya berdasarkan slug atau hostname apabila keduanya harus cocok.

## 11.4 `ValidateTenantEligibility`

Tanggung jawab:

- tenant status;
- subscription;
- website public flag;
- domain status;
- feature entitlement.

## 11.5 `InitializeTenancy`

Tanggung jawab:

```php
tenancy()->initialize($tenant);
```

Tidak boleh dipanggil sebelum central validation selesai.

## 11.6 `TenantDatabaseHealth`

Tanggung jawab:

- memastikan connection tenant aktif;
- fail closed;
- tidak fallback ke central database;
- tidak melanjutkan request apabila tenancy gagal.

## 11.7 `TenantRateLimit`

Rate limit key minimal:

```text
tenant_id + endpoint_group + client_ip
```

Mutation dapat menggunakan:

```text
tenant_id + normalized_customer_contact + endpoint_group
```

---

# 12. Public API conventions

## 12.1 Base URL

```text
https://api.sewantara.id/v1/public
```

## 12.2 Required request headers

```http
Accept: application/json
Authorization: Bearer {BFF_SERVICE_TOKEN}
X-Tenant: kamerajember
X-Tenant-Host: kamerajember.sewantara.id
X-Request-Id: 01J...
```

Mutation tertentu juga wajib menggunakan:

```http
Idempotency-Key: UUID-OR-ULID
```

## 12.3 Standard success response

```json
{
    "success": true,
    "data": {},
    "meta": {
        "request_id": "01J...",
        "tenant": "kamerajember"
    }
}
```

## 12.4 Standard error response

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Data yang dikirim tidak valid.",
        "fields": {
            "customer.phone": ["Nomor telepon tidak valid."]
        }
    },
    "meta": {
        "request_id": "01J..."
    }
}
```

Laravel validation harus menggunakan request object khusus dan hanya data tervalidasi yang masuk ke action/service. Laravel menyediakan validation exception dan rule abstractions untuk menghasilkan error terstruktur.

## 12.5 Pagination

Request:

```text
?page=1&per_page=20
```

Response:

```json
{
    "success": true,
    "data": [],
    "meta": {
        "page": 1,
        "per_page": 20,
        "total": 100,
        "last_page": 5,
        "has_more": true,
        "request_id": "01J..."
    }
}
```

Maximum `per_page`:

```text
50
```

## 12.6 Dates and time

- Semua timestamp API menggunakan ISO 8601.
- Timestamp disimpan di database dalam UTC.
- Business schedule dikonversi menggunakan timezone tenant.
- Response wajib menyertakan timezone yang digunakan.
- Booking yang melewati DST harus tetap benar untuk custom domain internasional pada masa depan.

Contoh:

```json
{
    "starts_at": "2026-08-10T01:00:00Z",
    "ends_at": "2026-08-10T05:00:00Z",
    "timezone": "Asia/Jakarta"
}
```

## 12.7 Money

Jangan gunakan floating-point untuk uang.

Gunakan integer minor unit:

```json
{
    "amount": 250000,
    "currency": "IDR"
}
```

Untuk IDR, nilai tersebut merepresentasikan rupiah.

---

# 13. API endpoints

# 13.1 Tenant profile

```http
GET /v1/public/tenant
```

Mengembalikan:

- tenant identity;
- public status;
- branding;
- theme;
- locale;
- currency;
- timezone;
- contact;
- location;
- social media;
- payment method display;
- public feature flags;
- SEO defaults.

Response minimal:

```json
{
    "success": true,
    "data": {
        "id": "01J...",
        "slug": "kamerajember",
        "name": "Kamera Jember",
        "tagline": "Sewa kamera mudah di Jember",
        "description": "Penyewaan kamera dan perlengkapan.",
        "logo_url": "https://cdn.sewantara.id/...",
        "favicon_url": "https://cdn.sewantara.id/...",
        "theme": {
            "primary": "#C62828",
            "secondary": "#111111",
            "font": "Inter"
        },
        "contact": {
            "phone": "+62812...",
            "whatsapp": "+62812...",
            "email": "..."
        },
        "location": {
            "address": "...",
            "latitude": -8.1,
            "longitude": 113.7
        },
        "timezone": "Asia/Jakarta",
        "locale": "id-ID",
        "currency": "IDR",
        "features": {
            "guest_checkout": true,
            "online_payment": true,
            "blog": true,
            "reviews": true
        }
    }
}
```

# 13.2 Home aggregation

```http
GET /v1/public/home
```

Mengembalikan agregasi yang dibutuhkan homepage:

- hero;
- category highlights;
- featured products;
- active promotions;
- how-to-book;
- testimonials;
- FAQ;
- latest articles;
- CTA.

Endpoint harus menghindari N+1 query.

# 13.3 Categories

```http
GET /v1/public/categories
```

# 13.4 Catalog

```http
GET /v1/public/catalog
```

Query:

```text
q
category
min_price
max_price
booking_mode
available_from
available_until
sort
page
per_page
```

Allowed sort:

```text
recommended
newest
price_asc
price_desc
name_asc
popular
```

Unknown sort value harus ditolak atau menggunakan default yang terdokumentasi.

# 13.5 Product detail

```http
GET /v1/public/catalog/{product_slug}
```

Mengembalikan:

- product;
- media;
- specifications;
- variants;
- add-ons;
- pricing presentation;
- booking mode;
- booking rules;
- availability summary;
- deposit policy;
- cancellation policy;
- related products.

Data internal tidak boleh terekspos:

- purchase cost;
- margin;
- internal notes;
- supplier;
- database ID sensitif;
- deleted stock;
- audit information.

# 13.6 Availability

```http
GET /v1/public/catalog/{product_slug}/availability
```

Query berdasarkan booking mode.

Contoh date range:

```text
start=2026-08-10
end=2026-08-17
quantity=1
```

Contoh time slot:

```text
date=2026-08-10
duration_minutes=120
quantity=1
```

Response availability bersifat indikatif sampai quote atau stock hold dibuat.

# 13.7 Create quote

```http
POST /v1/public/bookings/quote
```

Request:

```json
{
    "product_id": "public-product-id",
    "variant_id": null,
    "booking": {
        "starts_at": "2026-08-10T08:00:00+07:00",
        "ends_at": "2026-08-10T12:00:00+07:00",
        "quantity": 1
    },
    "addons": [
        {
            "id": "addon-id",
            "quantity": 1
        }
    ],
    "coupon_code": "JEMBER10"
}
```

Response:

```json
{
    "success": true,
    "data": {
        "quote_id": "01J...",
        "expires_at": "2026-08-04T04:00:00Z",
        "currency": "IDR",
        "lines": [
            {
                "type": "rental",
                "label": "Sony A7 IV",
                "quantity": 1,
                "unit_amount": 250000,
                "total_amount": 250000
            }
        ],
        "subtotal": 250000,
        "discount": 25000,
        "service_fee": 0,
        "tax": 0,
        "deposit": 100000,
        "grand_total": 325000,
        "payable_now": 325000
    }
}
```

Quote wajib:

- dihitung Laravel;
- memiliki expiry;
- terikat tenant;
- terikat product dan booking input;
- tidak bisa digunakan tenant lain;
- tidak dapat dimodifikasi browser;
- memiliki snapshot pricing;
- invalid jika availability berubah;
- dapat dibuat ulang setelah expired.

# 13.8 Create booking

```http
POST /v1/public/bookings
Idempotency-Key: 01J...
```

Request:

```json
{
    "quote_id": "01J...",
    "customer": {
        "name": "Dipta Ikromi Muslim",
        "phone": "+62812...",
        "email": "..."
    },
    "notes": "Pengambilan pagi.",
    "agreement": {
        "terms_accepted": true,
        "privacy_accepted": true
    },
    "payment_method": "qris"
}
```

Laravel wajib:

1. validasi quote;
2. memastikan quote milik tenant aktif;
3. memastikan quote belum expired;
4. lock inventory;
5. memeriksa availability ulang;
6. membuat booking;
7. membuat booking lines snapshot;
8. membuat stock hold;
9. membuat payment intent bila diperlukan;
10. commit transaction;
11. dispatch event setelah commit.

Response:

```json
{
    "success": true,
    "data": {
        "booking_code": "SWJ-202608-ABC123",
        "status": "awaiting_payment",
        "payment_status": "pending",
        "expires_at": "2026-08-04T04:20:00Z",
        "tracking": {
            "token": "one-time-or-signed-token"
        },
        "payment": {
            "method": "qris",
            "redirect_url": "https://...",
            "expires_at": "2026-08-04T04:20:00Z"
        }
    }
}
```

# 13.9 Payment status

```http
GET /v1/public/payments/{public_payment_id}
```

Wajib menggunakan verifier:

- signed token;
- secure tracking token;
- authenticated customer session.

Jangan menggunakan sequential database ID.

# 13.10 Booking tracking

```http
POST /v1/public/bookings/{booking_code}/tracking
```

Request:

```json
{
    "verifier": {
        "type": "phone",
        "value": "+62812..."
    },
    "tracking_token": "..."
}
```

Aturan:

- response gagal harus generik;
- rate limited;
- verifier dinormalisasi;
- data personal dimasking;
- tracking token disimpan dalam bentuk hash jika berupa opaque secret;
- booking tenant lain tidak boleh ditemukan.

# 13.11 Blog list

```http
GET /v1/public/blog
```

# 13.12 Blog detail

```http
GET /v1/public/blog/{slug}
```

Konten HTML wajib melalui sanitasi ketika disimpan atau sebelum dikirim.

# 13.13 Sitemap

```http
GET /v1/public/sitemap
```

Mengembalikan data URL untuk dibentuk Nuxt menjadi sitemap tenant, atau XML jika diputuskan Laravel menjadi generator authoritative.

# 13.14 Health endpoints

Public infrastructure health:

```http
GET /healthz
```

Tidak memerlukan tenant dan tidak mengungkap detail.

Readiness internal:

```http
GET /readyz
```

Harus dibatasi jaringan atau token internal.

---

# 14. Booking domain requirements

## 14.1 Booking modes

Backend harus mendukung:

```text
date_range
daily
hourly
time_slot
quantity_only
serialized_unit
appointment
queue
```

## 14.2 Inventory types

```text
serialized
pooled_stock
service_capacity
```

### Serialized

Contoh:

- mobil;
- kamera;
- PlayStation unit tertentu.

Setiap unit memiliki identitas sendiri.

### Pooled stock

Contoh:

- kursi;
- tenda;
- alat pesta.

Availability berdasarkan jumlah.

### Service capacity

Contoh:

- studio;
- teknisi;
- ruang permainan;
- tenaga layanan.

Availability berdasarkan kapasitas slot.

## 14.3 Availability authority

Frontend hanya menampilkan availability terbaru yang diketahui.

Pada booking, Laravel wajib melakukan pengecekan ulang menggunakan transaction dan locking.

## 14.4 Stock hold

Stock hold wajib memiliki:

```text
id
tenant_id/context
quote_id
booking_id
product_id
variant_id
starts_at
ends_at
quantity
expires_at
status
```

Status:

```text
active
converted
released
expired
```

Worker harus melepaskan hold yang expired.

## 14.5 Booking status

```text
draft
quoted
awaiting_payment
reserved
confirmed
in_progress
completed
cancelled
expired
rejected
refunded
partially_refunded
```

## 14.6 Payment status

```text
unpaid
pending
paid
failed
expired
cancelled
refunded
partially_refunded
```

Booking status dan payment status tidak boleh digabung menjadi satu kolom.

---

# 15. Idempotency requirements

Endpoint berikut wajib memakai `Idempotency-Key`:

```text
POST /bookings
POST /payments
POST /payments/{id}/retry
POST /bookings/{id}/cancel
```

## 15.1 Idempotency storage

Simpan:

```text
tenant_id
idempotency_key
endpoint
request_hash
response_status
response_body
resource_type
resource_id
expires_at
created_at
```

Unique constraint:

```text
tenant_id + endpoint + idempotency_key
```

## 15.2 Rules

- Key yang sama dan payload sama mengembalikan response awal.
- Key yang sama dengan payload berbeda menghasilkan `409 IDEMPOTENCY_CONFLICT`.
- Request yang sedang diproses menghasilkan `409 IDEMPOTENCY_IN_PROGRESS` atau menunggu lock terbatas.
- Data idempotency tidak boleh digunakan lintas tenant.
- Retention minimum disarankan 24 jam untuk booking/payment.

---

# 16. Payment requirements

## 16.1 Provider abstraction

Gunakan Strategy Pattern:

```text
PaymentGatewayContract
├── MidtransGateway
├── XenditGateway
├── TripayGateway
└── ManualPaymentGateway
```

Interface minimal:

```text
createPayment()
getPaymentStatus()
cancelPayment()
refundPayment()
verifyWebhook()
parseWebhook()
```

## 16.2 Tenant payment configuration

Kredensial payment gateway:

- dienkripsi at rest;
- tidak dikirim ke Nuxt;
- tidak dicatat ke log;
- hanya dapat dibaca service pembayaran;
- mendukung central merchant atau tenant merchant sesuai strategi Sewantara.

## 16.3 Webhook endpoint

```http
POST /v1/webhooks/payments/{provider}
```

Webhook tidak menggunakan tenant header dari Nuxt.

Tenant harus ditemukan dari:

- provider merchant reference;
- payment external ID;
- metadata payment internal;
- central payment routing table.

## 16.4 Webhook security

Laravel wajib:

- verifikasi signature;
- cek timestamp jika tersedia;
- deduplicate provider event ID;
- cocokkan amount dan currency;
- cocokkan internal payment;
- proses menggunakan transaction;
- simpan raw event dengan redaction;
- dispatch event setelah commit;
- merespons cepat;
- memindahkan pekerjaan lambat ke queue.

## 16.5 Return URL

Return URL hanya untuk UX:

```text
https://kamerajember.sewantara.id/payment/return
```

Query seperti berikut tidak boleh dipercaya:

```text
?status=success
```

Nuxt harus mengambil status authoritative dari Laravel.

---

# 17. Authentication decision

## 17.1 MVP

MVP mendukung guest checkout.

Guest memperoleh:

- booking code;
- tracking token;
- optional OTP/contact verifier.

## 17.2 Customer auth future

Direkomendasikan menggunakan secure HttpOnly cookie melalui Nuxt BFF.

Cookie:

```text
HttpOnly
Secure
SameSite=Lax
Path=/
```

Untuk subdomain isolation, default cookie sebaiknya host-only agar session customer tenant A tidak otomatis dikirim ke tenant B.

Jangan menetapkan:

```text
Domain=.sewantara.id
```

kecuali memang membutuhkan central customer SSO dan risikonya telah dirancang.

Laravel Sanctum dapat dipertimbangkan untuk autentikasi first-party, tetapi keputusan final harus mempertimbangkan bahwa browser berkomunikasi ke Nuxt BFF, bukan langsung ke Laravel. Laravel 12 menyediakan middleware stateful API untuk integrasi Sanctum, tetapi pola BFF dapat pula memakai session internal atau token exchange khusus.

---

# 18. Authorization

Semua query setelah tenancy aktif harus tetap menggunakan:

- policy;
- action authorization;
- public visibility scope;
- status scope;
- ownership checks.

Contoh public product harus memenuhi:

```text
status = active
published_at <= now
is_public = true
not soft-deleted
tenant context valid
```

Jangan mengandalkan tenancy saja sebagai pengganti authorization.

---

# 19. Caching requirements

## 19.1 Public cacheable responses

Dapat di-cache:

```text
tenant profile
home
categories
catalog
product detail
blog
FAQ
testimonials
```

Cache key wajib memasukkan:

```text
tenant_id
hostname bila relevan
locale
currency
endpoint
normalized query
content version
```

Contoh:

```text
public:v1:{tenant_id}:catalog:{query_hash}
```

## 19.2 Private no-store responses

Wajib:

```http
Cache-Control: private, no-store
```

Untuk:

```text
availability
quote
booking
checkout
payment
tracking
customer profile
```

## 19.3 Cache invalidation

Saat tenant mengubah:

- branding;
- produk;
- harga;
- availability;
- promo;
- artikel;
- kontak,

backend harus menghapus cache terkait menggunakan tagged cache atau versioned cache key.

## 19.4 CDN

Response publik harus menyertakan:

```text
Vary atau cache key berdasarkan Host
```

Cloudflare tidak boleh mencampur response antara dua hostname tenant.

---

# 20. Rate limiting

Rate limiter harus menggunakan Redis pada production. Laravel menyediakan dukungan konfigurasi API throttling dan Redis-backed throttling.

Baseline:

| Endpoint            |            Limit awal |
| ------------------- | --------------------: |
| Tenant/home/catalog |     120/min/IP/tenant |
| Product detail      |     180/min/IP/tenant |
| Availability        |      60/min/IP/tenant |
| Quote               |      20/min/IP/tenant |
| Booking             |   10/10 min/IP/tenant |
| Tracking            |   10/10 min/IP/tenant |
| OTP                 | 5/hour/contact/tenant |
| Payment retry       |        5/hour/booking |
| Webhook             |     Provider-specific |

Rate limit final harus dapat dikonfigurasi.

Response:

```http
429 Too Many Requests
Retry-After: 60
```

---

# 21. Security requirements

## 21.1 Host security

- Laravel hanya menerima hostname API resmi.
- Konfigurasi trusted hosts harus membatasi host aplikasi.
- Forwarded headers hanya dipercaya dari Nginx Proxy Manager atau proxy internal.
- Jangan mempercayai semua proxy tanpa kebutuhan.
- Origin API sebaiknya dibatasi agar hanya dapat diakses Cloudflare/NPM/Nuxt network jika memungkinkan.

Stancl mendokumentasikan bahwa trusted host configuration dapat memengaruhi domain-based tenant identification. Sewantara harus mendefinisikan trusted host secara eksplisit, bukan menonaktifkan host validation tanpa pembatasan.

## 21.2 Input security

- Form Request untuk semua mutation.
- Maximum request body.
- Maximum array size.
- Reject unknown enum.
- Normalize phone/email.
- Sanitize rich text.
- Restrict uploaded MIME type.
- Scan upload bila diperlukan.
- Generate storage filename server-side.

## 21.3 Data exposure

Jangan mengirim:

- database connection;
- internal tenant ID bila tidak perlu;
- cost price;
- gateway secret;
- internal audit data;
- stack trace;
- SQL error;
- raw exception;
- complete customer contact pada tracking response.

## 21.4 SQL safety

- Eloquent/query builder.
- Tidak membuat table/database name dari input request.
- Tenant database ditentukan hanya dari tenant model valid.
- Raw SQL harus menggunakan binding.
- Sorting column menggunakan allowlist.

## 21.5 SSRF prevention

Backend tidak boleh mengambil arbitrary URL yang dikirim tenant/customer.

Untuk media remote:

- gunakan allowlist provider;
- validasi scheme;
- blok private IP ranges;
- batasi redirect;
- batasi response size;
- gunakan media ingestion pipeline.

## 21.6 Mass assignment

- Gunakan DTO/action input.
- Model `$fillable` terbatas.
- Jangan menggunakan `$request->all()` untuk persistence.
- Gunakan `$request->validated()`.

## 21.7 Secrets

- Environment/secret manager.
- Encrypt tenant provider credentials.
- Redact logs.
- Rotation support.
- Jangan commit `.env`.

---

# 22. Observability

## 22.1 Structured logging

Setiap log request minimal memiliki:

```text
request_id
tenant_id
tenant_slug
tenant_host
route
method
status_code
duration_ms
client_ip
bff_service_id
user_id nullable
booking_id nullable
payment_id nullable
```

Data sensitif harus dimasking.

## 22.2 Audit logs

Catat:

- perubahan tenant/domain;
- perubahan payment configuration;
- booking status transition;
- payment status transition;
- refund;
- cancellation;
- manual override;
- webhook processing;
- authentication failure penting;
- tenant resolution anomaly.

## 22.3 Metrics

Minimal:

```text
request count
error rate
latency p50/p95/p99
tenant resolution failure
tenant database connection failure
quote creation
booking conversion
payment success/failure
webhook failure
queue depth
expired stock hold
rate limit events
```

## 22.4 Alerts

Alert untuk:

- tenant database failure meningkat;
- webhook failure;
- payment mismatch;
- queue backlog;
- API 5xx spike;
- Redis unavailable;
- central database unavailable;
- repeated tenant-host mismatch.

---

# 23. Database transactions and concurrency

## 23.1 Quote

Quote dapat dibuat tanpa lock panjang, tetapi availability harus dihitung konsisten.

## 23.2 Booking

Booking wajib menggunakan transaction.

Pseudo-flow:

```text
Begin transaction
  → load quote for update
  → validate quote
  → lock relevant inventory/capacity rows
  → recalculate availability
  → create booking
  → create booking lines snapshot
  → create stock hold
  → create payment record
Commit
Dispatch events after commit
```

## 23.3 Preventing overselling

Strategi tergantung inventory:

- serialized unit: row-level lock pada unit;
- pooled stock: lock inventory aggregate/calendar bucket;
- capacity: unique constraint atau lock slot capacity;
- time overlap: exclusion logic + transaction.

Tidak boleh hanya mengandalkan pengecekan availability sebelum transaction.

---

# 24. Event-driven requirements

Domain events:

```text
TenantPublicWebsiteAccessed
QuoteCreated
QuoteExpired
BookingCreated
BookingAwaitingPayment
BookingReserved
BookingConfirmed
BookingCancelled
BookingExpired
PaymentCreated
PaymentPaid
PaymentFailed
PaymentExpired
PaymentRefunded
StockHoldCreated
StockHoldReleased
```

Listeners:

```text
SendCustomerNotification
SendTenantNotification
GenerateInvoice
UpdateAnalytics
ReleaseInventory
InvalidateCache
WriteAuditLog
```

Listener eksternal harus dijalankan melalui queue.

---

# 25. Recommended project structure

```text
app/
├── Domain/
│   ├── Tenancy/
│   │   ├── Actions/
│   │   ├── DTOs/
│   │   ├── Exceptions/
│   │   ├── Services/
│   │   └── ValueObjects/
│   ├── Catalog/
│   ├── Availability/
│   ├── Booking/
│   ├── Payment/
│   ├── Customer/
│   └── Content/
├── Http/
│   ├── Controllers/Api/V1/Public/
│   ├── Middleware/
│   ├── Requests/Api/V1/Public/
│   └── Resources/Api/V1/Public/
├── Infrastructure/
│   ├── Payment/
│   ├── Cache/
│   ├── Persistence/
│   └── Observability/
├── Models/
│   ├── Central/
│   └── Tenant/
└── Support/
    ├── Enums/
    ├── Money/
    └── RequestId/
```

Patterns:

```text
Controller
→ Form Request
→ Action
→ Domain Service
→ Repository/Eloquent
→ Resource
```

Controller harus tipis.

---

# 26. Example route design

```php
Route::prefix('v1/public')
    ->middleware([
        'force.json',
        'request.id',
        'bff.auth',
        'tenant.headers',
        'tenant.resolve',
        'tenant.eligible',
        'tenant.initialize',
        'tenant.rate-limit',
    ])
    ->group(function (): void {
        Route::get('/tenant', TenantController::class);
        Route::get('/home', HomeController::class);
        Route::get('/categories', CategoryIndexController::class);
        Route::get('/catalog', ProductIndexController::class);
        Route::get('/catalog/{product:public_slug}', ProductShowController::class);
        Route::get('/catalog/{product:public_slug}/availability', AvailabilityController::class);

        Route::post('/bookings/quote', QuoteStoreController::class);
        Route::post('/bookings', BookingStoreController::class);

        Route::post('/bookings/{bookingCode}/tracking', BookingTrackingController::class);
        Route::get('/payments/{publicPaymentId}', PaymentShowController::class);

        Route::get('/blog', ArticleIndexController::class);
        Route::get('/blog/{article:slug}', ArticleShowController::class);
        Route::get('/sitemap', SitemapController::class);
    });
```

Webhook berada di group terpisah:

```php
Route::prefix('v1/webhooks/payments')
    ->middleware([
        'force.json',
        'request.id',
        'webhook.rate-limit',
    ])
    ->group(function (): void {
        Route::post('/{provider}', PaymentWebhookController::class);
    });
```

---

# 27. Contract versioning

API menggunakan version prefix:

```text
/v1/public
```

Breaking changes membutuhkan:

```text
/v2/public
```

Perubahan non-breaking yang diperbolehkan:

- menambah field optional;
- menambah endpoint;
- menambah enum hanya jika consumer dirancang tolerant;
- menambah metadata.

Perubahan breaking:

- menghapus field;
- mengganti tipe field;
- mengganti arti status;
- mengganti unit uang;
- mengganti timezone semantics;
- mengubah requirement authentication.

---

# 28. Error code catalog

```text
AUTH_SERVICE_REQUIRED
AUTH_SERVICE_INVALID
REQUEST_INVALID
VALIDATION_ERROR
TENANT_NOT_FOUND
TENANT_SUSPENDED
TENANT_MAINTENANCE
TENANT_SUBSCRIPTION_EXPIRED
TENANT_PUBLIC_WEB_DISABLED
TENANT_SERVICE_UNAVAILABLE
RESOURCE_NOT_FOUND
PRODUCT_UNAVAILABLE
AVAILABILITY_CHANGED
QUOTE_NOT_FOUND
QUOTE_EXPIRED
QUOTE_ALREADY_USED
COUPON_INVALID
COUPON_EXPIRED
BOOKING_CONFLICT
BOOKING_NOT_FOUND
BOOKING_NOT_CANCELLABLE
PAYMENT_METHOD_UNAVAILABLE
PAYMENT_INITIALIZATION_FAILED
PAYMENT_NOT_FOUND
PAYMENT_ALREADY_PAID
PAYMENT_EXPIRED
TRACKING_VERIFICATION_FAILED
IDEMPOTENCY_REQUIRED
IDEMPOTENCY_CONFLICT
IDEMPOTENCY_IN_PROGRESS
RATE_LIMITED
INTERNAL_ERROR
```

---

# 29. Testing requirements

## 29.1 Unit tests

- tenant slug normalization;
- hostname normalization;
- reserved slug;
- tenant eligibility;
- price calculation;
- coupon;
- booking rules;
- status transitions;
- payment event mapping;
- request signature;
- money value object.

## 29.2 Feature tests

- tenant ditemukan;
- tenant tidak ditemukan;
- tenant-host mismatch;
- domain milik tenant lain;
- tenant suspended;
- subscription expired;
- public web disabled;
- custom domain unverified;
- tenancy initialization failure;
- no cross-tenant product access;
- quote expiry;
- duplicate booking idempotency;
- booking concurrency;
- tracking verifier;
- webhook signature.

## 29.3 Integration tests

- PostgreSQL central;
- PostgreSQL tenant;
- Redis cache;
- Redis queue;
- payment sandbox;
- tenancy provisioning;
- tenant database switching.

## 29.4 Security tests

- spoofed `X-Tenant`;
- spoofed `X-Tenant-Host`;
- host header injection;
- forged forwarded headers;
- service token invalid;
- SQL injection filter/sort;
- mass assignment;
- IDOR public payment;
- cross-tenant booking code;
- replay webhook;
- replay booking request;
- rate-limit bypass;
- HTML injection article;
- oversized payload.

## 29.5 Performance tests

Target awal:

```text
GET tenant/home/catalog p95 < 300 ms tanpa cold start
GET product p95 < 300 ms
POST quote p95 < 700 ms
POST booking p95 < 1.5 s di luar latency provider
```

Load test wajib menggunakan banyak tenant slug agar cache isolation teruji.

---

# 30. Acceptance criteria

## Tenant resolution

- Request dengan slug dan hostname valid menginisialisasi tenant yang benar.
- Tenant yang tidak ditemukan menghasilkan `404`.
- Hostname milik tenant lain tidak dapat digunakan dengan slug berbeda.
- Tenant inactive tidak dapat mengakses endpoint booking.
- Failure tenancy tidak pernah fallback ke central connection.
- Request context dibersihkan setelah response.

## Security

- Browser tidak dapat memilih tenant Laravel secara langsung.
- Public API hanya menerima service credential valid.
- Service token tidak pernah muncul dalam response atau log.
- Tenant data tidak bercampur pada cache.
- Tidak ada sequential public identifier untuk payment/tracking.
- Price dan payment status tidak dipercaya dari browser.

## Booking

- Quote memiliki ID dan expiry.
- Booking tanpa idempotency key ditolak.
- Duplicate request tidak membuat dua booking.
- Availability diperiksa ulang dalam transaction.
- Stock tidak oversold pada concurrent request.
- Booking lines menyimpan snapshot harga.

## Payment

- Status paid hanya berasal dari provider verification/webhook.
- Duplicate webhook aman.
- Amount mismatch tidak menandai booking paid.
- Return URL tidak mengubah status.
- Webhook tetap dapat diproses saat tenant suspended.

## Operations

- Semua response memiliki request ID.
- Semua error production tidak membocorkan stack trace.
- Log memiliki tenant context.
- Health endpoint tersedia.
- Queue job retry dan dead-letter handling terdokumentasi.

---

# 31. Environment variables

```dotenv
APP_NAME=Sewantara
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.sewantara.id

CENTRAL_DOMAIN=sewantara.id
API_DOMAIN=api.sewantara.id

TENANT_PUBLIC_WEB_ENABLED=true
TENANT_DEFAULT_TIMEZONE=Asia/Jakarta
TENANT_DEFAULT_LOCALE=id-ID
TENANT_DEFAULT_CURRENCY=IDR

BFF_SERVICE_TOKEN_CURRENT=
BFF_SERVICE_TOKEN_PREVIOUS=

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

TENANT_RESOLUTION_CACHE_TTL=300
QUOTE_TTL_MINUTES=15
PAYMENT_TTL_MINUTES=30
STOCK_HOLD_TTL_MINUTES=20
IDEMPOTENCY_TTL_HOURS=24

TRUSTED_PROXY_IPS=
TRUSTED_BFF_IPS=
```

Secret tidak boleh menggunakan prefix public.

---

# 32. Rollout plan

## Phase 1 — Tenant resolution

- central tenant model;
- domain model;
- tenant resolver;
- BFF authentication;
- eligibility middleware;
- standardized error response;
- tenant profile endpoint.

## Phase 2 — Public read API

- home;
- categories;
- catalog;
- product;
- blog;
- cache;
- SEO data.

## Phase 3 — Availability and quote

- booking modes;
- inventory strategy;
- availability;
- quote;
- coupon;
- fee/tax/deposit.

## Phase 4 — Booking

- guest checkout;
- idempotency;
- stock hold;
- booking transaction;
- tracking.

## Phase 5 — Payment

- provider abstraction;
- payment creation;
- webhook;
- reconciliation;
- refund foundation.

## Phase 6 — Hardening

- audit log;
- metrics;
- alerts;
- load testing;
- security testing;
- disaster recovery;
- backup validation.

---

# 33. Definition of done

Module dianggap siap production apabila:

- seluruh acceptance criteria terpenuhi;
- kontrak response telah disepakati dengan Nuxt;
- tenant resolution memiliki automated test lengkap;
- tidak ada cross-tenant data leak pada test;
- booking concurrency test berhasil;
- payment webhook verification berhasil;
- OpenAPI specification tersedia;
- migration dan rollback diuji;
- backup tenant dan central database diuji;
- rate limit aktif;
- Redis aktif;
- queue worker dan scheduler aktif;
- observability tersedia;
- `APP_DEBUG=false`;
- trusted proxy dan trusted host dikonfigurasi;
- Cloudflare/NPM hanya meneruskan request ke origin yang benar;
- demo mode Nuxt dapat dimatikan tanpa perubahan komponen halaman.

---

# 34. Final architecture decision

Arsitektur resmi Sewantara Tenant Public API:

```text
Nuxt Tenant Web
    │
    │ Same-origin browser access
    │ Server-to-server API request
    ▼
Laravel Public Tenant API
    │
    ├── BFF service authentication
    ├── tenant slug validation
    ├── hostname validation
    ├── central tenant lookup
    ├── domain ownership verification
    ├── tenant status verification
    ├── subscription verification
    ├── tenancy initialization
    ├── policy and validation
    ├── authoritative pricing
    ├── transaction and locking
    └── standardized response
```

Prinsip utamanya:

> Tenant tidak dianggap valid hanya karena Nuxt mengirim `X-Tenant`. Tenant baru dianggap valid setelah slug, hostname, domain ownership, status, subscription, dan database tenant berhasil diverifikasi Laravel.
