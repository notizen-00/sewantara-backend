# API Specification

Dokumentasikan seluruh endpoint REST API beserta request, response, validation, dan permission.

## Tenant Registration

```http
POST /api/central/auth/register
```

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

`business_type` wajib menggunakan `code` dari:

```http
GET /api/central/business-templates
```

Registrasi menghasilkan tenant berstatus `onboarding`, bukan langsung
`active`. Owner tetap dapat login, tetapi endpoint operasional mengembalikan
`TENANT_ONBOARDING_REQUIRED` sampai proses Go Live selesai.

## Tenant Authentication

```http
POST http://localhost/api/tenant/auth/login
```

```json
{
  "email": "owner@example.test",
  "password": "<TENANT_PASSWORD>",
  "device_name": "web"
}
```

Response menghasilkan `access_token` Sanctum. Kirim token pada endpoint tenant
yang dilindungi:

Tenant tidak perlu dikirim melalui URL atau body. Sistem mengenali tenant
secara otomatis melalui relasi akun pusat berdasarkan alamat email.

```http
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
Accept: application/json
```

Seluruh endpoint tenant selain login wajib menerima header:

```http
X-Branch-Id: 2
```

Permintaan tanpa header ditolak dengan kode `BRANCH_HEADER_REQUIRED`. Header
hanya dapat menunjuk cabang aktif yang terhubung dengan pengguna. ID cabang
aktif selalu dikembalikan melalui response header `X-Branch-Id`.

Logout dan revoke token aktif:

```http
POST /api/tenant/{tenant}/auth/logout
```

Endpoint `GET /api/tenant/{tenant}/me` mengembalikan konteks tenant, cabang,
pengguna, dan langganan utama. Objek `subscription` mencakup status langganan,
periode trial/tagihan, paket aktif, harga, interval tagihan, serta feature
limit yang dapat langsung dikonsumsi aplikasi klien.

## Guided Tenant Onboarding

```text
GET   /api/tenant/{tenant}/onboarding
PATCH /api/tenant/{tenant}/onboarding/business
PATCH /api/tenant/{tenant}/onboarding/rental
POST  /api/tenant/{tenant}/onboarding/inventory/complete
POST  /api/tenant/{tenant}/onboarding/pricing/complete
PATCH /api/tenant/{tenant}/onboarding/booking
PATCH /api/tenant/{tenant}/onboarding/payments
POST  /api/tenant/{tenant}/onboarding/go-live
```

Urutan setup adalah business, template, rental configuration, inventory,
pricing, booking configuration, payment configuration, lalu Go Live.

Inventory dapat disiapkan melalui endpoint category, product, product unit,
dan inventory stock. Harga disiapkan melalui:

```text
GET    /api/tenant/{tenant}/product-prices
POST   /api/tenant/{tenant}/product-prices
PATCH  /api/tenant/{tenant}/product-prices/{productPrice}
DELETE /api/tenant/{tenant}/product-prices/{productPrice}
```

Go Live memvalidasi business profile dan operating hours, rental
configuration, inventory, harga yang kompatibel dengan rental model, booking
configuration, minimal satu payment method, branch aktif, dan subscription.

Konfigurasi payment method disimpan terenkripsi pada database tenant.

## Branch Context dan Sinkronisasi

Kategori dan produk merupakan master data tingkat tenant sehingga langsung
tersedia di seluruh cabang tanpa duplikasi. Harga, stok, unit berserial,
pemesanan, dan pemeliharaan mengikuti cabang pada header `X-Branch-Id`.

Sinkronisasi dari cabang aktif menuju cabang tujuan:

```http
POST /api/tenant/{tenant}/branches/{branch}/sync-master-data
X-Branch-Id: 1
```

```json
{
  "sync_prices": true,
  "prepare_stocks": true,
  "overwrite_prices": false
}
```

Sinkronisasi menyalin harga khusus cabang dan menyiapkan struktur stok kosong
secara idempoten. Jumlah stok fisik dan unit berserial tidak disalin karena
keduanya harus mengikuti kondisi barang nyata di masing-masing cabang.

