# Multi-Tenancy Architecture

## 1. Package

Sewantara menggunakan package berikut untuk kebutuhan multi-tenancy:

```text
Package:
stancl/tenancy
```

Package ini bertanggung jawab terhadap:

- Identifikasi tenant.
- Inisialisasi tenant context.
- Pemisahan central route dan tenant route.
- Resolusi tenant berdasarkan domain.
- Resolusi tenant berdasarkan subdomain.
- Resolusi tenant berdasarkan path.
- Resolusi tenant berdasarkan request header.
- Custom tenant resolver.
- Lifecycle event tenancy.

Import utama:

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
```

---

# 2. Strategi Identifikasi Tenant

Sewantara menggunakan beberapa metode identifikasi tenant berdasarkan jenis client dan kebutuhan sistem.

```text
Landing Page Mitra
    → Domain atau Subdomain

Mobile App
    → Path Tenant ID

Internal Integration
    → Request Header

Central SaaS
    → Tanpa Tenant Context
```

Strategi ini dipilih karena Sewantara memiliki beberapa jenis aplikasi:

- Landing page publik tenant.
- Dashboard web tenant.
- Mobile app owner dan staff.
- Website pusat SaaS.
- Super Admin SaaS.
- Webhook dan internal service.

---

# 3. Central Application

Central application berjalan tanpa tenant context.

Central application digunakan untuk:

- Landing page utama Sewantara.
- Registrasi tenant baru.
- Login Super Admin.
- Manajemen paket.
- Subscription SaaS.
- Tenant provisioning.
- Tenant suspension.
- Tenant activation.
- Verifikasi domain.
- Central billing.
- Global reporting.

Contoh central domain:

```text
sewantara.id
www.sewantara.id
api.sewantara.id
admin.sewantara.id
```

Central route tidak menggunakan middleware tenancy.

Contoh:

```php
use Illuminate\Support\Facades\Route;

Route::domain('api.sewantara.id')
    ->prefix('api/v1')
    ->group(function () {
        Route::post('/auth/register', RegisterTenantController::class);
        Route::post('/auth/login', CentralLoginController::class);
        Route::get('/plans', ListPlansController::class);
        Route::post('/tenants', CreateTenantController::class);
    });
```

Central domain wajib didaftarkan pada konfigurasi tenancy:

```php
'central_domains' => [
    'sewantara.id',
    'www.sewantara.id',
    'api.sewantara.id',
    'admin.sewantara.id',
],
```

Central domain tidak boleh terdeteksi sebagai tenant.

---

# 4. Landing Page Tenant

Landing page atau website publik milik mitra menggunakan identifikasi tenant berdasarkan domain atau subdomain.

Middleware yang digunakan:

```php
Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain
```

Import:

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
```

---

## 4.1 Subdomain Tenant

Contoh subdomain:

```text
rentalkamera.sewantara.id
tendamakmur.sewantara.id
mobiljember.sewantara.id
```

Pada tabel domain, nilai subdomain dapat disimpan tanpa titik:

```text
rentalkamera
tendamakmur
mobiljember
```

Record yang tidak memiliki titik akan dianggap sebagai subdomain.

Contoh data:

```text
tenant_id : tenant-uuid
domain    : rentalkamera
```

Request:

```text
https://rentalkamera.sewantara.id
```

akan di-resolve menjadi tenant yang memiliki domain:

```text
rentalkamera
```

---

## 4.2 Custom Domain Tenant

Contoh custom domain:

```text
booking.rentalmakmur.com
sewa.kamerajember.id
rentalmotorjember.com
```

Pada tabel domain, custom domain disimpan lengkap:

```text
booking.rentalmakmur.com
sewa.kamerajember.id
rentalmotorjember.com
```

Record yang mengandung titik dianggap sebagai domain atau hostname penuh.

Contoh data:

```text
tenant_id : tenant-uuid
domain    : booking.rentalmakmur.com
```

