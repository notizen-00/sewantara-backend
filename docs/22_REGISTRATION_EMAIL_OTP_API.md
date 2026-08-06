# PRD — Registration Email OTP Verification

**Project:** Sewantara Backend API
**Module:** `App\Modules\RegistrationVerification`
**Repository:** `sewantara-api`
**Version:** 1.0
**Status:** Implemented
**Backend:** Laravel 12
**Mail provider:** Resend
**Cache:** Redis / default cache store
**Primary consumer:** Halaman registrasi tenant (Nuxt)

---

# 1. Latar belakang

Sebelum fitur ini dibuat, `POST /api/central/auth/register` langsung membuat
akun owner dan memprovision tenant (schema database, trial subscription, dsb)
begitu form registrasi lolos validasi. Tidak ada pemeriksaan bahwa alamat
email owner benar-benar dapat diakses oleh pendaftar.

Akibatnya:

- Tenant dapat terprovisi untuk email yang salah ketik atau tidak valid,
  padahal provisioning tenant adalah proses berat (migrasi schema, trial
  subscription, dsb).
- Tidak ada mekanisme untuk memastikan pemilik email adalah orang yang
  benar-benar mendaftar, sebelum akun dan tenant dibuat.

Fitur ini menambahkan verifikasi email lewat kode OTP 6 digit yang dikirim
via email (Resend), **sebelum** endpoint registrasi tenant mau memproses
permintaan.

---

# 2. Goals

## 2.1 Primary goals

- Memastikan email owner terverifikasi sebelum tenant diprovisi.
- Mencegah registrasi dengan email yang tidak dapat diakses pendaftar.
- Mengirim kode OTP secara asynchronous (queue) tanpa memblokir response.
- Membatasi penyalahgunaan (spam OTP, brute force kode) dengan rate limit
  berlapis.
- Tidak mengubah kontrak endpoint `POST /api/central/auth/register` selain
  menambahkan syarat validasi baru pada `owner.email`.

## 2.2 Non-goals

- Verifikasi nomor telepon (di luar cakupan dokumen ini).
- Verifikasi email untuk login (fitur ini khusus alur registrasi).
- Penyimpanan riwayat OTP permanen di database — status OTP bersifat
  sementara dan disimpan di cache (auto-expire), bukan tabel.

---

# 3. Alur end-to-end

```text
Nuxt
  → POST /api/central/auth/otp/request       { email }
  → Backend generate kode 6 digit, simpan hash di cache, kirim email (Resend)
  → User membuka email, membaca kode
  → POST /api/central/auth/otp/verify         { email, code }
  → Backend menandai email "verified" (cache, TTL 30 menit)
  → POST /api/central/auth/register           { ..., owner: { email, ... } }
  → Backend memeriksa status "verified" milik email tsb, lalu memprovisi tenant
  → Status "verified" dihapus (sekali pakai)
```

Jika `owner.email` pada `POST /auth/register` belum pernah lolos
`/auth/otp/verify`, request registrasi ditolak dengan pesan validasi pada
field `owner.email`.

---

# 4. Endpoint

## 4.1 Minta kode OTP

```http
POST /api/central/auth/otp/request
Content-Type: application/json

{
  "email": "owner@bisnis.com"
}
```

Validasi:

- `email` wajib, format email valid (`email:rfc,dns`), maksimal 150 karakter.
- Email **tidak boleh** sudah terdaftar sebagai `CentralUser` (mencegah
  spam OTP ke akun yang sudah punya tenant).

Response berhasil (`200`):

```json
{
  "success": true,
  "message": "Kode verifikasi telah dikirim ke email Anda.",
  "data": null,
  "meta": null
}
```

Response gagal — email sudah terdaftar / format tidak valid (`422`):
mengikuti format error validasi Laravel standar (`errors.email`).

Response gagal — diminta ulang terlalu cepat (`429`):

```json
{
  "success": false,
  "error": {
    "code": "OTP_REQUEST_THROTTLED",
    "message": "Mohon tunggu sebentar sebelum meminta kode verifikasi baru.",
    "details": null
  }
}
```

## 4.2 Verifikasi kode OTP

```http
POST /api/central/auth/otp/verify
Content-Type: application/json

{
  "email": "owner@bisnis.com",
  "code": "482913"
}
```

Validasi:

- `email` wajib, format email valid.
- `code` wajib, tepat 6 digit angka.

Response berhasil (`200`):

```json
{
  "success": true,
  "message": "Email berhasil diverifikasi.",
  "data": null,
  "meta": null
}
```

Response gagal — kode salah, kedaluwarsa, atau percobaan sudah melebihi
batas (`422`):

```json
{
  "success": false,
  "error": {
    "code": "OTP_CODE_INVALID",
    "message": "Kode verifikasi salah atau sudah kedaluwarsa.",
    "details": null
  }
}
```

Endpoint ini **tidak** membedakan pesan antara "kode salah", "kode
kedaluwarsa", dan "belum pernah minta OTP" — semua direspons sebagai
`OTP_CODE_INVALID` agar tidak membocorkan informasi kepada pihak yang
mencoba menebak kode.

## 4.3 Registrasi tenant (kontrak yang berubah)

```http
POST /api/central/auth/register
Content-Type: application/json

{
  "business_name": "...",
  "business_type": "...",
  "subdomain": "...",
  "owner": {
    "name": "...",
    "email": "owner@bisnis.com",
    "phone": "...",
    "password": "...",
    "password_confirmation": "..."
  },
  "plan_id": 1,
  "billing_interval": "month",
  "terms_accepted": true
}
```