Rental engine mendukung:

| Rental model | Pricing | Booking strategy umum |
|---|---|---|
| `per_hour` | `hourly` | `queue` atau `date_range` |
| `per_day` | `daily` | `date_range` |
| `session` | `event` | `session` |

`auto_assign` membuat engine memilih unit tersedia tanpa bergantung pada nama
kategori. Queue dan session menggunakan `slot_duration_minutes`; jika
`end_at` tidak dikirim, engine membentuk waktu selesai dari durasi slot.

## Product Engine (Rental/Booking/Membership/Sales)

Tenant dapat mengaktifkan lebih dari satu engine sekaligus (mis. tenant rental
PlayStation menjalankan Booking sekaligus Sales). Rental dan Booking adalah
engine `is_core` (selalu aktif, tidak dapat dinonaktifkan). Membership dan
Sales adalah engine tambahan berbayar yang harus diaktifkan eksplisit; harga
langganan tenant terkomposisi dari setiap engine berbayar yang aktif.

```text
GET  /api/tenant/{tenant}/engines
POST /api/tenant/{tenant}/engines/enable
POST /api/tenant/{tenant}/engines/disable
```

```json
{
  "engine_code": "membership"
}
```

`engine_code` menerima `rental`, `booking`, `membership`, atau `sales`.
Response `GET /engines` berupa array katalog seluruh engine aktif di sistem:

```json
{
  "success": true,
  "data": [
    {
      "code": "membership",
      "name": "Membership",
      "description": "...",
      "is_core": false,
      "monthly_price": "50000.00",
      "is_enabled": true,
      "price_snapshot": "50000.00",
      "enabled_at": "2026-08-04T10:00:00+00:00"
    }
  ]
}
```

Menonaktifkan engine `is_core` (rental/booking) ditolak dengan validation
error pada field `engine_code`. Menonaktifkan engine yang belum aktif juga
ditolak dengan pesan serupa.

Endpoint Membership (`/memberships`) dan Sales Order (`/sales-orders`)
dilindungi middleware `tenant.engine:{code}`; permintaan ke tenant yang belum
mengaktifkan engine terkait ditolak `403` dengan kode `ENGINE_NOT_ENABLED`:

```json
{
  "success": false,
  "error": {
    "code": "ENGINE_NOT_ENABLED",
    "message": "Engine membership belum diaktifkan untuk akun usaha ini.",
    "details": null
  }
}
```

Setiap engine hanya mengizinkan `product_type` tertentu pada produk:

| Engine | Product type yang diizinkan |
|---|---|
| `rental` | `vehicle`, `equipment`, `accommodation` |
| `booking` | `space`, `service` |
| `membership` | `membership`, `package` |
| `sales` | `goods` |

## Membership

```text
GET   /api/tenant/{tenant}/memberships
POST  /api/tenant/{tenant}/memberships
GET   /api/tenant/{tenant}/memberships/{membership}
PATCH /api/tenant/{tenant}/memberships/{membership}
```

Membutuhkan engine `membership` aktif. `product_id` wajib merujuk produk
bertipe `engine_code = membership` milik tenant yang sama.

Contoh request create:

```json
{
  "product_id": 40,
  "customer_id": 12,
  "starts_on": "2026-08-05",
  "ends_on": "2026-09-04",
  "price_amount": 150000,
  "notes": "Paket bulanan gym"
}
```

`branch_id` opsional, default ke cabang aktif pada `X-Branch-Id`.
`membership_number` dan `status` (`pending`) dibuat otomatis saat create.
Query list mendukung `status` dan `per_page`. Status yang tersedia untuk
update: `pending`, `active`, `frozen`, `expired`, `renewed`, `cancelled`.

## Sales Order

```text
GET   /api/tenant/{tenant}/sales-orders
POST  /api/tenant/{tenant}/sales-orders
GET   /api/tenant/{tenant}/sales-orders/{salesOrder}
PATCH /api/tenant/{tenant}/sales-orders/{salesOrder}
```