---

## 4.3 Route Landing Page

Contoh implementasi:

```php
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomainOrSubdomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', LandingPageController::class);

    Route::get('/produk', ProductCatalogController::class);

    Route::get(
        '/produk/{slug}',
        ProductDetailController::class
    );

    Route::post(
        '/cek-ketersediaan',
        CheckPublicAvailabilityController::class
    );

    Route::post(
        '/booking',
        CreatePublicBookingController::class
    );
});
```

---

## 4.4 Kegunaan Domain-Based Tenancy

Domain atau subdomain digunakan untuk:

- Landing page tenant.
- Website katalog tenant.
- Website booking tenant.
- Custom branding tenant.
- SEO tenant.
- Custom domain.
- Public product catalog.
- Public availability check.
- Public booking form.
- Customer self-service portal.

---

## 4.5 Alur Identifikasi

```text
Request masuk
    ↓
Baca hostname
    ↓
InitializeTenancyByDomainOrSubdomain
    ↓
Cari record pada domains
    ↓
Resolve tenant
    ↓
Initialize tenant context
    ↓
Jalankan route tenant
```

---

# 5. Mobile App Tenant

Mobile app owner, admin, kasir, staff gudang, dan driver menggunakan identifikasi tenant berdasarkan path.

Middleware yang digunakan:

```php
Stancl\Tenancy\Middleware\InitializeTenancyByPath
```

Import:

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
```

Format API:

```text
/api/v1/{tenant}/...
```

Parameter `{tenant}` dapat berisi:

- Tenant UUID.
- Tenant key.
- Tenant slug.
- Identifier lain yang didukung resolver.

Rekomendasi Sewantara:

```text
Tenant UUID
```

Contoh:

```text
/api/v1/8aa487e7-5e13-4f56-a63d-82ce54da2f04/products
/api/v1/8aa487e7-5e13-4f56-a63d-82ce54da2f04/bookings
/api/v1/8aa487e7-5e13-4f56-a63d-82ce54da2f04/customers
```

---

## 5.1 Alasan Mobile Menggunakan Path

Mobile app tidak bergantung pada domain tenant.

Dengan path identification:

- Satu base API dapat digunakan seluruh tenant.
- Mobile app tidak perlu mengganti base URL.
- Tenant dikirim secara eksplisit pada URL.
- Debugging lebih mudah.
- API gateway lebih sederhana.
- Cocok untuk Flutter.
- Cocok untuk mobile multi-tenant.
- Mudah digunakan pada staging dan local development.

Base URL mobile:

```text
https://api.sewantara.id
```

Endpoint tenant:

```text
https://api.sewantara.id/api/v1/{tenant}/products
```

---

## 5.2 Route Mobile API

Contoh implementasi:

```php
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

Route::prefix('api/v1/{tenant}')
    ->middleware([
        InitializeTenancyByPath::class,
        'auth:sanctum',
        'subscription.active',
    ])
    ->group(function () {
        Route::get('/me', CurrentUserController::class);

        Route::apiResource(
            'products',
            ProductController::class
        );

        Route::apiResource(
            'customers',
            CustomerController::class
        );

        Route::apiResource(
            'bookings',
            BookingController::class
        );

        Route::get(
            '/inventory/availability',
            InventoryAvailabilityController::class
        );

        Route::get(
            '/reports/dashboard',
            DashboardReportController::class
        );
    });
