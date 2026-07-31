# Payment Gateway

## Arsitektur

Modul pembayaran menggunakan kontrak `PaymentGateway`. Application service
tidak bergantung langsung pada SDK Midtrans. Driver aktif dipilih oleh
`PaymentGatewayManager` berdasarkan `config/payments.php`.

Alur pembayaran booking:

1. API membuat payment berstatus `pending` dan audit request.
2. Driver membuat checkout pada gateway.
3. Frontend membuka `redirect_url` atau menggunakan token Snap.
4. Gateway mengirim webhook langsung ke endpoint tenant.
5. Backend memverifikasi tanda tangan dan nominal, lalu memperbarui payment dan booking.
6. Callback berulang diproses secara idempoten.

## Konfigurasi Midtrans

Kredensial bawaan aplikasi dibaca dari environment:

```dotenv
PAYMENT_GATEWAY=midtrans
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxx
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_IS_3DS=true
```

Untuk production, gunakan key production dan set
`MIDTRANS_IS_PRODUCTION=true`. Jangan mengirim atau menyimpan server key pada
frontend.

Setiap tenant dapat menggunakan akun Midtrans sendiri melalui tahap
konfigurasi pembayaran onboarding. Konfigurasi disimpan terenkripsi dan tidak
dikembalikan oleh endpoint snapshot:

```json
{
  "methods": [
    {
      "method": "midtrans",
      "is_enabled": true,
      "configuration": {
        "server_key": "SB-Mid-server-xxxxxxxx",
        "client_key": "SB-Mid-client-xxxxxxxx",
        "is_production": false,
        "is_3ds": true
      }
    }
  ]
}
```

Jika konfigurasi tenant tidak tersedia, aplikasi menggunakan kredensial dari
environment.

## Endpoint

Membuat checkout booking:

```http
POST /api/tenant/{tenant}/bookings/{booking}/payments/checkout
Authorization: Bearer {token}
X-Branch-Id: 1
Content-Type: application/json

{
  "type": "down_payment",
  "amount": 150000,
  "gateway": "midtrans"
}
```

Webhook dibuat otomatis sebagai notification URL checkout:

```http
POST /api/tenant/{tenant}/payments/webhooks/midtrans
```

Webhook tidak membutuhkan token dan header cabang karena dipanggil langsung
oleh Midtrans. Identitas tenant tetap diselesaikan dari path dan payload wajib
lulus verifikasi signature.

## Menambahkan Gateway Baru

1. Buat adapter yang mengimplementasikan `PaymentGateway`.
2. Normalisasikan hasil checkout menjadi `CheckoutSession`.
3. Verifikasi signature webhook dan normalisasikan status menjadi
   `paid`, `pending`, `failed`, atau `expired`.
4. Daftarkan driver di `config/payments.php`.
5. Tambahkan pengujian kontrak dan keamanan adapter.

Controller dan application service pembayaran tidak perlu diubah ketika driver
baru ditambahkan.