Membutuhkan engine `sales` aktif. Setiap item wajib merujuk produk bertipe
`engine_code = sales` milik tenant yang sama.

Contoh request create:

```json
{
  "customer_id": 12,
  "notes": "Pembelian aksesoris",
  "items": [
    { "product_id": 55, "quantity": 2, "unit_price": 75000 }
  ]
}
```

`order_number`, `status` (`draft`), dan `total_amount` (dihitung dari
`quantity * unit_price` seluruh item) dibuat otomatis saat create. Status
yang tersedia untuk update: `draft`, `pending`, `completed`, `cancelled`.

## Product Category Master

ID category, parent, product, branch, customer, dan product unit menggunakan
integer auto-increment, bukan UUID.

```text
GET    /api/tenant/{tenant}/categories
POST   /api/tenant/{tenant}/categories
GET    /api/tenant/{tenant}/categories/{category}
PATCH  /api/tenant/{tenant}/categories/{category}
DELETE /api/tenant/{tenant}/categories/{category}
POST   /api/tenant/{tenant}/categories/{category}/image
DELETE /api/tenant/{tenant}/categories/{category}/image
```

Query list mendukung `search`, `parent_id`, `roots_only`, `is_active`, dan
`per_page`.

Contoh request create metadata:

```json
{
  "parent_id": null,
  "name": "Kamera",
  "description": "Peralatan kamera dan aksesorinya",
  "sort_order": 10,
  "is_active": true
}
```

`slug` dibuat otomatis jika tidak dikirim. Parent wajib berasal dari tenant
yang sama dan kategori tidak dapat menjadi parent bagi dirinya sendiri.
Gambar dikirim sebagai field file `image` menggunakan `multipart/form-data`,
baik saat create maupun melalui endpoint khusus image.

## Tenant Settings dan Private Media

```text
GET    /api/tenant/{tenant}/settings
PATCH  /api/tenant/{tenant}/settings
POST   /api/tenant/{tenant}/settings/images
DELETE /api/tenant/{tenant}/settings/images/{image}
GET    /api/tenant/{tenant}/media/{path}

POST   /api/tenant/{tenant}/products/{product}/images
PATCH  /api/tenant/{tenant}/products/{product}/images/{productImage}
DELETE /api/tenant/{tenant}/products/{product}/images/{productImage}
```

Logo tenant, logo cabang, gambar kategori, dan foto produk disimpan pada disk
private yang diisolasi per tenant oleh `FilesystemTenancyBootstrapper`.
Dokumentasi request, response, validasi, dan integrasi frontend tersedia di
[`16_TENANT_SETTINGS_PRIVATE_MEDIA_API.md`](16_TENANT_SETTINGS_PRIVATE_MEDIA_API.md).

Response `GET /settings` menyertakan objek `website_status`:

```json
{
  "website_status": {
    "is_enabled": true,
    "tenant_status": "active"
  }
}
```

`is_enabled` merefleksikan kolom `public_web_enabled` pada tenant (central).
Nilai ini menentukan apakah domain publik tenant (`{tenant}.sewantara.id`)
dapat diakses melalui Tenant Public API — lihat `ValidatePublicTenantEligibility`
pada dokumen arsitektur multi-tenancy.

## Status Website Publik Tenant

```http
PATCH /api/tenant/{tenant}/settings/website-status
```

```json
{
  "is_enabled": false
}
```

Mengaktifkan atau menonaktifkan website publik tenant tanpa mengubah
pengaturan lain. Berguna untuk menonaktifkan sementara website (mis. sedang
maintenance internal) tanpa harus mengulang proses onboarding.

Aturan:

- `is_enabled` wajib boolean.
- Mengirim `is_enabled: true` ditolak dengan validation error pada field
  `is_enabled` apabila tenant belum berstatus `active` (belum menyelesaikan
  Go Live).
- Menonaktifkan (`is_enabled: false`) selalu diizinkan selama tenant dapat
  diakses (`tenant.accessible`, `tenant.subscription`).
