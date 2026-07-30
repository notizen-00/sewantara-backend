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
Accept: application/json
```

Endpoint operasional yang bergantung pada cabang menerima header:

```http
X-Branch-Id: 2
```

Jika header tidak dikirim, sistem memakai cabang utama pengguna. Header hanya
dapat menunjuk cabang aktif yang terhubung dengan pengguna. ID cabang aktif
selalu dikembalikan melalui response header `X-Branch-Id`.

Logout dan revoke token aktif:

```http
POST /api/tenant/{tenant}/auth/logout
```

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

## Product Category Master

ID category, parent, product, branch, customer, dan product unit menggunakan
integer auto-increment, bukan UUID.

```text
GET    /api/tenant/{tenant}/categories
POST   /api/tenant/{tenant}/categories
GET    /api/tenant/{tenant}/categories/{category}
PATCH  /api/tenant/{tenant}/categories/{category}
DELETE /api/tenant/{tenant}/categories/{category}
```

Query list mendukung `search`, `parent_id`, `roots_only`, `is_active`, dan
`per_page`.

Contoh request create:

```json
{
  "parent_id": null,
  "name": "Kamera",
  "description": "Peralatan kamera dan aksesorinya",
  "image_path": "categories/camera.jpg",
  "sort_order": 10,
  "is_active": true
}
```

`slug` dibuat otomatis jika tidak dikirim. Parent wajib berasal dari tenant
yang sama dan kategori tidak dapat menjadi parent bagi dirinya sendiri.

## Inventory Lifecycle

Stok `quantity` dikelola per produk dan branch:

```text
GET  /api/tenant/{tenant}/inventory/stocks
POST /api/tenant/{tenant}/inventory/stocks/adjust
GET  /api/tenant/{tenant}/inventory/movements/stocks
GET  /api/tenant/{tenant}/inventory/movements/units
```

Contoh penyesuaian stok:

```json
{
  "product_id": 10,
  "quantity": 5,
  "notes": "Stok awal gudang"
}
```

Nilai `quantity` positif menambah stok dan nilai negatif mengurangi stok.
Total tidak dapat dikurangi melewati jumlah yang sedang reserved, rented,
maintenance, damaged, atau lost.

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
  "inventory_type": "serialized",
  "default_pricing_type": "daily",
  "minimum_rental_duration": 1,
  "deposit_amount": 1000000,
  "late_fee_amount": 250000,
  "is_featured": true,
  "is_active": true
}
```
