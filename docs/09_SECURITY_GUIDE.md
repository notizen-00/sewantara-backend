# Security Guide

## Sewantara — Universal Rental Management SaaS

**Versi:** 1.0  
**Status:** Draft  
**Framework:** Laravel 12  
**Tenant Management:** `stancl/tenancy`  
**Subscription Management:** `laravelcm/laravel-subscriptions`

---

# 1. Tujuan

Dokumen ini menjadi standar keamanan aplikasi Sewantara.

Seluruh fitur wajib mengikuti standar keamanan agar:

- Data tenant tetap terisolasi.
- Akses pengguna sesuai haknya.
- API aman digunakan.
- Data sensitif terlindungi.
- Aktivitas dapat diaudit.
- Risiko kebocoran data diminimalkan.

Security Guide berlaku untuk:

- Backend
- Frontend
- Flutter Mobile
- DevOps
- QA

---

# 2. Security Principle

Sewantara mengikuti prinsip:

- Zero Trust
- Least Privilege
- Defense in Depth
- Secure by Default
- Fail Secure
- Principle of Least Knowledge

---

# 3. Authentication

Authentication menggunakan **Laravel Sanctum** dengan Bearer Token.

```http
Authorization: Bearer {access_token}
```

Tidak menggunakan Session Authentication.

---

## Login Flow

```text
User Login
      ↓
Validate Credential
      ↓
Validate Tenant
      ↓
Validate Subscription
      ↓
Generate Sanctum Token
      ↓
Return User Profile
```

---

## Token Policy

Access Token:

- Personal Access Token
- SHA256 Hashed
- Revocable
- Device Based

Token wajib memiliki:

- User
- Tenant
- Device Name
- Last Used
- Created At

---

## Logout

Logout harus:

- Menghapus token aktif.
- Menghapus refresh token (jika ada).
- Menghapus session device.

---

## Password Policy

Minimal:

- 8 karakter
- Huruf besar
- Huruf kecil
- Angka
- Symbol

Contoh:

```text
Rental@2026
```

---

## Password Hash

Menggunakan:

```php
Hash::make()
```

Laravel akan menggunakan Argon2id atau Bcrypt sesuai konfigurasi.

Password tidak boleh:

- Disimpan plain text.
- Dikirim kembali ke client.
- Dicatat di log.

---

## MFA (Future)

Roadmap:

- Email OTP
- TOTP
- Authenticator App
- Passkey (WebAuthn)

---

# 4. Authorization

Authorization menggunakan kombinasi:

- Role
- Permission
- Policy
- Gate

---

## Role

Contoh:

```text
Owner
Administrator
Manager
Cashier
Warehouse
Staff
Viewer
```

---

## Permission

Format:

```text
module.action
```

Contoh:

```text
booking.view
booking.create
booking.update
booking.cancel

product.view
product.create

customer.view

report.export
```

---

## Middleware

```php
auth:sanctum
```

↓

```php
EnsureUserBelongsToTenant
```

↓

```php
EnsureTenantSubscriptionActive
```

↓

```php
permission:booking.create
```

---

## Policy

Semua resource penting wajib menggunakan Laravel Policy.

Contoh:

- BookingPolicy
- ProductPolicy
- CustomerPolicy
- PaymentPolicy
- ReportPolicy

Tidak diperbolehkan melakukan authorization langsung di Controller.

---

# 5. Tenant Isolation

Package:

```text
stancl/tenancy
```

Tenant adalah boundary utama keamanan.

Semua data harus terisolasi.

---

## Tenant Identification

Landing page:

```text
InitializeTenancyByDomainOrSubdomain
```

Mobile API:

```text
InitializeTenancyByPath
```

Internal API:

```text
InitializeTenancyByRequestData
```

Queue:

```text
tenancy()->initialize()
```

---

## Tenant Validation

Tenant resolution **bukan authorization**.

Backend tetap wajib memastikan:

```text
Authenticated User
↓

Tenant Context
↓

User Tenant
↓

Permission
↓

Business Logic
```

---

## Cross Tenant Protection

User Tenant A

Tidak boleh mengakses

Tenant B.

Contoh:

```text
GET

/api/v1/{tenant-b}/bookings
```

Response:

```json
{
    "success": false,
    "error": {
        "code": "TENANT_ACCESS_DENIED",
        "message": "Tenant access denied."
    }
}
```

HTTP:

```text
403 Forbidden
```

---

## Route Model Binding

Tidak boleh:

```php
Product::find($id);
```

Harus:

```php
Product::query()
    ->where('tenant_id', tenant('id'))
    ->findOrFail($id);
```

Atau menggunakan Tenant Global Scope.

---

## Queue Isolation

Queue wajib membawa:

```text
tenant_id
```

Contoh:

```php
$tenant = Tenant::findOrFail($tenantId);

tenancy()->initialize($tenant);

try {

    ...

} finally {

    tenancy()->end();

}
```

---

## Cache Isolation

Semua cache key wajib mengandung tenant.

Contoh:

```text
tenant:{tenantId}:dashboard

tenant:{tenantId}:products

tenant:{tenantId}:subscription
```

---

## Storage Isolation

Direkomendasikan:

```text
storage/

tenant-1/

tenant-2/

tenant-3/
```

Tenant tidak boleh membaca file tenant lain.

---

# 6. Subscription Security

Package:

```text
laravelcm/laravel-subscriptions
```

Subscription melekat pada:

```text
Tenant
```

Bukan User.

---

## Validation Order

```text
Authentication

↓

Tenant

↓

Subscription

↓

Permission

↓

Business Logic
```

---

## Feature Validation

Semua feature harus diperiksa di backend.

Contoh:

