# Tenant Settings and Private Media API

Dokumen ini menjelaskan endpoint pengaturan tenant serta penyimpanan gambar
private untuk branding, cabang, kategori, dan produk.

## Base URL dan Header

Seluruh endpoint memakai prefix:

```text
/api/tenant/{tenant}
```

Header wajib:

```http
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
Accept: application/json
```

Gunakan `Content-Type: application/json` untuk pengaturan biasa. Jangan
menentukan `Content-Type` secara manual untuk upload file; HTTP client harus
membentuk boundary `multipart/form-data`.

## Penyimpanan Private per Tenant

File disimpan pada disk Laravel `local`. `FilesystemTenancyBootstrapper` dari
`stancl/tenancy` mengubah root disk secara otomatis setelah tenant pada path
berhasil diinisialisasi.

Struktur fisik:

```text
storage/{suffix_base}{tenant_id}/app/
|-- branding/
|-- branches/{branch_id}/
|-- categories/{category_id}/
`-- products/{product_id}/
```

Dengan konfigurasi saat ini, `suffix_base` bernilai `tenant`. Aplikasi tidak
menambahkan tenant ID ke `image_path` karena isolasi root dikerjakan oleh
Stancl.

File tidak berada di `storage/app/public`, tidak menggunakan
`public/storage`, dan tidak dapat diakses secara langsung dari web server.

## Tenant Settings

### Membaca Settings

```http
GET /api/tenant/{tenant}/settings
```

Response:

```json
{
  "success": true,
  "data": {
    "regular": {
      "business_name": "Rental Kamera Jember",
      "timezone": "Asia/Jakarta",
      "currency": "IDR",
      "default_language": "id"
    },
    "branding": {
      "primary_color": "#0F766E",
      "logo_path": "branding/01K4...png",
      "logo_url": "http://localhost/api/tenant/{tenant}/media/branding/01K4...png"
    },
    "branch": {
      "id": 1,
      "name": "Cabang Utama",
      "settings": {
        "logo_path": "branches/1/01K4...jpg",
        "logo_url": "http://localhost/api/tenant/{tenant}/media/branches/1/01K4...jpg"
      }
    },
    "rental_engine": {
      "rental_model": "per_day",
      "booking_strategy": "date_range",
      "allocation_strategy": "manual"
    }
  }
}
```

`*_path` adalah path internal pada disk private tenant. `*_url` adalah endpoint
API terautentikasi untuk membaca file.

### Memperbarui Settings Biasa

```http
PATCH /api/tenant/{tenant}/settings
Content-Type: application/json
```

```json
{
  "regular": {
    "business_name": "Sewantara Jember",
    "timezone": "Asia/Jakarta",
    "currency": "IDR",
    "default_language": "id",
    "date_format": "DD/MM/YYYY",
    "time_format": "HH:mm"
  },
  "branding": {
    "primary_color": "#0F766E",
    "secondary_color": "#F59E0B"
  },
  "branch": {
    "name": "Cabang Jember",
    "phone": "0331123456",
    "address": "Jalan Contoh 10",
    "is_active": true
  },
  "rental_engine": {
    "rental_model": "per_hour",
    "booking_strategy": "session",
    "allocation_strategy": "auto_assign",
    "slot_duration_minutes": 60,
    "enable_waiting_list": true
  }
}
```

Field URL gambar berikut tidak diperbolehkan:

```text
branding.logo_url
branding.favicon_url
branding.invoice_logo_url
branch.logo_url
```

Jika dikirim, API mengembalikan `422`. Semua gambar harus diunggah melalui
endpoint private image.

## Branding dan Logo Cabang

### Upload atau Ganti Gambar

```http
POST /api/tenant/{tenant}/settings/images
Content-Type: multipart/form-data
```

Minimal satu file wajib dikirim.

| Field | Format | Ukuran maksimum | Folder |
|---|---|---:|---|
| `logo` | JPG, JPEG, PNG, WEBP | 5 MB | `branding/` |
| `favicon` | PNG, ICO | 1 MB | `branding/` |
| `invoice_logo` | JPG, JPEG, PNG, WEBP | 5 MB | `branding/` |
| `branch_logo` | JPG, JPEG, PNG, WEBP | 5 MB | `branches/{branch_id}/` |

Contoh cURL:

```bash
curl --request POST \
  --url "http://localhost/api/tenant/{tenant}/settings/images" \
  --header "Authorization: Bearer {access_token}" \
  --header "X-Branch-Id: 1" \
  --header "Accept: application/json" \
  --form "logo=@C:/images/logo.png" \
  --form "branch_logo=@C:/images/branch-logo.webp"
```

Saat gambar diganti, backend menyimpan file baru dan menghapus file lama
setelah database berhasil diperbarui.

### Menghapus Gambar

```http
DELETE /api/tenant/{tenant}/settings/images/{image}
```

Nilai `{image}`:

```text
logo
favicon
invoice_logo
branch_logo
```

Contoh:

```http
DELETE /api/tenant/{tenant}/settings/images/logo
```

Penghapusan menghapus metadata database dan file fisik.

## Gambar Kategori

CRUD metadata kategori tidak lagi menerima `image_path` dari client.

### Membuat Kategori dengan Gambar

```http
POST /api/tenant/{tenant}/categories
Content-Type: multipart/form-data
```

Field metadata kategori tetap dapat dikirim sebagai form field. Field gambar:

| Field | Format | Ukuran maksimum |
|---|---|---:|
| `image` | JPG, JPEG, PNG, WEBP | 5 MB |

### Upload atau Ganti Gambar Kategori

```http
POST /api/tenant/{tenant}/categories/{category}/image
Content-Type: multipart/form-data
```

```bash
curl --request POST \
  --url "http://localhost/api/tenant/{tenant}/categories/10/image" \
  --header "Authorization: Bearer {access_token}" \
  --header "X-Branch-Id: 1" \
  --header "Accept: application/json" \
  --form "image=@C:/images/category-camera.jpg"