```

---

## 5.3 Contoh Flutter

Base URL:

```dart
const baseUrl = 'https://api.sewantara.id/api/v1';
```

Tenant path:

```dart
final endpoint = '$baseUrl/$tenantId/bookings';
```

Contoh Dio:

```dart
final response = await dio.get(
  '/$tenantId/bookings',
  queryParameters: {
    'status': 'confirmed',
  },
);
```

Tenant ID dapat disimpan setelah login:

```dart
await secureStorage.write(
  key: 'tenant_id',
  value: loginResponse.tenant.id,
);
```

---

## 5.4 Validasi Tenant dan User

Walaupun tenant berada pada path, backend tetap wajib memastikan:

```text
Authenticated user tenant_id
harus sama dengan
tenant pada URL
```

Validasi:

```php
if (auth()->user()->tenant_id !== tenant('id')) {
    abort(403, 'Tenant access denied.');
}
```

Validasi sebaiknya ditempatkan pada middleware khusus:

```php
final class EnsureUserBelongsToTenant
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (
            $user !== null &&
            $user->tenant_id !== tenant('id')
        ) {
            abort(403, 'Tenant access denied.');
        }

        return $next($request);
    }
}
```

Route:

```php
Route::prefix('api/v1/{tenant}')
    ->middleware([
        InitializeTenancyByPath::class,
        'auth:sanctum',
        EnsureUserBelongsToTenant::class,
        'subscription.active',
    ])
    ->group(function () {
        // Tenant API routes
    });
```

---

# 6. Request Data Identification

Untuk integrasi internal, webhook tertentu, testing, atau trusted service, tenant dapat diidentifikasi berdasarkan request header.

Middleware:

```php
Stancl\Tenancy\Middleware\InitializeTenancyByRequestData
```

Import:

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
```

Default header:

```http
X-Tenant: tenant-id
```

Default query parameter:

```text
?tenant=tenant-id
```

---

## 6.1 Konfigurasi Header

Sewantara menggunakan header:

```http
X-Tenant-ID: tenant-id
```

Konfigurasi:

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

InitializeTenancyByRequestData::$header = 'X-Tenant-ID';
```

Untuk menonaktifkan query parameter:

```php
InitializeTenancyByRequestData::$queryParameter = null;
```

Rekomendasi Sewantara:

```text
Request header identification
hanya digunakan untuk internal service.
```

Public client tidak boleh memilih tenant bebas melalui header.

---

## 6.2 Route Internal API

```php
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

Route::prefix('internal/v1')
    ->middleware([
        'auth.internal',
        InitializeTenancyByRequestData::class,
    ])
    ->group(function () {
        Route::post(
            '/sync-inventory',
            SyncInventoryController::class
        );

        Route::post(
            '/rebuild-report',
            RebuildTenantReportController::class
        );
    });
```

---

## 6.3 Kegunaan Request Data Identification

Digunakan untuk:

- Internal microservice.
- Queue callback.
- Scheduled task gateway.
- Data migration.
- ETL.
- Internal reporting.
- Testing.
- Trusted integration.

Tidak digunakan untuk:

- Landing page tenant.
- Mobile app publik.
- Customer booking website.
- Browser request biasa.

---

# 7. Manual Tenant Initialization

Pada proses tertentu, tenant dapat diinisialisasi secara manual.

Contoh:

- Queue job.
- Scheduled command.
- Webhook.
- Import data.
- Batch process.
- Background report.
- Notification worker.

Contoh:

```php
$tenant = Tenant::findOrFail($tenantId);

tenancy()->initialize($tenant);

try {
    // Tenant-specific process
} finally {
    tenancy()->end();
}
```

Contoh queue job:

```php
final class GenerateTenantReportJob
{
    public function __construct(
        public readonly string $tenantId,
    ) {
    }

    public function handle(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        tenancy()->initialize($tenant);

        try {
            app(GenerateReportAction::class)
                ->execute($this->tenantId);
        } finally {
            tenancy()->end();
        }
    }
}
```

Setiap queue job tenant wajib membawa:

```text
tenant_id
```

Tidak boleh bergantung pada:

```php
auth()->user()
```

karena authentication context tidak tersedia pada queue.

---

# 8. Custom onFail Handling

Setiap tenancy identification middleware memiliki properti static `$onFail`.

Sewantara harus memiliki response yang berbeda berdasarkan jenis client.

---

## 8.1 Landing Page onFail

Jika tenant tidak ditemukan pada landing page, user diarahkan ke halaman pusat.

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;

InitializeTenancyByDomainOrSubdomain::$onFail =
    function ($exception, $request, $next) {
        return redirect(
            'https://sewantara.id/tenant-not-found'
        );
    };
```

