# Inventory Stock Adjustment and Transfer API

Dokumen ini menjelaskan adjustment stok berdasarkan alasan, transfer antar
outlet/cabang, transfer unit serialized, serta informasi stok per cabang pada
detail produk.

## Header dan Branch Sumber

Seluruh endpoint memakai prefix:

```text
/api/tenant/{tenant}
```

Header wajib:

```http
Authorization: Bearer {access_token}
X-Branch-Id: {source_branch_id}
Accept: application/json
Content-Type: application/json
```

Pada adjustment dan transfer, `X-Branch-Id` selalu menjadi cabang aktif atau
cabang sumber. Client tidak boleh mengirim `branch_id` pada body.

## Model Stok

Produk `quantity` menyimpan stok per cabang dalam beberapa bucket:

| Field | Arti |
|---|---|
| `quantity_total` | Seluruh stok fisik yang tercatat |
| `quantity_available` | Stok yang dapat disewa atau dipindahkan |
| `quantity_reserved` | Stok untuk booking yang belum check-out |
| `quantity_rented` | Stok yang sedang disewa |
| `quantity_maintenance` | Stok yang sedang dipelihara |
| `quantity_damaged` | Stok rusak dan tidak tersedia |
| `quantity_lost` | Stok hilang dan tidak tersedia |

`quantity_available` dihitung oleh backend:

```text
quantity_total
- quantity_reserved
- quantity_rented
- quantity_maintenance
- quantity_damaged
- quantity_lost
```

## Adjustment Stok Quantity

```http
POST /api/tenant/{tenant}/inventory/stocks/adjust
```

Request:

```json
{
  "product_id": 25,
  "quantity": 2,
  "reason_type": "damaged",
  "notes": "Dua tripod patah saat pemeriksaan."
}
```

`quantity` selalu berupa integer positif minimal `1`. Arah dan efek perubahan
ditentukan oleh `reason_type`.

| `reason_type` | Efek |
|---|---|
| `initial_stock` | Menambah total dan available sebagai stok awal |
| `purchase` | Menambah total dan available dari pembelian |
| `correction_in` | Menambah total dan available hasil koreksi |
| `correction_out` | Mengurangi total dan available |
| `damaged` | Memindahkan available ke damaged; total tetap |
| `lost` | Memindahkan available ke lost; total tetap |
| `damaged_recovered` | Mengembalikan damaged ke available |
| `lost_recovered` | Mengembalikan lost ke available |
| `damaged_disposed` | Menghapus stok damaged dari total fisik |
| `lost_write_off` | Menghapus stok lost dari total fisik |
| `other_in` | Penambahan lain; `notes` wajib |
| `other_out` | Pengurangan lain; `notes` wajib |

Adjustment `damaged`, `lost`, `correction_out`, dan `other_out` ditolak jika
stok available tidak cukup. Recovery dan write-off ditolak jika jumlahnya
melebihi bucket sumber.

Response:

```json
{
  "success": true,
  "message": "Stok berhasil disesuaikan.",
  "data": {
    "id": 12,
    "product_id": 25,
    "branch_id": 1,
    "quantity_total": 10,
    "quantity_available": 8,
    "quantity_reserved": 0,
    "quantity_rented": 0,
    "quantity_maintenance": 0,
    "quantity_damaged": 2,
    "quantity_lost": 0
  }
}
```

Setiap adjustment membuat movement `adjustment_{reason_type}`, misalnya
`adjustment_damaged`, `adjustment_purchase`, dan
`adjustment_lost_write_off`.

## Transfer Stok Quantity Antar Outlet

```http
POST /api/tenant/{tenant}/inventory/stocks/transfer
```

Request:

```json
{
  "product_id": 25,
  "target_branch_id": 2,
  "quantity": 3,
  "notes": "Pemerataan stok akhir pekan."
}
```

Aturan:

- Cabang sumber berasal dari `X-Branch-Id`.
- `target_branch_id` wajib cabang aktif dari tenant yang sama.
- Cabang tujuan harus berbeda dari cabang sumber.
- Produk wajib bertipe `quantity`.
- Hanya `quantity_available` yang dapat dipindahkan.
- Transfer tidak mengubah total stok tenant; hanya distribusi antar cabang.
- Seluruh proses berjalan dalam satu transaksi database.