```text
advanced_reports

custom_domain

api_access

mobile_staff
```

---

## Usage Limit

Contoh:

```text
branches

users

products

bookings

storage
```

Limit tidak boleh hanya dicek di UI.

---

# 7. Rate Limit

Seluruh API wajib menggunakan Rate Limiting.

Laravel Middleware:

```php
throttle
```

---

## Public API

```text
60 request / menit
```

---

## Authenticated API

```text
300 request / menit
```

---

## Login

```text
5 login

/

menit
```

Setelah melebihi batas:

```text
429 Too Many Requests
```

---

## OTP

```text
3 request

/

5 menit
```

---

## Upload

```text
20 upload

/

jam
```

---

## Webhook

Whitelist:

- Midtrans
- Xendit
- Tripay

Gunakan:

- Signature Validation
- Idempotency
- Replay Protection

---

# 8. Input Validation

Semua endpoint wajib menggunakan:

```text
Laravel Form Request
```

Tidak diperbolehkan validasi langsung di Controller.

---

## File Upload

Validasi:

- MIME Type
- Extension
- File Size

Contoh:

```php
'image|max:5120'
```

---

## SQL Injection

Gunakan:

- Eloquent
- Query Builder
- Parameter Binding

Tidak diperbolehkan:

```php
DB::select("SELECT * FROM users WHERE id=$id");
```

---

## XSS

Output HTML harus disanitasi.

Blade:

```blade
{{ $title }}
```

bukan

```blade
{!! $title !!}
```

kecuali benar-benar dibutuhkan.

---

## Mass Assignment

Seluruh Model wajib menggunakan:

```php
$fillable
```

atau DTO.

Tidak diperbolehkan:

```php
$guarded = [];
```

---

# 9. Sensitive Data

Data berikut dianggap sensitif:

- Password
- Access Token
- Refresh Token
- API Key
- Secret Key
- Payment Token
- Identity Number
- Bank Account
- Customer Document

Data sensitif:

- Tidak boleh di-log.
- Tidak boleh dikirim ke frontend jika tidak diperlukan.
- Tidak boleh ditampilkan pada exception.

---

# 10. Audit Log

Semua aktivitas penting wajib dicatat.

---

## Audit Event

Contoh:

- Login
- Logout
- Password Change
- Booking Created
- Booking Cancelled
- Payment Verified
- Refund
- User Created
- User Deleted
- Product Deleted
- Subscription Changed
- Role Changed

---

## Audit Data

Minimal menyimpan:

```text
User ID

Tenant ID

Action

Entity

Entity ID

Old Value

New Value

IP Address

User Agent

Created At
```

---

## Audit Example

```json
{
    "tenant_id": "...",
    "user_id": "...",
    "action": "booking.cancel",
    "entity": "Booking",
    "entity_id": "...",
    "old_values": {},
    "new_values": {},
    "ip_address": "127.0.0.1",
    "user_agent": "...",
    "created_at": "..."
}
```

Audit Log bersifat immutable dan tidak boleh diubah oleh pengguna.

---

# 11. Logging

Gunakan Laravel Log.

Level:

```text
Emergency
Alert
Critical
Error
Warning
Notice
Info
Debug
```

Production:

- Debug dimatikan.
- Stack trace tidak dikirim ke client.
- Sensitive data dimasking.

---

# 12. Security Headers

Server wajib mengaktifkan:

```text
X-Frame-Options

DENY
```

```text
X-Content-Type-Options

nosniff
```

```text
Referrer-Policy

strict-origin-when-cross-origin
```

```text
Permissions-Policy
```

```text
Content-Security-Policy
```

---

# 13. HTTPS

Seluruh production wajib menggunakan:

```text
HTTPS
TLS 1.2+
```

Tidak diperbolehkan HTTP biasa.

---

# 14. Secrets Management

Rahasia aplikasi disimpan pada:

```text
.env
```

atau Secret Manager.

Tidak diperbolehkan:

- Commit `.env`
- Commit API Key
- Commit Database Password
- Commit Private Key

---

# 15. Backup & Recovery

Backup meliputi:

- Database
- Storage
- Audit Log

Backup harus:

- Terenkripsi.
- Memiliki retensi.
- Dapat direstore.

---

# 16. OWASP Checklist

Sistem harus terlindungi dari:

- Broken Access Control
- Cryptographic Failures
- Injection
- Insecure Design
- Security Misconfiguration
- Vulnerable Components
- Authentication Failures
- Software Integrity Failure
- Logging Failure
- SSRF

---

# 17. Security Testing

Wajib dilakukan:

- Unit Test
- Feature Test
- Authorization Test
- Tenant Isolation Test
- Rate Limit Test
- Subscription Validation Test
- File Upload Test
- SQL Injection Test
- XSS Test
- Audit Log Test

---

# 18. Incident Response

Jika ditemukan insiden keamanan:

1. Isolasi sistem.
2. Nonaktifkan akses terkait.
3. Simpan audit log.
4. Analisis penyebab.
5. Patch kerentanan.
6. Verifikasi perbaikan.
7. Dokumentasikan insiden.

---

# 19. Security Checklist

Sebelum release:

```text
☐ Authentication menggunakan Sanctum
☐ Authorization menggunakan Policy & Permission
☐ Tenant Isolation tervalidasi
☐ Subscription tervalidasi
☐ Rate Limit aktif
☐ Audit Log aktif
☐ HTTPS aktif
☐ Security Header aktif
☐ Input Validation lengkap
☐ SQL Injection aman
☐ XSS aman
☐ File Upload aman
☐ Secrets tidak tersimpan di repository
☐ Backup berjalan
☐ Semua Security Test lulus
```