---

## 8.2 API Path onFail

Untuk mobile API, response harus JSON.

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByPath;

InitializeTenancyByPath::$onFail =
    function ($exception, $request, $next) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TENANT_NOT_FOUND',
                'message' => 'Tenant tidak ditemukan.',
            ],
        ], 404);
    };
```

---

## 8.3 Request Header onFail

```php
use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;

InitializeTenancyByRequestData::$onFail =
    function ($exception, $request, $next) {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'TENANT_HEADER_INVALID',
                'message' => 'Tenant header tidak valid.',
            ],
        ], 400);
    };
```

---

# 9. Tenant Route Architecture

Rekomendasi struktur route:

```text
routes/
├── central.php
├── tenant-web.php
├── tenant-api.php
├── public-tenant-api.php
└── internal.php
```

---

## 9.1 central.php

Digunakan untuk:

- Registrasi tenant.
- Login central.
- Daftar plan.
- Subscription.
- Super Admin.
- Tenant provisioning.

Tidak menggunakan tenancy middleware.

---

## 9.2 tenant-web.php

Digunakan untuk:

- Landing page mitra.
- Product catalog.
- Booking website.
- Customer portal.

Middleware:

```php
InitializeTenancyByDomainOrSubdomain::class
```

---

## 9.3 tenant-api.php

Digunakan untuk:

- Flutter mobile.
- Tenant admin API.
- Staff API.
- Owner API.

Middleware:

```php
InitializeTenancyByPath::class
```

Prefix:

```text
/api/v1/{tenant}
```

---

## 9.4 public-tenant-api.php

Digunakan untuk public API website tenant.

Dapat menggunakan:

```php
InitializeTenancyByDomainOrSubdomain::class
```

Endpoint:

```text
/public/catalog
/public/products
/public/availability
/public/bookings
```

---

## 9.5 internal.php

Digunakan untuk:

- Internal service.
- Data sync.
- Batch process.
- Internal reporting.

Middleware:

```php
InitializeTenancyByRequestData::class
```

---

# 10. Tenant Identification Matrix

| Client                | Metode           | Middleware                             |
| --------------------- | ---------------- | -------------------------------------- |
| Landing page mitra    | Domain/subdomain | `InitializeTenancyByDomainOrSubdomain` |
| Website booking mitra | Domain/subdomain | `InitializeTenancyByDomainOrSubdomain` |
| Custom domain mitra   | Domain/subdomain | `InitializeTenancyByDomainOrSubdomain` |
| Flutter owner app     | Path             | `InitializeTenancyByPath`              |
| Flutter staff app     | Path             | `InitializeTenancyByPath`              |
| Mobile customer app   | Path             | `InitializeTenancyByPath`              |
| Internal service      | Header           | `InitializeTenancyByRequestData`       |
| Queue job             | Manual           | `tenancy()->initialize()`              |
| Scheduled task        | Manual           | `tenancy()->initialize()`              |
| Central SaaS          | Tidak ada        | Tanpa tenancy middleware               |
| Super Admin           | Tidak ada        | Central context                        |

---

# 11. API URL Convention

## Central API

```text
POST /api/v1/auth/register
POST /api/v1/auth/login
GET  /api/v1/plans
POST /api/v1/tenants
```

Base:

```text
https://api.sewantara.id
```

---

## Mobile Tenant API

```text
GET  /api/v1/{tenant}/products
POST /api/v1/{tenant}/bookings
GET  /api/v1/{tenant}/customers
GET  /api/v1/{tenant}/reports/dashboard
```

Contoh:

```text
https://api.sewantara.id/api/v1/
8aa487e7-5e13-4f56-a63d-82ce54da2f04/
bookings
```

---

## Landing Page Tenant

Subdomain:

```text
https://rentalkamera.sewantara.id
```

Custom domain:

```text
https://booking.rentaljember.com
```

Endpoint:

```text
GET  /
GET  /produk
GET  /produk/{slug}
POST /cek-ketersediaan
POST /booking
```

---

# 12. Subscription Architecture

Subscription management menggunakan package:

```text
Package:
laravelcm/laravel-subscriptions
```

Tenant menjadi model subscriber.

```text
Tenant
    ↓