Response:

```json
{
  "success": true,
  "message": "Stok berhasil dipindahkan antar cabang.",
  "data": {
    "transfer": {
      "id": 9,
      "product_id": 25,
      "from_branch_id": 1,
      "to_branch_id": 2,
      "quantity": 3,
      "notes": "Pemerataan stok akhir pekan."
    },
    "source_stock": {
      "branch_id": 1,
      "quantity_total": 7,
      "quantity_available": 5
    },
    "target_stock": {
      "branch_id": 2,
      "quantity_total": 3,
      "quantity_available": 3
    }
  }
}
```

Backend membuat dua movement dengan `reference_id` transfer yang sama:

```text
transfer_out pada cabang sumber
transfer_in  pada cabang tujuan
```

## Transfer Unit Serialized

Produk serialized dipindahkan per unit:

```http
POST /api/tenant/{tenant}/product-units/{productUnit}/transfer
```

Request:

```json
{
  "target_branch_id": 2,
  "notes": "Unit dibutuhkan cabang kedua."
}
```

Aturan:

- Unit harus berada pada cabang dari `X-Branch-Id`.
- Unit hanya dapat dipindahkan saat status `available`.
- Unit `reserved`, `rented`, `maintenance`, `damaged`, `lost`, atau `inactive`
  tidak dapat dipindahkan.
- Movement disimpan sebagai `branch_transfer` dengan `from_branch_id` dan
  `to_branch_id`.

## Informasi Stok pada Detail Produk

```http
GET /api/tenant/{tenant}/products/{product}
```

Response detail produk sekarang memiliki:

```json
{
  "stock_summary": {
    "quantity_total": 13,
    "quantity_available": 9,
    "quantity_reserved": 1,
    "quantity_rented": 1,
    "quantity_maintenance": 0,
    "quantity_damaged": 2,
    "quantity_lost": 0,
    "quantity_inactive": 0
  },
  "stock_by_branch": [
    {
      "branch": {
        "id": 1,
        "name": "Cabang Utama",
        "code": "MAIN"
      },
      "is_current_branch": true,
      "quantity_total": 10,
      "quantity_available": 6,
      "quantity_reserved": 1,
      "quantity_rented": 1,
      "quantity_maintenance": 0,
      "quantity_damaged": 2,
      "quantity_lost": 0,
      "quantity_inactive": 0
    },
    {
      "branch": {
        "id": 2,
        "name": "Cabang Kedua",
        "code": "SECOND"
      },
      "is_current_branch": false,
      "quantity_total": 3,
      "quantity_available": 3,
      "quantity_reserved": 0,
      "quantity_rented": 0,
      "quantity_maintenance": 0,
      "quantity_damaged": 0,
      "quantity_lost": 0,
      "quantity_inactive": 0
    }
  ]
}
```

Untuk produk `quantity`, angka berasal dari `inventory_stocks`. Untuk produk
`serialized`, backend menghitung unit per `branch_id` dan `status`. Status
`cleaning` digabung ke `quantity_maintenance`.

Semua cabang aktif ditampilkan, termasuk cabang yang belum memiliki stok.

## Riwayat Movement

Riwayat stok quantity:

```http
GET /api/tenant/{tenant}/inventory/movements/stocks
```

Riwayat unit serialized:

```http
GET /api/tenant/{tenant}/inventory/movements/units
```

Endpoint movement mengikuti cabang pada `X-Branch-Id`. Transfer quantity muncul
pada source dan target masing-masing. Transfer serialized muncul jika cabang
aktif merupakan `from_branch_id` atau `to_branch_id`.

## Error Utama

| HTTP | Kondisi |
|---:|---|
| `201` | Transfer quantity berhasil |
| `200` | Adjustment atau transfer unit berhasil |
| `403` | User tidak memiliki akses ke tenant/cabang |
| `404` | Produk, unit, atau cabang tujuan tidak ditemukan |
| `422` | Reason tidak valid, stok tidak cukup, atau target sama dengan sumber |
| `423` | Tenant atau subscription tidak aktif |
