# Mitra Dashboard API Guide

Dokumen ini merangkum alur API untuk aplikasi dashboard mitra: memilih paket,
registrasi tenant, login owner, menyelesaikan onboarding, lalu memakai endpoint
operasional tenant.

## Base URL

```text
Local:      http://localhost
API prefix: /api
```

Semua request sebaiknya mengirim header:

```http
Accept: application/json
Content-Type: application/json
```

## Format Response

Response sukses umum:

```json
{
  "success": true,
  "message": "Berhasil.",
  "data": {},
  "meta": null
}
```

Response error umum:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Pesan error.",
    "details": null
  }
}
```

Error validasi Laravel memakai HTTP `422` dan biasanya mengembalikan daftar
field yang gagal divalidasi.

## Ringkasan Alur Dashboard Mitra

1. Dashboard mengambil katalog template bisnis.
2. Dashboard mengambil paket langganan aktif.
3. Calon mitra registrasi tenant dan owner.
4. Backend membuat tenant, domain, owner, branch utama, database tenant, dan
   subscription trial.
5. Owner login memakai email dan password yang dibuat saat registrasi.
6. Dashboard menyimpan `access_token`, `tenant.id`, dan branch aktif.
7. Dashboard memanggil endpoint tenant dengan path `/api/tenant/{tenant}`.
8. Selama status tenant `onboarding`, dashboard hanya membuka endpoint setup.
9. Setelah checklist onboarding lengkap, dashboard memanggil `go-live`.
10. Saat tenant sudah `active`, dashboard dapat memakai fitur operasional.

## Public Central API

Endpoint central tidak membutuhkan bearer token.

### Health Check

```http
GET /api/shared/health
```

Gunakan untuk mengecek backend hidup sebelum dashboard melakukan call penting.

### Daftar Template Bisnis

```http
GET /api/central/business-templates
```

Response:

```json
{
  "success": true,
  "data": [
    {
      "code": "camera_rental",
      "name": "Rental Kamera",
      "description": "Preset untuk usaha rental kamera.",
      "icon": "camera",
      "configuration": {},
      "version": 1
    }
  ]
}
```

Pakai `code` sebagai nilai `business_type` saat registrasi tenant.

### Daftar Paket

```http
GET /api/central/plans
```

Query opsional:

```http
GET /api/central/plans?billing_interval=month&currency=IDR
```

Response:

```json
{
  "success": true,
  "message": "Daftar paket berhasil diambil.",
  "data": [
    {
      "id": 1,
      "name": "Starter",
      "slug": "starter",
      "description": "Paket awal.",
      "price": "99000.00",
      "signup_fee": "0.00",
      "currency": "IDR",
      "invoice_period": 1,
      "invoice_interval": "month",
      "trial_period": 14,
      "trial_interval": "day",
      "features": [
        {
          "slug": "branches",
          "value": "1"
        }
      ]
    }
  ],
  "meta": null
}
```

Pakai `id` sebagai `plan_id` saat registrasi tenant.

## Registrasi Tenant

```http
POST /api/central/auth/register
```

Endpoint ini punya throttle `5 request / menit`.

Request:

```json
{
  "business_name": "Rental Kamera Jember",
  "business_type": "camera_rental",
  "subdomain": "rentalkamerajember",
  "owner": {
    "name": "Owner Rental",
    "email": "owner@example.test",
    "phone": "081234567890",
    "password": "Password#123",
    "password_confirmation": "Password#123"
  },
  "plan_id": 1,
  "billing_interval": "month",
  "terms_accepted": true
}
```

Validasi penting:

| Field | Rule |
|---|---|
| `business_name` | required, max 150 |
| `business_type` | required, harus `code` template aktif |
| `subdomain` | required, 3-63 karakter, huruf kecil/angka/tanda hubung, unik |
| `owner.email` | required, email valid, unik di central user |
| `owner.password` | required, minimal 8, mixed case, angka, simbol, confirmed |
| `plan_id` | required, plan aktif |
| `billing_interval` | `month` atau `year` |
| `terms_accepted` | harus accepted |

Response `201`:

```json
{
  "success": true,
  "message": "Akun usaha berhasil dibuat. Selesaikan penyiapan awal agar akun dapat digunakan.",
  "data": {
    "tenant": {
      "id": "9b7f4e4a-4c6a-4b7a-a51b-bd55e9f0d001",
      "name": "Rental Kamera Jember",
      "slug": "rental-kamera-jember",
      "status": "onboarding",
      "timezone": "Asia/Jakarta",
      "currency": "IDR"
    },
    "domain": {
      "domain": "rentalkamerajember.localhost",
      "url": "http://rentalkamerajember.localhost"
    },
    "owner": {
      "id": 1,
      "name": "Owner Rental",
      "email": "owner@example.test"
    },
    "subscription": {
      "name": "main",
      "plan": "starter",
      "status": "trialing",
      "trial_ends_at": "2026-08-13T21:00:00+07:00"
    }
  },
  "meta": null
}
```

Simpan `data.tenant.id`. Nilai ini dipakai sebagai `{tenant}` pada endpoint
tenant, misalnya:

```http
/api/tenant/9b7f4e4a-4c6a-4b7a-a51b-bd55e9f0d001/me
```

## Login Mitra

```http
POST /api/tenant/auth/login
```

Endpoint login tidak memakai `{tenant}` di URL. Backend resolve tenant dari
relasi akun central berdasarkan email.

Request:

```json
{
  "email": "owner@example.test",
  "password": "Password#123",
  "device_name": "mitra-dashboard"
}
```

Response:

```json
{
  "success": true,
  "message": "Berhasil masuk.",
  "data": {
    "token_type": "Bearer",
    "access_token": "1|plain-text-token",
    "user": {
      "id": 1,
      "tenant_id": "9b7f4e4a-4c6a-4b7a-a51b-bd55e9f0d001",
      "name": "Owner Rental",
      "email": "owner@example.test",
      "is_active": true
    }
  }
}
```

Simpan:

| Data | Sumber | Kegunaan |
|---|---|---|
| `access_token` | `data.access_token` | Header `Authorization` |
| `tenant_id` | `data.user.tenant_id` atau response registrasi | Path tenant |
| `branch_id` | response `/me` atau branch utama hasil onboarding | Header `X-Branch-Id` |

Error login penting:

| HTTP | Code | Arti |
|---|---|---|
| `401` | `INVALID_CREDENTIALS` | Email/password salah |
| `403` | `USER_INACTIVE` | User tenant tidak aktif |
| `423` | `TENANT_SUSPENDED` | Tenant disuspend |
| `423` | `TENANT_INACCESSIBLE` | Tenant tidak bisa diakses |

## Header Tenant Setelah Login

Semua endpoint tenant selain login membutuhkan:

```http
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
Accept: application/json
Content-Type: application/json
```

`X-Branch-Id` wajib berupa ID cabang aktif yang dimiliki user. Backend juga
mengembalikan header response `X-Branch-Id` berisi branch yang sedang aktif.

Error branch penting:

| HTTP | Code | Arti |
|---|---|---|
| `422` | `BRANCH_HEADER_REQUIRED` | Header `X-Branch-Id` belum dikirim |
| `422` | `BRANCH_HEADER_INVALID` | Nilai branch bukan integer valid |
| `403` | `BRANCH_ACCESS_DENIED` | User tidak punya akses ke branch |

## Current Tenant Session

```http
GET /api/tenant/{tenant}/me
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
```

Response:

```json
{
  "success": true,
  "data": {
    "tenant": {
      "id": "9b7f4e4a-4c6a-4b7a-a51b-bd55e9f0d001",
      "name": "Rental Kamera Jember",
      "status": "onboarding"
    },
    "branch": {
      "id": 1,
      "name": "Cabang Utama",
      "code": "MAIN",
      "is_active": true
    },
    "user": {
      "id": 1,
      "name": "Owner Rental",
      "email": "owner@example.test"
    }
  }
}
```

Dashboard bisa memakai endpoint ini untuk hydrate session setelah refresh page.

## Logout

```http
POST /api/tenant/{tenant}/auth/logout
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
```

Response:

```json
{
  "success": true,
  "message": "Berhasil keluar.",
  "data": null
}
```

Logout menghapus token aktif saat ini.

## Guided Onboarding

Selama tenant masih `onboarding`, fitur operasional seperti customer, booking,
maintenance, availability, dan report akan mengembalikan:

```json
{
  "success": false,
  "error": {
    "code": "TENANT_ONBOARDING_REQUIRED",
    "message": "Selesaikan penyiapan awal sebelum menggunakan fitur operasional.",
    "details": null
  }
}
```

Endpoint setup berikut tetap dapat dipakai selama subscription aktif.

### Ambil Progress Onboarding

```http
GET /api/tenant/{tenant}/onboarding
```

Response:

```json
{
  "success": true,
  "data": {
    "status": "in_progress",
    "current_step": "rental_configuration",
    "completed_steps": ["business_setup", "business_template"],
    "profile": {},
    "rental_configuration": {},
    "payment_methods": [
      {
        "method": "cash",
        "is_enabled": true,
        "is_configured": false
      }
    ],
    "checklist": {
      "business": true,
      "template": true,
      "rental_configuration": false,
      "inventory": false,
      "pricing": false,
      "booking": false,
      "payment": false,
      "branch": true,
      "subscription": true
    }
  }
}
```

### Step 1: Informasi Usaha

```http
PATCH /api/tenant/{tenant}/onboarding/business
```

Request:

```json
{
  "business_name": "Rental Kamera Jember",
  "timezone": "Asia/Jakarta",
  "currency": "IDR",
  "branch_name": "Cabang Utama",
  "operating_hours": {
    "monday": {"open": "09:00", "close": "21:00"},
    "tuesday": {"open": "09:00", "close": "21:00"},
    "wednesday": {"open": "09:00", "close": "21:00"},
    "thursday": {"open": "09:00", "close": "21:00"},
    "friday": {"open": "09:00", "close": "21:00"},
    "saturday": {"open": "09:00", "close": "18:00"},
    "sunday": {"closed": true}
  }
}
```

### Step 2: Konfigurasi Rental

```http
PATCH /api/tenant/{tenant}/onboarding/rental
```

Request:

```json
{
  "rental_model": "per_day",
  "booking_strategy": "date_range",
  "allocation_strategy": "auto_assign",
  "slot_duration_minutes": null,
  "enable_waiting_list": false,
  "allow_extend_booking": true,
  "realtime_availability": true
}
```

Kombinasi yang didukung:

| `rental_model` | `booking_strategy` | Pricing yang dibutuhkan |
|---|---|---|
| `per_hour` | `queue` atau `date_range` | `hourly` |
| `per_day` | `date_range` | `daily` |
| `session` | `session` | `event` |

Jika `booking_strategy` adalah `queue` atau `session`,
`slot_duration_minutes` wajib diisi.

### Step 3: Setup Inventory

Buat kategori, produk, lalu unit/stok. Setelah minimal satu unit atau stok
tersedia, tandai inventory selesai.

```http
POST /api/tenant/{tenant}/onboarding/inventory/complete
```

Response semua endpoint onboarding update adalah snapshot onboarding terbaru:

```json
{
  "success": true,
  "message": "Pengaturan persediaan telah berhasil diverifikasi.",
  "data": {
    "status": "in_progress",
    "current_step": "pricing",
    "completed_steps": ["business_setup", "business_template", "rental_configuration", "inventory_setup"],
    "checklist": {}
  }
}
```

### Step 4: Setup Pricing

Buat harga produk sesuai model rental. Setelah minimal satu harga aktif
kompatibel tersedia, tandai pricing selesai.

```http
POST /api/tenant/{tenant}/onboarding/pricing/complete
```

### Step 5: Konfigurasi Booking

```http
PATCH /api/tenant/{tenant}/onboarding/booking
```

Request:

```json
{
  "allow_online_booking": true,
  "allow_walk_in": true,
  "enable_waiting_list": false,
  "allocation_strategy": "auto_assign",
  "auto_reminder": true,
  "auto_cancel_unpaid": true,
  "auto_cancel_minutes": 30
}
```

Jika `auto_cancel_unpaid=true`, `auto_cancel_minutes` wajib diisi minimal `5`.

### Step 6: Konfigurasi Pembayaran

```http
PATCH /api/tenant/{tenant}/onboarding/payments
```

Request:

```json
{
  "methods": [
    {
      "method": "cash",
      "is_enabled": true,
      "configuration": null
    },
    {
      "method": "transfer",
      "is_enabled": true,
      "configuration": {
        "bank_name": "BCA",
        "account_number": "1234567890",
        "account_name": "Rental Kamera Jember"
      }
    }
  ]
}
```

`method` yang tersedia:

```text
cash, transfer, midtrans, deposit, pay_later
```

Minimal satu metode harus aktif. `method` tidak boleh duplikat dalam satu
request.

### Step 7: Go Live

```http
POST /api/tenant/{tenant}/onboarding/go-live
```

Go Live memvalidasi checklist:

```text
business, template, rental_configuration, inventory, pricing, booking,
payment, branch, subscription
```

Jika sukses, tenant berubah dari `onboarding` menjadi `active`.

## Endpoint Awal Untuk Setup Inventory

Endpoint ini memakai branch aktif dari header `X-Branch-Id`.

### Kategori

```http
GET    /api/tenant/{tenant}/categories
POST   /api/tenant/{tenant}/categories
GET    /api/tenant/{tenant}/categories/{category}
PATCH  /api/tenant/{tenant}/categories/{category}
DELETE /api/tenant/{tenant}/categories/{category}
```

Query list:

```text
search, parent_id, roots_only, is_active, per_page
```

Create:

```json
{
  "parent_id": null,
  "name": "Kamera",
  "slug": "kamera",
  "description": "Peralatan kamera",
  "image_path": "categories/kamera.jpg",
  "sort_order": 10,
  "is_active": true
}
```

### Produk

```http
GET    /api/tenant/{tenant}/products
POST   /api/tenant/{tenant}/products
GET    /api/tenant/{tenant}/products/{product}
PATCH  /api/tenant/{tenant}/products/{product}
DELETE /api/tenant/{tenant}/products/{product}
```

Query list:

```text
search, category_id, inventory_type, is_active, per_page
```

Create:

```json
{
  "category_id": 1,
  "name": "Sony Alpha A7 IV",
  "slug": "sony-alpha-a7-iv",
  "sku": "CAM-SONY-A7IV",
  "brand": "Sony",
  "model": "A7 IV",
  "description": "Mirrorless full-frame.",
  "inventory_type": "serialized",
  "default_pricing_type": "daily",
  "minimum_rental_duration": 1,
  "deposit_amount": 1000000,
  "late_fee_amount": 250000,
  "is_featured": true,
  "is_active": true
}
```

`inventory_type`:

```text
serialized, quantity
```

`default_pricing_type`:

```text
hourly, daily, weekly, monthly, event, custom
```

### Unit Produk Serialized

Untuk produk `inventory_type=serialized`:

```http
GET  /api/tenant/{tenant}/product-units
POST /api/tenant/{tenant}/product-units
```

Query list:

```text
product_id, status, per_page
```

Create:

```json
{
  "product_id": 1,
  "unit_code": "A7IV-001",
  "barcode": "BR-A7IV-001",
  "qr_code": "QR-A7IV-001",
  "serial_number": "SN123456",
  "status": "available",
  "condition": "good",
  "purchase_date": "2026-07-30",
  "purchase_price": 32000000,
  "current_meter": 0,
  "meter_unit": "shot",
  "notes": "Unit utama"
}
```

`branch_id` tidak boleh dikirim. Backend mengambil branch dari `X-Branch-Id`.

### Stok Produk Quantity

Untuk produk `inventory_type=quantity`:

```http
GET  /api/tenant/{tenant}/inventory/stocks
POST /api/tenant/{tenant}/inventory/stocks/adjust
```

Request adjust:

```json
{
  "product_id": 2,
  "quantity": 5,
  "notes": "Stok awal"
}
```

Nilai `quantity` positif menambah stok, nilai negatif mengurangi stok.
Pengurangan tidak boleh melewati jumlah barang yang sedang reserved, rented,
maintenance, damaged, atau lost.

### Harga Produk

```http
GET    /api/tenant/{tenant}/product-prices
POST   /api/tenant/{tenant}/product-prices
PATCH  /api/tenant/{tenant}/product-prices/{productPrice}
DELETE /api/tenant/{tenant}/product-prices/{productPrice}
```

Query list:

```text
product_id, per_page
```

Create:

```json
{
  "product_id": 1,
  "pricing_type": "daily",
  "duration": 1,
  "price": 150000,
  "start_at": null,
  "end_at": null,
  "is_active": true
}
```

`branch_id` tidak boleh dikirim. Harga tersimpan untuk branch dari
`X-Branch-Id`.

## Endpoint Operasional Setelah Active

Endpoint berikut hanya dapat dipakai setelah tenant `active` dan subscription
masih aktif.

```http
GET  /api/tenant/{tenant}/reports/dashboard
GET  /api/tenant/{tenant}/customers
POST /api/tenant/{tenant}/customers
GET  /api/tenant/{tenant}/bookings
POST /api/tenant/{tenant}/bookings
GET  /api/tenant/{tenant}/availability/check
GET  /api/tenant/{tenant}/maintenance
POST /api/tenant/{tenant}/maintenance
```

Error akses penting:

| HTTP | Code | Arti |
|---|---|---|
| `401` | `UNAUTHENTICATED` | Bearer token tidak ada/tidak valid |
| `403` | `TENANT_ACCESS_DENIED` | User tidak cocok dengan tenant di path |
| `403` | `SUBSCRIPTION_REQUIRED` | Subscription utama belum tersedia |
| `423` | `SUBSCRIPTION_EXPIRED` | Subscription sudah tidak aktif |
| `423` | `TENANT_ONBOARDING_REQUIRED` | Tenant belum go-live |
| `404` | `TENANT_NOT_FOUND` | `{tenant}` tidak ditemukan |

## Contoh Implementasi FE

### Register

```ts
const response = await fetch("http://localhost/api/central/auth/register", {
  method: "POST",
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
  body: JSON.stringify(payload),
});