```

Response kategori memuat:

```json
{
  "image_path": "categories/10/01K4...jpg",
  "image_url": "http://localhost/api/tenant/{tenant}/media/categories/10/01K4...jpg"
}
```

### Menghapus Gambar Kategori

```http
DELETE /api/tenant/{tenant}/categories/{category}/image
```

## Foto Produk

Detail dan daftar produk memuat relation `images`. Urutan response adalah
primary image, `sort_order`, lalu ID.

### Menambahkan Foto

```http
POST /api/tenant/{tenant}/products/{product}/images
Content-Type: multipart/form-data
```

| Field | Tipe | Aturan |
|---|---|---|
| `image` | file | Wajib; JPG, JPEG, PNG, atau WEBP; maksimum 5 MB |
| `alt_text` | string | Opsional; maksimum 255 karakter |
| `is_primary` | boolean | Opsional |
| `sort_order` | integer | Opsional; minimum 0 |

Contoh:

```bash
curl --request POST \
  --url "http://localhost/api/tenant/{tenant}/products/25/images" \
  --header "Authorization: Bearer {access_token}" \
  --header "X-Branch-Id: 1" \
  --header "Accept: application/json" \
  --form "image=@C:/images/sony-a7-front.jpg" \
  --form "alt_text=Sony A7 IV tampak depan" \
  --form "is_primary=true" \
  --form "sort_order=0"
```

Response:

```json
{
  "success": true,
  "message": "Foto produk berhasil ditambahkan.",
  "data": {
    "id": 44,
    "tenant_id": "{tenant}",
    "product_id": 25,
    "image_path": "products/25/01K4...jpg",
    "alt_text": "Sony A7 IV tampak depan",
    "is_primary": true,
    "sort_order": 0,
    "image_url": "http://localhost/api/tenant/{tenant}/media/products/25/01K4...jpg"
  }
}
```

Foto pertama otomatis menjadi primary. Saat foto lain diberi
`is_primary=true`, foto primary sebelumnya otomatis dinonaktifkan.

### Memperbarui Metadata Foto

```http
PATCH /api/tenant/{tenant}/products/{product}/images/{productImage}
Content-Type: application/json
```

```json
{
  "alt_text": "Sony A7 IV sisi depan",
  "is_primary": true,
  "sort_order": 1
}
```

Endpoint ini hanya memperbarui metadata. Untuk mengganti file, upload foto baru
kemudian hapus foto lama.

### Menghapus Foto

```http
DELETE /api/tenant/{tenant}/products/{product}/images/{productImage}
```

File fisik ikut dihapus. Jika foto yang dihapus adalah primary, foto berikutnya
berdasarkan `sort_order` otomatis dijadikan primary.

## Membaca File Private

```http
GET /api/tenant/{tenant}/media/{path}
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
```

Gunakan nilai `*_url` atau `image_url` dari response API. Endpoint hanya
menerima path di dalam `branding/`, `branches/`, `categories/`, atau
`products/`.

Response menggunakan:

```http
Content-Disposition: inline
Cache-Control: private, max-age=3600
```

Karena endpoint membutuhkan Bearer token dan `X-Branch-Id`, URL tidak dapat
langsung dipasang pada elemen `<img src>` apabila client tidak dapat mengirim
header. Frontend web dapat mengambil file sebagai Blob:

```javascript
async function loadPrivateImage(imageUrl, token, branchId) {
  const response = await fetch(imageUrl, {
    headers: {
      Authorization: `Bearer ${token}`,
      "X-Branch-Id": String(branchId),
      Accept: "application/json",
    },
  });

  if (!response.ok) {
    throw new Error("Gambar tidak dapat dimuat");
  }

  return URL.createObjectURL(await response.blob());
}
```

Panggil `URL.revokeObjectURL()` saat preview tidak lagi dipakai.

## Status dan Error

| HTTP | Kondisi |
|---:|---|
| `200` | Update, delete, atau media stream berhasil |
| `201` | Foto produk berhasil ditambahkan |
| `401` | Bearer token tidak ada atau tidak valid |
| `403` | User tidak memiliki akses ke branch atau tenant |
| `404` | Resource, file, atau jenis gambar tidak ditemukan |
| `422` | Validasi file/form gagal atau URL eksternal dikirim |
| `423` | Tenant tidak dapat diakses karena status atau subscription |

Response validasi mengikuti format Laravel:

```json
{
  "message": "Minimal satu file gambar wajib dikirim.",
  "errors": {
    "image": [
      "Minimal satu file gambar wajib dikirim."
    ]
  }
}
```

## Catatan Integrasi

- Simpan `image_path` sebagai metadata saja; client sebaiknya memakai
  `image_url` untuk membaca file.
- Jangan membentuk URL `/storage/...` karena file tidak berada di public disk.
- Jangan mengirim URL CDN atau URL eksternal sebagai pengganti upload.
- Setiap request media tetap harus membawa tenant path dan branch aktif.
- Path tenant lain tidak dapat dibaca karena root disk sudah dipisahkan oleh
  `FilesystemTenancyBootstrapper`.