- Response mengikuti bentuk yang sama dengan `GET /settings`, termasuk objek
  `website_status` terbaru.

## Inventory Lifecycle

Stok `quantity` dikelola per produk dan branch:

```text
GET  /api/tenant/{tenant}/inventory/stocks
POST /api/tenant/{tenant}/inventory/stocks/adjust
POST /api/tenant/{tenant}/inventory/stocks/transfer
GET  /api/tenant/{tenant}/inventory/movements/stocks
GET  /api/tenant/{tenant}/inventory/movements/units
POST /api/tenant/{tenant}/product-units/{productUnit}/transfer
```

Contoh penyesuaian stok:

```json
{
  "product_id": 10,
  "quantity": 5,
  "reason_type": "initial_stock",
  "notes": "Stok awal gudang"
}
```

`quantity` selalu positif. `reason_type` menentukan penambahan, pengurangan,
rusak, hilang, recovery, disposal, atau write-off. Transfer quantity memakai
cabang dari `X-Branch-Id` sebagai sumber dan `target_branch_id` sebagai tujuan.
Detail lengkap tersedia di
[`17_INVENTORY_STOCK_TRANSFER_API.md`](17_INVENTORY_STOCK_TRANSFER_API.md).

Pembuatan booking otomatis:

- mengubah unit serialized menjadi `reserved`;
- menambah `quantity_reserved` untuk produk quantity;
- membuat histori pada `product_movements` atau
  `inventory_stock_movements`.

Lifecycle booking:

```text
POST /api/tenant/{tenant}/bookings/{booking}/check-out
POST /api/tenant/{tenant}/bookings/{booking}/return
POST /api/tenant/{tenant}/bookings/{booking}/cancel
```

Check-out memindahkan inventory dari reserved ke rented. Return mengembalikan
inventory ke available, sedangkan cancel melepas reservasi. Seluruh perubahan
direkam sebagai movement.

## Maintenance

```text
GET  /api/tenant/{tenant}/maintenance
POST /api/tenant/{tenant}/maintenance
GET  /api/tenant/{tenant}/maintenance/{maintenance}
POST /api/tenant/{tenant}/maintenance/{maintenance}/start
POST /api/tenant/{tenant}/maintenance/{maintenance}/complete
POST /api/tenant/{tenant}/maintenance/{maintenance}/cancel
```

Contoh penjadwalan:

```json
{
  "product_unit_id": 25,
  "type": "service",
  "title": "Service sensor kamera",
  "vendor": "Service Center",
  "cost": 250000,
  "scheduled_at": "2026-08-05T09:00:00+07:00"
}
```

Tipe yang tersedia adalah `service`, `repair`, `cleaning`, `inspection`, dan
`calibration`. Ketika maintenance dimulai, status unit menjadi `maintenance`
sehingga tidak dapat dibooking. Penyelesaian maintenance mengembalikan unit ke
`available`, `damaged`, atau `inactive` sesuai hasil pemeriksaan.

## Product Master

```text
GET    /api/tenant/{tenant}/products
POST   /api/tenant/{tenant}/products
GET    /api/tenant/{tenant}/products/{product}
PATCH  /api/tenant/{tenant}/products/{product}
DELETE /api/tenant/{tenant}/products/{product}
```

Contoh request create:

```json
{
  "name": "Sony Alpha A7 IV",
  "sku": "CAM-SONY-A7IV",
  "brand": "Sony",
  "model": "A7 IV",
  "engine_code": "rental",
  "product_type": "equipment",
  "inventory_type": "serialized",
  "default_pricing_type": "daily",
  "minimum_rental_duration": 1,
  "deposit_amount": 1000000,
  "late_fee_amount": 250000,
  "is_featured": true,
  "is_active": true
}
```

`engine_code` wajib dan menentukan engine pemilik produk (`rental`,
`booking`, `membership`, `sales`). `product_type` wajib dan harus termasuk
dalam daftar tipe yang diizinkan engine tersebut, lihat tabel pada
[Product Engine](#product-engine-rentalbookingmembershipsales).
