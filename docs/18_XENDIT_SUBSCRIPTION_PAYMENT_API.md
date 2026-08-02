# API Pembayaran Subscription Xendit untuk Frontend

Dokumen ini menjelaskan kontrak API subscription Sewantara yang memakai Xendit,
alur yang dapat dipakai frontend, dan batas implementasi backend saat ini.

> **Status implementasi:** endpoint checkout, pembacaan status pembayaran,
> integrasi Xendit Payment Session, dan webhook pembayaran sudah tersedia.

## Base URL dan Header

```text
Local:      http://sewantara-backend.test
API prefix: /api
```

Header JSON:

```http
Accept: application/json
Content-Type: application/json
```

Endpoint tenant yang terautentikasi juga membutuhkan:

```http
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
```

Jangan menyimpan atau mengirim `XENDIT_SECRET_KEY` dan
`XENDIT_WEBHOOK_TOKEN` ke frontend. Seluruh komunikasi langsung dengan API
Xendit dilakukan oleh backend.

URL tujuan setelah hosted checkout dapat dikonfigurasi oleh backend:

```dotenv
SUBSCRIPTION_PAYMENT_SUCCESS_URL=https://dashboard.example.com/billing/success
SUBSCRIPTION_PAYMENT_CANCEL_URL=https://dashboard.example.com/billing/cancel
```

Xendit mensyaratkan URL tersebut memakai HTTPS. Jika nilainya dikosongkan,
Xendit akan memakai halaman hasil bawaannya.

## Ringkasan Ketersediaan API

| Kebutuhan | Method dan endpoint | Status |
|---|---|---|
| Mengambil paket subscription | `GET /api/central/plans` | Tersedia |
| Registrasi dan memulai trial | `POST /api/central/auth/register` | Tersedia |
| Membaca subscription aktif | `GET /api/tenant/{tenant}/me` | Tersedia |
| Membuat Xendit Payment Link | `POST /api/tenant/{tenant}/subscription/payments/checkout` | Tersedia |
| Membaca status transaksi subscription | `GET /api/tenant/{tenant}/subscription/payments/{payment}` | Tersedia |
| Menerima callback Xendit | `POST /api/central/billing/xendit/webhook` | Tersedia, khusus Xendit |

## Alur Frontend

Alur lengkap yang dituju:

```text
Pilih plan
  -> registrasi / pilih perpanjangan
  -> backend membuat tagihan dan Xendit Payment Session
  -> frontend menerima redirect_url
  -> browser diarahkan ke Xendit Payment Link
  -> pengguna menyelesaikan pembayaran
  -> Xendit mengirim webhook ke backend
  -> backend memvalidasi token dan nominal
  -> frontend membaca ulang status pembayaran dan subscription
```

Frontend tidak boleh menganggap redirect kembali dari halaman Xendit sebagai
bukti pembayaran berhasil. Status final harus berasal dari backend setelah
webhook Xendit diterima dan diverifikasi.

## 1. Mengambil Daftar Paket

```http
GET /api/central/plans?billing_interval=month&currency=IDR
```

Query bersifat opsional:

| Query | Nilai | Keterangan |
|---|---|---|
| `billing_interval` | `month` atau `year` | Menyaring periode tagihan |
| `currency` | `IDR` | Pencocokan tidak sensitif kapital |

Response `200`:

```json
{
  "success": true,
  "message": "Daftar paket berhasil diambil.",
  "data": [
    {
      "id": 1,
      "name": "Pemula",
      "slug": "starter",
      "description": "Untuk usaha penyewaan yang baru memulai.",
      "price": "199000.00",
      "signup_fee": "0.00",
      "currency": "IDR",
      "invoice_period": 1,
      "invoice_interval": "month",
      "trial_period": 14,
      "trial_interval": "day",
      "features": [
        {
          "slug": "branches.limit",
          "value": "1"
        }
      ]
    }
  ],
  "meta": null
}
```

Catatan frontend:

- `price` dan `signup_fee` dikirim sebagai string desimal. Jangan memakai
  parsing floating point untuk kalkulasi finansial.
- Nominal yang ditampilkan harus berasal dari response backend.
- Gunakan `id` sebagai `plan_id`, bukan `slug`.

## 2. Registrasi dan Memulai Trial

```http
POST /api/central/auth/register
```

Request ringkas:

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

