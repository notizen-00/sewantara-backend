# API Pembayaran Subscription DOKU

Integrasi ini memakai **DOKU Checkout Non-SNAP** di backend. Frontend tetap
memakai endpoint subscription yang sama dan hanya perlu membuka URL checkout
yang dikembalikan API.

## Konfigurasi backend

```dotenv
SUBSCRIPTION_PAYMENT_GATEWAY=doku
DOKU_CLIENT_ID=BRN-xxxx
DOKU_SECRET_KEY=doku_key_sandbox_xxxx
DOKU_PUBLIC_KEY=
DOKU_BASE_URL=https://api-sandbox.doku.com
DOKU_PAYMENT_DUE_MINUTES=60
DOKU_NOTIFICATION_URL=https://api.example.com/api/central/billing/doku/webhook
SUBSCRIPTION_PAYMENT_SUCCESS_URL=https://app.example.com/billing/success
SUBSCRIPTION_PAYMENT_CANCEL_URL=https://app.example.com/billing/cancel
```

`DOKU_SECRET_KEY` wajib disimpan hanya di environment backend. Public key RSA
tidak digunakan oleh DOKU Checkout Non-SNAP; kolom konfigurasi disediakan untuk
integrasi SNAP yang mungkin ditambahkan kemudian.

Sandbox memakai `https://api-sandbox.doku.com`. Saat go-live, ganti credential
dan base URL menjadi `https://api.doku.com`.

Setelah mengubah environment Laravel, jalankan:

```bash
php artisan optimize:clear
php artisan config:cache
```

## Membuat checkout

```http
POST /api/tenant/{tenant_id}/subscription/payments/checkout
Authorization: Bearer {access_token}
Accept: application/json
```

Contoh respons:

```json
{
  "success": true,
  "message": "Checkout pembayaran langganan berhasil dibuat.",
  "data": {
    "payment": {
      "payment_number": "SUB-01J...",
      "gateway": "doku",
      "status": "pending",
      "amount": "499000.00",
      "currency": "IDR"
    },
    "checkout": {
      "gateway": "doku",
      "token": "...",
      "redirect_url": "https://checkout.doku.com/..."
    }
  },
  "meta": null
}
```

Frontend mengarahkan browser ke `data.checkout.redirect_url`. Redirect kembali
dari DOKU bukan bukti pembayaran; frontend harus membaca status pembayaran dari
backend:

```http
GET /api/tenant/{tenant_id}/subscription/payments/{payment_number}
Authorization: Bearer {access_token}
```

Status final pembayaran berhasil adalah `paid`. Setelah webhook `SUCCESS`
terverifikasi, backend juga mengubah subscription tenant menjadi `active`.

## Webhook DOKU

```http
POST /api/central/billing/doku/webhook
```

Daftarkan URL publik HTTPS tersebut di dashboard DOKU atau isi
`DOKU_NOTIFICATION_URL` agar checkout melakukan override notification URL.
Endpoint memverifikasi `Client-Id`, `Request-Id`, `Request-Timestamp`, dan
`Signature` HMAC-SHA256 terhadap raw request body.

Notifikasi dengan `transaction.status=SUCCESS` akan menandai payment sebagai
`paid` dan mengaktifkan subscription. Status selain `SUCCESS` diakui dengan HTTP
200 tetapi tidak mengubah subscription, supaya percobaan pembayaran berikutnya
tetap dapat dilakukan.

Referensi resmi:

- [DOKU Checkout backend integration](https://developers.doku.com/accept-payments/doku-checkout/integration-guide/backend-integration)
- [DOKU Non-SNAP signature](https://developers.doku.com/get-started-with-doku-api/signature-component/non-snap/signature-component-from-request-header)
- [DOKU notification best practice](https://developers.doku.com/get-started-with-doku-api/notification/best-practice)