Subscription
    ↓
Plan
    ↓
Plan Features
```

Subscription tidak melekat pada user.

Alasannya:

- Satu tenant memiliki banyak user.
- Paket berlaku pada seluruh bisnis.
- Limit cabang berlaku per tenant.
- Limit produk berlaku per tenant.
- Limit booking berlaku per tenant.
- Feature entitlement berlaku per tenant.

---

## 12.1 Tenant Model

Tenant mengimplementasikan behavior subscriber package.

Contoh:

```php
use Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;

final class Tenant extends BaseTenant
{
    use HasPlanSubscriptions;
}
```

Catatan implementasi harus disesuaikan dengan API trait dari versi package yang digunakan.

---

## 12.2 Fitur Subscription

Contoh feature:

```text
branches
users
products
product_units
monthly_bookings
custom_domain
payment_gateway
advanced_reports
whatsapp_notification
api_access
white_label
mobile_staff
```

---

## 12.3 Middleware Subscription

Setelah tenant diinisialisasi, subscription harus divalidasi.

Urutan middleware mobile:

```text
InitializeTenancyByPath
    ↓
Authenticate Sanctum
    ↓
EnsureUserBelongsToTenant
    ↓
EnsureTenantSubscriptionActive
    ↓
EnsureSubscriptionFeature
    ↓
Permission
```

Contoh:

```php
Route::prefix('api/v1/{tenant}')
    ->middleware([
        InitializeTenancyByPath::class,
        'auth:sanctum',
        EnsureUserBelongsToTenant::class,
        EnsureTenantSubscriptionActive::class,
    ])
    ->group(function () {
        Route::post(
            '/branches',
            CreateBranchController::class
        )->middleware(
            'subscription.limit:branches'
        );

        Route::get(
            '/reports/revenue',
            RevenueReportController::class
        )->middleware(
            'subscription.feature:advanced_reports'
        );
    });
```

---

## 12.4 Domain Landing Page Subscription

Landing page tenant masih dapat diakses pada kondisi tertentu.

Contoh kebijakan:

| Subscription Status |      Landing Page |                  Admin Write |
| ------------------- | ----------------: | ---------------------------: |
| Trial               |             Aktif |                        Aktif |
| Active              |             Aktif |                        Aktif |
| Past Due            |             Aktif |                     Terbatas |
| Grace Period        |             Aktif |                    Read-only |
| Suspended           |   Halaman suspend |                  Tidak aktif |
| Expired             |   Halaman expired |                  Tidak aktif |
| Cancelled           | Sesuai masa aktif | Tidak aktif setelah end date |

Landing page middleware dapat mengecek status tenant setelah tenancy berhasil diinisialisasi.

---

# 13. Security Rules

## 13.1 Tenant dari Client Tidak Dipercaya

Nilai tenant dari:

- Path.
- Header.
- Domain.
- Query parameter.

harus dianggap sebagai input tidak terpercaya.

Backend wajib memvalidasi:

- Tenant ada.
- Tenant aktif.
- Domain benar.
- User anggota tenant.
- Token milik tenant.
- Subscription valid.
- Resource berasal dari tenant yang sama.

---

## 13.2 Route Model Binding

Route model binding wajib dibatasi tenant.

Tidak boleh:

```php
Product::findOrFail($id);
```

Gunakan:

```php
Product::query()
    ->where('tenant_id', tenant('id'))
    ->findOrFail($id);