Response `201` memuat subscription trial:

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
      "status": "trial",
      "trial_ends_at": "2026-08-16T10:00:00+07:00"
    }
  },
  "meta": null
}
```

Registrasi tidak menghasilkan Xendit Payment Link. Tenant langsung memperoleh
trial sesuai konfigurasi plan.

## 3. Membaca Status Subscription Tenant

```http
GET /api/tenant/{tenant}/me
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
```

Bagian `subscription` pada response:

```json
{
  "id": 10,
  "name": "main",
  "slug": "main",
  "status": "trial",
  "is_active": true,
  "is_on_trial": true,
  "is_canceled": false,
  "trial_ends_at": "2026-08-16T03:00:00+00:00",
  "starts_at": "2026-08-02T03:00:00+00:00",
  "ends_at": null,
  "canceled_at": null,
  "plan": {
    "id": 1,
    "name": "Pemula",
    "slug": "starter",
    "description": "Untuk usaha penyewaan yang baru memulai.",
    "price": "199000.00",
    "signup_fee": "0.00",
    "currency": "IDR",
    "invoice_period": 1,
    "invoice_interval": "month",
    "features": [
      {
        "slug": "branches.limit",
        "name": "Batas Cabang",
        "value": "1"
      }
    ]
  }
}
```

Nilai `status` yang dapat dikembalikan:

| Status | Arti untuk UI |
|---|---|
| `trial` | Trial masih berlaku |
| `active` | Subscription berbayar aktif |
| `canceled` | Subscription telah dibatalkan |
| `expired` | Masa subscription telah berakhir |
| `null` | Tenant tidak memiliki subscription `main` |

Gunakan `is_active` sebagai sumber utama untuk mengaktifkan fitur. `status`
dapat digunakan sebagai label visual.

## 4. Membuat Checkout Xendit

Endpoint ini dapat dipakai tenant dengan subscription `trial`, `active`, maupun
`expired`, selama akun tenant masih dapat diakses.

```http
POST /api/tenant/{tenant}/subscription/payments/checkout
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
Accept: application/json
```

Request tidak memiliki body. Backend mengambil subscription `main`, plan, harga,
mata uang, dan data pengguna dari sesi yang terautentikasi. Endpoint dibatasi
`10 request / menit`.

Response `201`:

```json
{
  "success": true,
  "message": "Checkout pembayaran langganan berhasil dibuat.",
  "data": {
    "payment": {
      "id": "019fbfec-3cb0-72ca-becb-679d703c1148",
      "payment_number": "SUB-01K1M8M6MZP0DYJ2E6ZP7WB9Z1",
      "gateway": "xendit",
      "gateway_reference": "ps-661f87c614802d6c402cd82d",
      "status": "pending",
      "amount": "199000.00",
      "currency": "IDR",
      "paid_at": null,
      "created_at": "2026-08-02T07:42:38+00:00",
      "updated_at": "2026-08-02T07:42:38+00:00"
    },
    "checkout": {
      "gateway": "xendit",
      "token": "ps-661f87c614802d6c402cd82d",
      "redirect_url": "https://dev.xen.to/subscription-test"
    }
  },
  "meta": null
}
```

Setelah menerima response, simpan `payment.id` untuk polling lalu arahkan
browser ke `checkout.redirect_url`. Setiap pemanggilan endpoint membuat transaksi
baru; cegah double-click pada tombol bayar di frontend.

Jika return URL dikonfigurasi, halaman success/cancel frontend sebaiknya membawa
`payment.id` melalui state aplikasi atau storage sementara agar polling dapat
dilanjutkan setelah pengguna kembali dari Xendit.

Backend akan menandai transaksi sebagai `failed` jika Xendit gagal membuat
Payment Session. Nominal tidak diterima dari frontend sehingga tidak dapat
dimanipulasi melalui request checkout.

Kemungkinan error:

| HTTP | Penyebab |
|---|---|
| `401` | Bearer token tidak ada atau tidak valid |
| `403` | Pengguna bukan anggota tenant atau tidak memiliki akses cabang |
| `404` | Tenant tidak ditemukan |
| `422` | Header cabang tidak valid, subscription tidak ada, plan tidak aktif, atau plan gratis |
| `423` | Tenant suspended atau tidak dapat diakses |
| `429` | Melebihi batas checkout |
| `500` | Konfigurasi Xendit tidak tersedia atau Xendit gagal membuat session |

## 5. Membaca Status Pembayaran

```http
GET /api/tenant/{tenant}/subscription/payments/{payment}
Authorization: Bearer {access_token}
X-Branch-Id: {branch_id}
Accept: application/json
```

`{payment}` adalah UUID dari `data.payment.id` pada response checkout. Backend
memastikan pembayaran dimiliki tenant pada URL; pembayaran tenant lain akan
dikembalikan sebagai `404`.

Response `200`:

```json
{
  "success": true,
  "message": "Status pembayaran langganan berhasil diambil.",
  "data": {
    "payment": {
      "id": "019fbfec-3cb0-72ca-becb-679d703c1148",
      "payment_number": "SUB-01K1M8M6MZP0DYJ2E6ZP7WB9Z1",
      "gateway": "xendit",
      "gateway_reference": "py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c",
      "status": "paid",
      "amount": "199000.00",
      "currency": "IDR",
      "paid_at": "2026-08-02T07:45:12+00:00",
      "created_at": "2026-08-02T07:42:38+00:00",
      "updated_at": "2026-08-02T07:45:12+00:00"
    }
  },
  "meta": null
}
```

Status transaksi saat ini:

| Status | Arti untuk frontend |
|---|---|
| `pending` | Menunggu pembayaran atau webhook Xendit |
| `paid` | Pembayaran telah diverifikasi backend |
| `failed` | Backend gagal membuat Payment Session |

Polling disarankan setiap 2-3 detik dan dihentikan setelah menerima status
final atau mencapai batas waktu halaman. `paid` adalah satu-satunya status
sukses.

## 6. Endpoint Webhook Xendit

```http
POST /api/central/billing/xendit/webhook
X-Callback-Token: {xendit_webhook_token}
```

Endpoint ini hanya untuk server Xendit. Frontend tidak boleh memanggil,
meniru, atau mengetahui callback token-nya.

Event pembayaran final yang diproses backend adalah
`payment_session.completed`. Contoh payload dari Xendit:

```json
{
  "event": "payment_session.completed",
  "data": {
    "reference_id": "SUB-INV-XENDIT-1",
    "payment_session_id": "ps-661f87c614802d6c402cd82d",
    "payment_id": "py-ac1fcd3e-21c5-4c70-bb06-fa3c34e19e0c",
    "amount": 199000
  }
}
```

Backend akan:

1. membandingkan `X-Callback-Token` secara aman;
2. mencari pembayaran berdasarkan `data.reference_id`;
3. memastikan `data.amount` sama dengan nominal tagihan;
4. mengubah pembayaran menjadi `paid` secara idempoten;
5. menyimpan `payment_id` sebagai referensi gateway; dan
6. menerbitkan event internal `SubscriptionPaymentPaid`.

Response webhook `200`:

```json
{
  "success": true,
  "message": "Pembayaran langganan berhasil dikonfirmasi."
}
```

Kemungkinan error webhook:

| HTTP | Penyebab |
|---|---|
| `403` | Callback token tidak valid |
| `404` | `reference_id` tidak cocok dengan pembayaran |
| `422` | Struktur payload tidak valid atau data pembayaran tidak lengkap |
| `500` | Nominal notifikasi tidak cocok atau terjadi kegagalan internal |

## 7. Error Subscription pada Endpoint Tenant

Jika subscription tidak ada:

```http
HTTP/1.1 403 Forbidden
```

```json
{
  "success": false,
  "error": {
    "code": "SUBSCRIPTION_REQUIRED",
    "message": "Langganan utama belum tersedia.",
    "details": null
  }
}
```

Jika subscription kedaluwarsa:

```http
HTTP/1.1 423 Locked
```

```json
{
  "success": false,
  "error": {
    "code": "SUBSCRIPTION_EXPIRED",
    "message": "Masa langganan telah berakhir.",
    "details": null
  }
}
```

Frontend sebaiknya menangani kode error tersebut secara global dan mengarahkan
pengguna ke halaman billing. Endpoint checkout tetap berada di luar middleware
subscription aktif, sehingga masih dapat dipakai untuk memulihkan subscription
yang kedaluwarsa.

## Checklist Integrasi Frontend

- Ambil harga dan fitur dari `GET /api/central/plans`.
- Tampilkan tanggal dalam timezone tenant; timestamp API memakai ISO 8601.
- Jangan hitung ulang atau mengirim nominal final dari state frontend.
- Jangan expose key atau callback token Xendit.
- Buka Payment Link dari `redirect_url` yang diterbitkan backend.
- Jangan menandai transaksi paid berdasarkan redirect browser.
- Setelah kembali dari Xendit, polling backend sampai status final dengan batas
  waktu yang wajar.
- Tangani `SUBSCRIPTION_REQUIRED` dan `SUBSCRIPTION_EXPIRED` secara global.
- Cegah double-click tombol checkout karena setiap request membuat pembayaran
  baru.