Aturan baru pada field `owner.email`: request ditolak (`422`) dengan pesan
berikut pada `errors.owner.email` apabila email belum melewati
`/auth/otp/verify`:

```text
Email belum diverifikasi. Silakan minta dan masukkan kode OTP terlebih dahulu.
```

Tidak ada field baru yang perlu dikirim frontend pada payload registrasi —
backend memeriksa status verifikasi berdasarkan email yang sama, bukan
lewat token terpisah.

---

# 5. Business rules

| ID | Aturan |
|----|--------|
| OTP-001 | Kode OTP terdiri dari 6 digit angka acak. |
| OTP-002 | Kode disimpan sebagai SHA-256 hash di cache, bukan plaintext. |
| OTP-003 | Kode berlaku `REGISTRATION_OTP_TTL_MINUTES` menit (default 5). |
| OTP-004 | Permintaan OTP baru untuk email yang sama dibatasi cooldown `REGISTRATION_OTP_RESEND_SECONDS` detik (default 60). |
| OTP-005 | Percobaan verifikasi salah dibatasi `REGISTRATION_OTP_MAX_ATTEMPTS` kali (default 5); setelah itu kode dianggap hangus dan harus diminta ulang. |
| OTP-006 | Status "verified" berlaku `REGISTRATION_OTP_VERIFIED_TTL_MINUTES` menit (default 30) sejak verifikasi berhasil. |
| OTP-007 | Status "verified" hanya berlaku satu kali — dihapus otomatis setelah registrasi tenant berhasil. |
| OTP-008 | OTP tidak dikirim untuk email yang sudah terdaftar sebagai `CentralUser`. |
| OTP-009 | Endpoint request dan verify memiliki rate limit per-IP terpisah dari cooldown per-email. |
| OTP-010 | Pesan error verifikasi tidak membedakan alasan gagal (kode salah/kedaluwarsa/tidak ada) untuk mencegah enumerasi. |

---

# 6. Rate limiting

| Endpoint | Limit per-IP | Limit per-email |
|---|---|---|
| `POST /auth/otp/request` | 5 request/menit (`throttle:5,1`) | 1 request per `REGISTRATION_OTP_RESEND_SECONDS` detik |
| `POST /auth/otp/verify` | 10 request/menit (`throttle:10,1`) | 5 percobaan per kode aktif (`REGISTRATION_OTP_MAX_ATTEMPTS`) |
| `POST /auth/register` | 5 request/menit (`throttle:5,1`) — tidak berubah | — |

---

# 7. Konfigurasi backend

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@sewantara.id
MAIL_FROM_NAME=Sewantara

RESEND_API_KEY=your-resend-api-key

REGISTRATION_OTP_TTL_MINUTES=5
REGISTRATION_OTP_RESEND_SECONDS=60
REGISTRATION_OTP_MAX_ATTEMPTS=5
REGISTRATION_OTP_VERIFIED_TTL_MINUTES=30
```

`RESEND_API_KEY` dibaca lewat `config('services.resend.key')` (konvensi
mailer `resend` bawaan Laravel). Domain pengirim (`sewantara.id`) harus
sudah diverifikasi di dashboard Resend, termasuk record DNS (SPF/DKIM) yang
diarahkan lewat Cloudflare Email Routing/DNS.

Setelah mengubah environment:

```bash
php artisan optimize:clear
php artisan config:cache
```

Karena `RegistrationOtpMail` bersifat `ShouldQueue`, worker queue
(`QUEUE_CONNECTION`) harus berjalan agar email benar-benar terkirim:

```bash
php artisan queue:work
```

---

# 8. Contoh integrasi Nuxt

```vue
<script setup lang="ts">
const config = useRuntimeConfig()

const email = ref('')
const code = ref('')
const step = ref<'request' | 'verify'>('request')
const errorMessage = ref<string | null>(null)

async function requestOtp() {
  errorMessage.value = null

  try {
    await $fetch(`${config.public.apiBase}/api/central/auth/otp/request`, {
      method: 'POST',
      body: { email: email.value },
    })

    step.value = 'verify'
  } catch (error) {
    errorMessage.value = 'Gagal mengirim kode verifikasi. Coba lagi beberapa saat.'
  }
}

async function verifyOtp() {
  errorMessage.value = null

  try {
    await $fetch(`${config.public.apiBase}/api/central/auth/otp/verify`, {
      method: 'POST',
      body: { email: email.value, code: code.value },
    })

    // lanjutkan ke form registrasi tenant (business_name, subdomain, dst)
  } catch (error) {
    errorMessage.value = 'Kode verifikasi salah atau sudah kedaluwarsa.'
  }
}
</script>
```

Field `owner.email` pada form registrasi tenant harus memakai nilai email
yang sama persis dengan yang berhasil diverifikasi — status "verified"
dicocokkan berdasarkan alamat email (case-insensitive, di-trim), bukan token
terpisah.

---

# 9. Checklist deployment

- Domain `sewantara.id` sudah diverifikasi di Resend (SPF/DKIM via Cloudflare).
- `RESEND_API_KEY` terisi di environment production.
- `MAIL_MAILER=resend` dan `MAIL_FROM_ADDRESS=noreply@sewantara.id`.
- Queue worker berjalan (`php artisan queue:work` atau supervisor setara)
  agar `RegistrationOtpMail` terkirim.
- Config cache production dibuat ulang setelah environment diperbarui.
- Frontend memanggil `/auth/otp/request` dan `/auth/otp/verify` sebelum
  `/auth/register`, menggunakan email yang identik pada ketiga request.
- Rate limit `throttle:5,1` / `throttle:10,1` tidak diblokir oleh reverse
  proxy atau WAF sebelum mencapai Laravel.