```

Atau gunakan tenant-aware global scope.

---

## 13.3 Cross-Tenant Access

Request berikut wajib ditolak:

```text
User Tenant A
mengakses URL Tenant B
```

Contoh:

```text
Token Tenant A
GET /api/v1/tenant-b-id/bookings
```

Response:

```json
{
    "success": false,
    "error": {
        "code": "TENANT_ACCESS_DENIED",
        "message": "Anda tidak memiliki akses ke tenant ini."
    }
}
```

HTTP status:

```text
403 Forbidden
```

---

## 13.4 Header Tenant

`X-Tenant-ID` tidak boleh dipakai sebagai satu-satunya mekanisme authorization.

Header hanya untuk resolusi context.

Authorization tetap berdasarkan:

- Internal API token.
- Service identity.
- Allowed tenant scope.
- Signed request.
- Permission.

---

# 14. Testing Requirements

## Domain Identification Test

- Subdomain berhasil resolve tenant.
- Custom domain berhasil resolve tenant.
- Domain tidak dikenal gagal.
- Central domain tidak menjadi tenant.
- Subdomain tanpa titik dianggap subdomain.
- Domain bertitik dianggap hostname.

---

## Path Identification Test

- Tenant UUID valid berhasil.
- Tenant tidak ditemukan menghasilkan `TENANT_NOT_FOUND`.
- User tenant A tidak dapat akses path tenant B.
- Token invalid ditolak.
- Subscription expired ditolak.
- Route tanpa tenant ID ditolak.

---

## Request Data Test

- Header `X-Tenant-ID` valid berhasil.
- Header tidak valid ditolak.
- Query parameter disabled.
- Public token tidak dapat memakai header internal.
- Internal token tenant scope diverifikasi.

---

## Queue Test

- Queue membawa tenant ID.
- Tenancy diinisialisasi sebelum proses.
- Tenancy diakhiri setelah proses.
- Job tenant A tidak memproses data tenant B.
- Retry tetap menggunakan tenant yang sama.

---

## Subscription Test

- Tenant active dapat write.
- Tenant expired tidak dapat write.
- Plan tanpa custom domain ditolak.
- Branch limit diterapkan.
- User limit diterapkan.
- Product limit diterapkan.
- Feature entitlement diperiksa backend.

---

# 15. Final Architecture

Arsitektur tenancy Sewantara:

```text
Central SaaS
    → Tanpa Tenant Context

Landing Page Mitra
    → InitializeTenancyByDomainOrSubdomain

Custom Domain Mitra
    → InitializeTenancyByDomainOrSubdomain

Mobile App
    → InitializeTenancyByPath

Internal API
    → InitializeTenancyByRequestData

Queue dan Scheduler
    → Manual Tenant Initialization
```

Package yang digunakan:

```text
Tenant Management:
stancl/tenancy

Subscription Management:
laravelcm/laravel-subscriptions
```

Keputusan utama:

1. Landing page tenant menggunakan domain atau subdomain.
2. Custom domain tenant menggunakan domain resolution yang sama.
3. Mobile app menggunakan path `/api/v1/{tenant}`.
4. Tenant pada mobile direkomendasikan menggunakan UUID.
5. Internal service dapat menggunakan `X-Tenant-ID`.
6. Central SaaS tidak menggunakan tenant middleware.
7. Queue membawa tenant ID dan menginisialisasi tenant secara manual.
8. User harus diverifikasi sebagai anggota tenant pada path.
9. Subscription melekat pada Tenant, bukan User.
10. Feature dan limit subscription diperiksa pada backend.
11. Tenant resolution tidak menggantikan authorization.
12. Semua query dan resource wajib terisolasi berdasarkan tenant.