const result = await response.json();

if (result.success) {
  localStorage.setItem("tenant_id", result.data.tenant.id);
}
```

### Login

```ts
const response = await fetch("http://localhost/api/tenant/auth/login", {
  method: "POST",
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
  body: JSON.stringify({
    email,
    password,
    device_name: "mitra-dashboard",
  }),
});

const result = await response.json();

if (result.success) {
  localStorage.setItem("access_token", result.data.access_token);
  localStorage.setItem("tenant_id", result.data.user.tenant_id);
}
```

### Authenticated Tenant Request

```ts
async function tenantFetch(path: string, options: RequestInit = {}) {
  const tenantId = localStorage.getItem("tenant_id");
  const token = localStorage.getItem("access_token");
  const branchId = localStorage.getItem("branch_id") ?? "1";

  return fetch(`http://localhost/api/tenant/${tenantId}${path}`, {
    ...options,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
      "X-Branch-Id": branchId,
      ...options.headers,
    },
  });
}
```

## Catatan Integrasi

- Registrasi tidak otomatis login. Dashboard perlu memanggil endpoint login
  setelah registrasi sukses.
- Gunakan `tenant.id`, bukan domain, untuk path tenant API.
- Domain dari response registrasi berguna untuk routing produk di sisi app,
  tetapi backend tenant API saat ini memakai path `/api/tenant/{tenant}`.
- Jangan kirim `branch_id` pada endpoint unit, stok, atau harga. Backend
  menentukan branch dari header `X-Branch-Id`.
- Untuk onboarding awal, branch utama biasanya bernilai `1` pada database tenant
  baru, tetapi dashboard tetap sebaiknya hydrate session lewat `/me`.
