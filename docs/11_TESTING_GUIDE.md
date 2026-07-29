# Testing Guide

## Sewantara — Universal Rental Management SaaS

**Versi:** 1.0
**Status:** Draft
**Backend:** Laravel 12
**Frontend:** Nuxt 4
**Mobile:** Flutter
**Database:** PostgreSQL
**Tenant Management:** `stancl/tenancy`
**Subscription Management:** `laravelcm/laravel-subscriptions`

---

# 1. Tujuan

Dokumen ini menjadi standar pengujian aplikasi Sewantara.

Pengujian bertujuan untuk:

- Memastikan fitur bekerja sesuai requirement.
- Mencegah regression.
- Menjamin isolasi data antar-tenant.
- Mencegah double booking.
- Menjamin akurasi pembayaran dan deposit.
- Memastikan subscription dan feature limit berjalan.
- Menjaga kestabilan API.
- Mengukur performa sistem.
- Mempermudah refactoring.
- Meningkatkan kepercayaan saat deployment.

Dokumen ini berlaku untuk:

- Backend developer.
- Frontend developer.
- Mobile developer.
- QA engineer.
- DevOps engineer.
- Technical lead.

---

# 2. Testing Principles

Sewantara menggunakan prinsip berikut:

## 2.1 Test Behavior, Not Implementation

Test harus menguji hasil dan aturan bisnis, bukan detail internal class.

Fokus:

```text
Input
↓
Business process
↓
Expected result
```

Hindari test yang terlalu bergantung pada:

- Nama private method.
- Urutan pemanggilan internal.
- Detail implementasi framework.
- Struktur query yang tidak relevan dengan hasil.

---

## 2.2 Deterministic

Test harus memberikan hasil yang sama setiap kali dijalankan.

Test tidak boleh bergantung pada:

- Waktu sistem tanpa fake clock.
- Data production.
- API eksternal nyata.
- Urutan eksekusi test.
- Internet.
- Nilai random tanpa seed.

---

## 2.3 Isolated

Setiap test harus dapat berjalan sendiri.

Test tidak boleh bergantung pada data yang dibuat test sebelumnya.

Gunakan:

```text
Factory
Fixture
Fake
Mock
Database transaction
```

---

## 2.4 Fast Feedback

Test yang paling cepat harus dijalankan lebih dahulu.

Urutan umum:

```text
Static Analysis
↓
Unit Test
↓
Feature Test
↓
Integration Test
↓
API Test
↓
Performance Test
```

---

## 2.5 Tenant-Aware

Semua fitur tenant wajib memiliki test isolasi tenant.

Minimal setiap modul menguji:

```text
Tenant A dapat mengakses datanya sendiri.
Tenant A tidak dapat mengakses data Tenant B.
```

---

## 2.6 Regression Test

Setiap bug yang diperbaiki wajib memiliki test yang gagal sebelum perbaikan dan berhasil setelah perbaikan.

---

# 3. Testing Pyramid

Proporsi pengujian yang disarankan:

```text
Unit Test        50–60%
Feature Test     25–35%
Integration Test 10–15%
API/E2E Test      5–10%
Performance Test  sesuai kebutuhan
```

Unit test dibuat lebih banyak karena:

- Cepat.
- Mudah dijalankan.
- Mudah menemukan lokasi error.
- Tidak membutuhkan seluruh aplikasi.

---

# 4. Testing Tools

## 4.1 Backend

Gunakan:

```text
Pest PHP
PHPUnit
Laravel HTTP Testing
Laravel Database Testing
Mockery
Laravel Sanctum Testing
Laravel Queue Fake
Laravel Event Fake
Laravel Notification Fake
Laravel Storage Fake
Laravel Time Travel
```

Command:

```bash
php artisan test
```

atau:

```bash
./vendor/bin/pest
```

---

## 4.2 Static Analysis

Gunakan:

```text
PHPStan atau Larastan
Laravel Pint
Composer Audit
```

Command:

```bash
./vendor/bin/phpstan analyse
./vendor/bin/pint --test
composer audit
```

---

## 4.3 Frontend

Gunakan:

```text
Vitest
Vue Test Utils
Playwright
ESLint
TypeScript
```

---

## 4.4 Flutter

Gunakan:

```text
flutter_test
bloc_test
mocktail
integration_test
golden_toolkit
```

Command:

```bash
flutter test
flutter analyze
```

---

## 4.5 Performance

Gunakan:

```text
k6
Apache JMeter
Artillery
Laravel Pulse
Prometheus
Grafana
```

Rekomendasi utama:

```text
k6
```

---

# 5. Test Environment

Environment test harus terpisah dari development dan production.

File:

```text
.env.testing
```

Contoh:

```env
APP_ENV=testing
APP_DEBUG=false

DB_CONNECTION=pgsql
DB_DATABASE=sewantara_testing

CACHE_STORE=array
SESSION_DRIVER=array
QUEUE_CONNECTION=sync
MAIL_MAILER=array

FILESYSTEM_DISK=local
```

Dilarang menjalankan automated test pada database production.

---

# 6. Database Testing Strategy

## 6.1 Database Khusus Test

Gunakan database khusus:

```text
sewantara_testing
```

Jangan menggunakan database development utama.

---

## 6.2 Refresh Database

Feature dan integration test dapat menggunakan:

```php
use RefreshDatabase;
```

Atau database transaction jika lebih sesuai.

---

## 6.3 Factory

Semua entity utama wajib memiliki factory.

Contoh:

```text
TenantFactory
UserFactory
BranchFactory
CustomerFactory
ProductFactory
ProductUnitFactory
BookingFactory
PaymentFactory
SubscriptionFactory
```

Factory harus memiliki state.

Contoh:

```php
ProductUnitFactory::new()->available()->create();

ProductUnitFactory::new()->maintenance()->create();

BookingFactory::new()->confirmed()->create();

CustomerFactory::new()->blacklisted()->create();
```

---

## 6.4 Seeder

Seeder digunakan untuk:

- Development.
- Demo.
- Staging.
- Baseline permission.
- Default subscription plan.

Seeder tidak boleh menjadi dependency wajib test.

Test harus membuat datanya sendiri menggunakan factory.

---

# 7. Unit Test

## 7.1 Tujuan

Unit test menguji satu unit business logic secara terisolasi.

Unit test tidak menggunakan:

- HTTP.
- Database nyata.
- Redis.
- Queue nyata.
- Payment gateway nyata.
- Storage nyata.

---

## 7.2 Objek yang Diuji

Unit test digunakan untuk:

- Value Object.
- Entity.
- Aggregate.
- Domain Service.
- Specification.
- Strategy.
- State transition.
- Calculator.
- Validator domain.
- DTO normalization.

---

## 7.3 Naming

Format nama test:

```text
it_<expected_behavior>
```

Contoh:

```php
it('rejects an invalid rental period', function () {
    // ...
});
```

Atau struktur:

```text
Given
When
Then
```

---

## 7.4 Rental Period Test

```php
use App\Modules\Booking\Domain\Exceptions\InvalidRentalPeriod;
use App\Modules\Booking\Domain\ValueObjects\RentalPeriod;

it('rejects an end time before the start time', function () {
    expect(fn () => new RentalPeriod(
        startAt: new DateTimeImmutable('2026-08-02 10:00:00'),
        endAt: new DateTimeImmutable('2026-08-01 10:00:00'),
    ))->toThrow(InvalidRentalPeriod::class);
});
```

---

## 7.5 Period Overlap Test

```php
it('detects overlapping rental periods', function () {
    $first = new RentalPeriod(
        new DateTimeImmutable('2026-08-01 08:00:00'),
        new DateTimeImmutable('2026-08-03 08:00:00'),
    );

    $second = new RentalPeriod(
        new DateTimeImmutable('2026-08-02 08:00:00'),
        new DateTimeImmutable('2026-08-04 08:00:00'),
    );

    expect($first->overlaps($second))->toBeTrue();
});
```

Boundary harus menggunakan interval:

```text
[start_at, end_at)
```

Artinya booking baru boleh dimulai tepat ketika booking lama berakhir.

```php
it('does not treat adjacent periods as overlapping', function () {
    $first = new RentalPeriod(
        new DateTimeImmutable('2026-08-01 08:00:00'),
        new DateTimeImmutable('2026-08-03 08:00:00'),
    );

    $second = new RentalPeriod(
        new DateTimeImmutable('2026-08-03 08:00:00'),
        new DateTimeImmutable('2026-08-04 08:00:00'),
    );

    expect($first->overlaps($second))->toBeFalse();
});
```

---

## 7.6 Pricing Strategy Test

Test minimal:

- Hourly pricing.
- Daily pricing.
- Weekly pricing.
- Monthly pricing.
- Minimum duration.
- Partial duration rounding.
- Weekend pricing jika tersedia.
- Custom price.
- Currency consistency.

```php
it('calculates daily rental pricing', function () {
    $strategy = new DailyPricingStrategy();

    $result = $strategy->calculate(
        unitPrice: Money::idr(350_000),
        duration: 2,
    );

    expect($result)->toEqual(Money::idr(700_000));
});
```

---

## 7.7 Booking State Test

Test setiap transisi valid:

```text
draft → pending
pending → confirmed
confirmed → preparing
preparing → ready
ready → ongoing
ongoing → returned
returned → completed
```

Test setiap transisi tidak valid.

```php
it('prevents a completed booking from returning to pending', function () {
    expect(
        BookingStatus::Completed->canTransitionTo(
            BookingStatus::Pending
        )
    )->toBeFalse();
});
```

---

## 7.8 Deposit Test

Test minimal:

- Deposit bukan pendapatan.
- Potongan tidak melebihi saldo.
- Refund tidak melebihi sisa.
- Full refund.
- Partial deduction.
- Forfeit.
- Duplicate refund ditolak.

---

## 7.9 Subscription Domain Test

Karena subscription menggunakan `laravelcm/laravel-subscriptions`, unit test business wrapper Sewantara harus menguji:

- Plan memiliki feature.
- Plan tidak memiliki feature.
- Limit usage.
- Unlimited feature.
- Trial active.
- Subscription expired.
- Grace period.
- Downgrade invalid karena usage melebihi limit.

Hindari menguji source code internal package. Uji integrasi Sewantara terhadap package.

---

# 8. Feature Test

## 8.1 Tujuan

Feature test menguji satu fitur melalui application boundary.

Feature test dapat menggunakan:

- HTTP endpoint.
- Database.
- Middleware.
- Authentication.
- Authorization.
- Tenant context.
- Validation.
- Event fake.
- Queue fake.

---

## 8.2 Hal yang Diuji

Feature test digunakan untuk:

- Endpoint API.
- Authentication.
- Permission.
- Tenant isolation.
- Validation.
- Database persistence.
- Subscription enforcement.
- Status transition.
- Response format.
- Error code.

---

## 8.3 Authentication Test

Test minimal:

```text
Login berhasil.
Password salah ditolak.
User nonaktif ditolak.
Tenant suspended ditolak.
Token invalid ditolak.
Logout mencabut token.
```

Contoh:

```php
it('authenticates an active tenant user', function () {
    $tenant = Tenant::factory()->active()->create();
    $user = User::factory()
        ->for($tenant)
        ->create([
            'password' => bcrypt('Secret123!'),
        ]);

    $response = $this->postJson('/api/tenant/{tenant}/auth/login', [
        'tenant_id' => $tenant->id,
        'email' => $user->email,
        'password' => 'Secret123!',
        'device_name' => 'Pest',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'data' => ['token', 'user'],
        ]);
});
```

---

## 8.4 Authorization Test

Setiap endpoint wajib memiliki test:

```text
Tanpa token → 401
Tanpa permission → 403
Dengan permission → berhasil
```

Contoh:

```php
it('prevents users without permission from creating products', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson(
        tenantApiUrl($user->tenant_id, '/products'),
        ProductFactoryData::valid()
    );

    $response->assertForbidden();
});
```

---

## 8.5 Validation Test

Test minimal:

- Required field.
- Invalid UUID.
- Foreign key tenant berbeda.
- Invalid enum.
- Nilai uang negatif.
- End date sebelum start date.
- File terlalu besar.
- MIME type tidak valid.

Gunakan dataset Pest agar ringkas.

```php
it('validates booking payload', function (
    string $field,
    mixed $value
) {
    // ...
})->with([
    'missing customer' => ['customer_id', null],
    'invalid quantity' => ['items.0.quantity', 0],
    'invalid end date' => ['end_at', '2026-07-01'],
]);
```

---

# 9. Tenant Isolation Test

Tenant isolation merupakan test wajib dan tidak boleh dilewati.

Package:

```text
stancl/tenancy
```

---

## 9.1 Domain/Subdomain Test

Landing page tenant menggunakan:

```text
InitializeTenancyByDomainOrSubdomain
```

Test minimal:

- Subdomain valid menginisialisasi tenant.
- Custom domain valid menginisialisasi tenant.
- Domain tidak dikenal menghasilkan tenant not found.
- Central domain tidak dianggap tenant.
- Data tenant lain tidak tampil.

---

## 9.2 Path Tenant Test

Mobile API menggunakan:

```text
InitializeTenancyByPath
```

Test minimal:

- Tenant path valid berhasil.
- Tenant path tidak valid menghasilkan 404.
- Token tenant A tidak dapat menggunakan path tenant B.
- Resource tenant B tidak ditemukan pada tenant A.
- Subscription tenant pada path diperiksa.

Contoh:

```php
it('prevents a tenant user from accessing another tenant path', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $userA = User::factory()->for($tenantA)->create();

    Sanctum::actingAs($userA);

    $response = $this->getJson(
        "/api/tenant/{$tenantB->id}/products"
    );

    $response
        ->assertForbidden()
        ->assertJsonPath(
            'error.code',
            'TENANT_ACCESS_DENIED'
        );
});
```

---

## 9.3 Request Header Test

Internal API menggunakan:

```text
InitializeTenancyByRequestData
```

Test minimal:

- `X-Tenant-ID` valid berhasil.
- Header kosong ditolak.
- Tenant tidak valid ditolak.
- Token internal tanpa tenant scope ditolak.
- Query parameter identification dinonaktifkan.
- Public token tidak dapat memakai internal endpoint.

---

## 9.4 Manual Initialization Test

Queue dan scheduler wajib diuji:

- Job membawa tenant ID.
- Tenancy diinisialisasi.
- Job membaca data tenant yang tepat.
- Tenancy diakhiri.
- Retry tetap menggunakan tenant yang sama.

---

# 10. Subscription Test

Subscription management menggunakan:

```text
laravelcm/laravel-subscriptions
```

Test harus berfokus pada integrasi package dengan business rule Sewantara.

---

## 10.1 Active Subscription

Test:

```text
Tenant active dapat melakukan operasi write.
```

---

## 10.2 Expired Subscription

Test:

```text
Tenant expired tidak dapat create, update, atau delete.
```

Read-only endpoint dapat tetap tersedia sesuai kebijakan.

---

## 10.3 Feature Entitlement

Contoh:

```text
Starter tidak dapat advanced report.
Business dapat advanced report.
Starter tidak dapat custom domain.
```

```php
it('blocks features unavailable on the current plan', function () {
    $tenant = Tenant::factory()
        ->withStarterSubscription()
        ->create();

    $user = User::factory()->for($tenant)->owner()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson(
        "/api/tenant/{$tenant->id}/reports/revenue"
    );

    $response
        ->assertForbidden()
        ->assertJsonPath(
            'error.code',
            'SUBSCRIPTION_FEATURE_NOT_AVAILABLE'
        );
});
```

---

## 10.4 Usage Limit

Test:

- Batas cabang.
- Batas user.
- Batas produk.
- Batas product unit.
- Batas booking bulanan.
- Unlimited plan.
- Concurrent creation saat limit tersisa satu.

Concurrency test penting agar dua request tidak sama-sama melewati limit.

---

## 10.5 Trial

Test:

- Trial aktif.
- Trial berakhir.
- Trial hanya satu kali.
- Trial berubah menjadi subscription aktif.
- Trial expired menjadi read-only atau blocked sesuai kebijakan.

---

# 11. Booking Feature Test

Test minimal:

## Create Booking

- Booking berhasil.
- Booking tanpa item ditolak.
- Customer tidak ditemukan.
- Customer tenant berbeda ditolak.
- Customer blacklist ditolak.
- Produk nonaktif ditolak.
- Product tenant berbeda ditolak.
- Harga snapshot tersimpan.
- Nomor booking unik.
- Idempotency key bekerja.

## Availability

- Unit tersedia.
- Unit maintenance tidak tersedia.
- Unit damaged tidak tersedia.
- Unit overlap tidak tersedia.
- Periode bersebelahan tetap tersedia.
- Quantity cukup.
- Quantity tidak cukup.
- Cabang berbeda dihitung terpisah.

## Confirmation

- Pending dapat dikonfirmasi.
- Draft tidak langsung ongoing.
- Inventory dialokasikan.
- Pembayaran minimum diperiksa.
- Reservation gagal menyebabkan transaction rollback.

## Cancellation

- Reservation dilepas.
- Status history dibuat.
- Alasan tersimpan.
- Booking completed tidak dapat dibatalkan.

## Return

- Unit dikembalikan.
- Inspection dibuat.
- Late fee dihitung.
- Damage charge dibuat.
- Deposit diselesaikan.
- Status inventory benar.

---

# 12. Payment Feature Test

Test minimal:

- Membuat pembayaran manual.
- Memverifikasi pembayaran.
- Partial payment.
- Full payment.
- Paid amount diperbarui.
- Remaining amount diperbarui.
- Payment status benar.
- Payment paid tidak dapat dihapus.
- Refund valid.
- Refund melebihi pembayaran ditolak.
- Currency salah ditolak.
- Payment tenant berbeda ditolak.

---

## 12.1 Webhook Test

Test webhook payment gateway:

- Signature valid.
- Signature invalid.
- Transaction ditemukan.
- Transaction tidak ditemukan.
- Duplicate webhook.
- Payload status berubah.
- Nominal tidak sesuai.
- Gateway timeout.
- Webhook dikirim tidak berurutan.
- Callback paid setelah expired.
- Idempotency.

Gunakan payload fixture lokal.

Jangan memanggil Midtrans atau gateway nyata pada automated test.

---

# 13. Integration Test

## 13.1 Tujuan

Integration test menguji interaksi nyata antara beberapa komponen.

Contoh:

- Repository dengan PostgreSQL.
- Laravel dengan Redis.
- Storage adapter dengan MinIO test container.
- Subscription wrapper dengan package.
- Tenancy middleware dengan domain table.
- Queue producer dan worker.
- Payment adapter menggunakan sandbox atau mock server.

---

## 13.2 Repository Test

Repository contract harus memiliki test yang sama untuk:

- Fake repository.
- Eloquent repository.

Contoh yang diuji:

```text
find
save
pagination
locking
tenant filter
aggregate hydration
```

---

## 13.3 PostgreSQL-Specific Test

Karena production menggunakan PostgreSQL, test penting harus berjalan pada PostgreSQL, bukan hanya SQLite.

Fitur yang wajib diuji pada PostgreSQL:

- UUID.
- JSONB.
- Partial index.
- Exclusion constraint.
- `tstzrange`.
- Row-level locking.
- Concurrent transaction.
- Trigram search.
- Check constraint.

SQLite tidak cukup untuk menguji pencegahan overlap menggunakan exclusion constraint.

---

## 13.4 Tenancy Integration Test

Uji integrasi `stancl/tenancy`:

- Domain record tanpa titik dianggap subdomain.
- Domain dengan titik dianggap hostname.
- Path resolver menggunakan tenant identifier.
- Request data resolver membaca `X-Tenant-ID`.
- `onFail` mengembalikan response sesuai client.
- Tenant bootstrappers berjalan.
- Cache/storage tenant terisolasi jika fitur tersebut diaktifkan.

---

## 13.5 Subscription Integration Test

Uji integrasi `laravelcm/laravel-subscriptions`:

- Tenant dapat subscribe ke plan.
- Subscription tersimpan.
- Feature plan dapat dibaca.
- Feature usage diperbarui.
- Subscription expired terdeteksi.
- Upgrade dan downgrade.
- Wrapper middleware Sewantara membaca status package dengan benar.

---

## 13.6 External Service Contract Test

Adapter eksternal harus memiliki contract test.

Contoh Payment Gateway Contract:

```text
createCharge
getStatus
refund
verifyWebhook
```

Semua implementation harus memenuhi contract:

```text
MidtransPaymentGateway
XenditPaymentGateway
ManualPaymentGateway
```

---

# 14. API Test

## 14.1 Tujuan

API test memastikan kontrak antara backend dan client tetap konsisten.

API test memeriksa:

- Endpoint.
- HTTP method.
- Authentication.
- Header.
- Request schema.
- Response schema.
- HTTP status.
- Error code.
- Pagination.
- Filtering.
- Sorting.
- Versioning.

---

## 14.2 Response Contract

Success:

```json
{
    "success": true,
    "message": "Data berhasil diproses.",
    "data": {}
}
```

Error:

```json
{
    "success": false,
    "error": {
        "code": "ERROR_CODE",
        "message": "Pesan error.",
        "details": {}
    }
}
```

Setiap endpoint harus memiliki assertion terhadap struktur response.

---

## 14.3 Status Code Test

Minimal:

| Kondisi               | Status |
| --------------------- | -----: |
| Berhasil dibaca       |    200 |
| Berhasil dibuat       |    201 |
| Proses async diterima |    202 |
| Berhasil tanpa body   |    204 |
| Belum login           |    401 |
| Tidak berhak          |    403 |
| Tidak ditemukan       |    404 |
| Konflik state         |    409 |
| Validation error      |    422 |
| Rate limit            |    429 |

---

## 14.4 Pagination Test

Test:

- Default page.
- Default per page.
- Maximum per page.
- Invalid page.
- Metadata total.
- Next link.
- Tenant data tidak tercampur.

---

## 14.5 Filter and Sort Test

Test:

- Filter status.
- Filter branch.
- Filter date.
- Filter category.
- Search.
- Ascending sort.
- Descending sort.
- Invalid sort field ditolak atau diabaikan sesuai kontrak.

---

## 14.6 API Versioning Test

Endpoint:

```text
/api/tenant/{tenant}
```

Test memastikan:

- Route v1 tersedia.
- Breaking change tidak dimasukkan diam-diam.
- Deprecated field tetap tersedia selama periode transisi.
- Unknown version menghasilkan 404.

---

## 14.7 OpenAPI Contract Test

Jika menggunakan OpenAPI, lakukan validasi:

- Request sesuai schema.
- Response sesuai schema.
- Enum sesuai dokumentasi.
- Required field konsisten.
- Error response terdokumentasi.

OpenAPI dapat digunakan untuk menghasilkan:

- Client SDK.
- Mock server.
- Contract test.
- API documentation.

---

# 15. Frontend Test

## 15.1 Unit Test

Uji:

- Utility function.
- Composable.
- Store.
- Formatter.
- Form schema.
- Permission helper.

---

## 15.2 Component Test

Uji:

- Rendering.
- Props.
- Event.
- Loading state.
- Empty state.
- Error state.
- Permission-based visibility.
- Subscription feature visibility.

UI hiding bukan pengganti authorization backend.

---

## 15.3 E2E Test

Gunakan Playwright untuk skenario:

```text
Login
Create customer
Create product
Create booking
Record payment
Check-out
Return
Complete booking
```

Tambahkan:

- Tenant domain flow.
- Tenant path API flow.
- Expired subscription flow.
- Permission denied flow.

---

# 16. Flutter Test

## 16.1 Unit Test

Uji:

- Repository abstraction.
- Use case.
- Price formatter.
- Form validator.
- State mapper.

---

## 16.2 BLoC Test

Gunakan `bloc_test`.

```dart
blocTest<BookingBloc, BookingState>(
  'emits loading and loaded when bookings succeed',
  build: () => BookingBloc(repository: repository),
  act: (bloc) => bloc.add(const BookingRequested()),
  expect: () => [
    const BookingState.loading(),
    BookingState.loaded(bookings),
  ],
);
```

---

## 16.3 Widget Test

Uji:

- Booking form.
- Product card.
- Loading indicator.
- Error state.
- Empty state.
- Button permission.
- Subscription limitation message.

---

## 16.4 Integration Test

Uji alur mobile:

- Login.
- Menyimpan tenant ID.
- Memanggil `/api/tenant/{tenant}`.
- Token refresh atau re-login.
- Create booking.
- Offline error.
- Tenant access denied.
- Subscription expired.

---

# 17. Performance Test

## 17.1 Tujuan

Performance test mengukur:

- Response time.
- Throughput.
- Error rate.
- Concurrent users.
- Database load.
- Queue delay.
- Memory.
- CPU.
- Availability check speed.

---

## 17.2 Jenis Performance Test

### Load Test

Menguji beban normal yang diharapkan.

### Stress Test

Meningkatkan beban sampai sistem gagal atau menurun drastis.

### Spike Test

Menguji kenaikan trafik secara tiba-tiba.

### Soak Test

Menguji beban stabil dalam waktu lama.

### Scalability Test

Menguji pengaruh penambahan resource.

---

## 17.3 Target Awal

Target MVP:

| Endpoint           |         p95 |
| ------------------ | ----------: |
| Login              | < 1.5 detik |
| Product list       |   < 1 detik |
| Booking list       | < 1.5 detik |
| Availability check |   < 2 detik |
| Create booking     |   < 2 detik |
| Dashboard          |   < 3 detik |

Target error rate:

```text
< 1%
```

Target availability check:

```text
Tidak menghasilkan double booking.
```

---

## 17.4 k6 Example

```javascript
import http from "k6/http";
import { check, sleep } from "k6";

export const options = {
    stages: [
        { duration: "1m", target: 20 },
        { duration: "3m", target: 100 },
        { duration: "1m", target: 0 },
    ],
    thresholds: {
        http_req_failed: ["rate<0.01"],
        http_req_duration: ["p(95)<2000"],
    },
};

export default function () {
    const tenantId = __ENV.TENANT_ID;
    const token = __ENV.ACCESS_TOKEN;

    const response = http.get(`${__ENV.BASE_URL}/api/tenant/${tenantId}/products`, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
        },
    });

    check(response, {
        "status is 200": (result) => result.status === 200,
    });

    sleep(1);
}
```

---

## 17.5 Availability Concurrency Test

Performance test wajib mencoba beberapa request booking untuk unit yang sama pada periode yang sama.

Expected:

```text
Hanya satu request berhasil mengalokasikan unit.
Request lain menerima conflict.
Tidak ada dua allocation aktif yang overlap.
```

HTTP conflict:

```text
409 Conflict
```

Error code:

```text
BOOKING_UNIT_NOT_AVAILABLE
```

---

## 17.6 Subscription Limit Concurrency

Uji dua request bersamaan ketika limit tersisa satu.

Contoh:

```text
Plan max branches: 5
Current branches: 4
Dua request create branch bersamaan
```

Expected:

```text
Satu berhasil.
Satu ditolak.
Total cabang tetap 5.
```

---

# 18. Security Test

Meskipun memiliki dokumen security terpisah, testing wajib mencakup:

- Authentication bypass.
- Broken access control.
- Tenant path manipulation.
- IDOR.
- SQL injection.
- XSS.
- File upload.
- Mass assignment.
- Rate limit.
- Webhook signature.
- Replay attack.
- Sensitive response data.
- Audit log.
- Subscription bypass.

---

# 19. Fake, Mock, Stub, dan Spy

## Fake

Gunakan untuk implementasi ringan.

Contoh:

```text
FakeBookingRepository
Storage::fake()
Queue::fake()
Notification::fake()
```

## Mock

Gunakan untuk memverifikasi interaksi tertentu.

Jangan mock seluruh aplikasi.

## Stub

Gunakan untuk memberikan hasil terkontrol.

## Spy

Gunakan untuk memastikan method atau event dipanggil.

Prinsip:

```text
Gunakan real object jika cepat dan stabil.
Gunakan fake pada infrastructure boundary.
Mock hanya interaction yang penting.
```

---

# 20. Time Testing

Jangan bergantung pada waktu nyata.

Gunakan:

```php
use Illuminate\Support\Carbon;

Carbon::setTestNow('2026-07-28 10:00:00');
```

Atau:

```php
$this->travelTo(
    Carbon::parse('2026-07-28 10:00:00')
);
```

Test:

- Booking expiry.
- Trial expiry.
- Subscription expiry.
- Late fee.
- Invoice overdue.
- Notification reminder.

Reset waktu setelah test jika framework tidak melakukannya otomatis.

---

# 21. Queue Testing

Test minimal:

- Job didispatch.
- Queue yang tepat.
- Tenant ID ada pada payload.
- Job idempotent.
- Retry tidak menggandakan data.
- Failure tercatat.
- Timeout ditentukan.
- Job tidak membawa Eloquent model besar.

Contoh:

```php
Queue::fake();

$action->execute($data);

Queue::assertPushed(
    SendBookingConfirmationJob::class,
    fn ($job) => $job->tenantId === $tenant->id
);
```

---

# 22. Event Testing

Domain event wajib diuji pada proses penting.

```php
Event::fake([
    BookingConfirmed::class,
]);

$action->execute($data);

Event::assertDispatched(
    BookingConfirmed::class
);
```

Jangan hanya menguji event dipanggil. Tetap uji state database dan business result.

---

# 23. Audit Log Testing

Test minimal:

- Aksi penting membuat audit log.
- Tenant ID benar.
- User ID benar.
- Action benar.
- Old values dan new values benar.
- Data sensitif dimasking.
- Audit log tidak dapat diedit oleh tenant user.

Aksi yang wajib diuji:

- Login.
- Role assignment.
- Booking cancellation.
- Payment verification.
- Refund.
- Deposit deduction.
- Tenant suspension.
- Subscription override.

---

# 24. Test Data Security

Dilarang menggunakan data customer production pada test.

Gunakan data sintetis.

Jangan memasukkan:

- KTP nyata.
- Nomor rekening nyata.
- Token nyata.
- API key nyata.
- Email customer nyata.
- Payment gateway secret.

---

# 25. CI Pipeline

Setiap pull request menjalankan:

```text
Composer install
↓
Laravel Pint
↓
PHPStan/Larastan
↓
Unit Test
↓
Feature Test
↓
Integration Test
↓
Frontend Lint dan Test
↓
Flutter Analyze dan Test
↓
Build validation
```

Performance test tidak wajib pada setiap commit, tetapi dijalankan:

- Sebelum release besar.
- Setelah perubahan query kritikal.
- Setelah perubahan availability.
- Setelah perubahan deployment.
- Secara terjadwal pada staging.

---

# 26. Test Coverage

Coverage adalah indikator, bukan tujuan utama.

Target awal:

| Area                     | Target |
| ------------------------ | -----: |
| Domain                   |  ≥ 90% |
| Application Actions      |  ≥ 85% |
| Critical booking/payment |  ≥ 90% |
| Overall backend          |  ≥ 80% |
| Frontend utilities/store |  ≥ 70% |
| Flutter domain/BLoC      |  ≥ 80% |

Tidak diperbolehkan menambah test tanpa assertion bermakna hanya untuk mengejar coverage.

---

# 27. Critical Test Matrix

| Area         | Skenario wajib                         |
| ------------ | -------------------------------------- |
| Tenant       | Cross-tenant access ditolak            |
| Subscription | Feature dan limit tidak dapat dilewati |
| Booking      | Overlap dan race condition dicegah     |
| Inventory    | Stok tidak menjadi negatif             |
| Payment      | Webhook idempotent                     |
| Deposit      | Refund tidak melebihi saldo            |
| Permission   | User tanpa hak ditolak                 |
| Audit        | Perubahan kritikal tercatat            |
| Queue        | Tenant context benar                   |
| Report       | Deposit tidak dihitung sebagai revenue |

---

# 28. Test Naming Convention

Backend:

```text
tests/
├── Unit/
│   ├── Booking/
│   ├── Inventory/
│   ├── Payment/
│   └── Subscription/
├── Feature/
│   ├── Api/
│   ├── Auth/
│   ├── Tenancy/
│   └── Subscription/
├── Integration/
│   ├── Persistence/
│   ├── Tenancy/
│   ├── Subscription/
│   └── Gateway/
└── Performance/
```

Contoh file:

```text
CreateBookingTest.php
BookingAvailabilityTest.php
TenantPathIsolationTest.php
PaymentWebhookTest.php
SubscriptionFeatureTest.php
```

---

# 29. Definition of Done Testing

Sebuah fitur dianggap selesai jika:

- Unit test business rule tersedia.
- Feature test endpoint tersedia.
- Authorization diuji.
- Tenant isolation diuji.
- Subscription diuji jika terkait.
- Validation diuji.
- Error code diuji.
- Happy path dan failure path diuji.
- Event atau queue diuji jika digunakan.
- Audit log diuji untuk aksi kritikal.
- Regression test tersedia untuk bug.
- Static analysis lulus.
- Seluruh test CI lulus.
- Performance test dilakukan jika fitur kritikal.
- Dokumentasi test diperbarui.

---

# 30. Release Testing Checklist

```text
☐ Unit test lulus
☐ Feature test lulus
☐ Integration test lulus
☐ API contract test lulus
☐ Tenant isolation test lulus
☐ Subscription test lulus
☐ Payment webhook test lulus
☐ Booking concurrency test lulus
☐ Static analysis lulus
☐ Security test lulus
☐ Migration diuji pada database kosong
☐ Migration rollback diuji
☐ Backup dan restore staging diuji
☐ Performance threshold terpenuhi
☐ Smoke test staging lulus
```

---

# 31. Smoke Test Production

Setelah deployment, jalankan smoke test yang aman:

- Health endpoint.
- Login test account.
- Tenant resolution.
- Product list.
- Booking list.
- Queue status.
- Cache.
- Database connection.
- Storage.
- Payment webhook health.
- Monitoring dan error tracking.

Smoke test tidak boleh membuat transaksi finansial nyata.

---

# 32. Kesimpulan

Strategi pengujian Sewantara mencakup:

```text
Unit Test
Feature Test
Integration Test
API Test
Performance Test
Security Test
Tenant Isolation Test
Subscription Test
Concurrency Test
```

Prioritas utama:

1. Business rule diuji pada unit test.
2. Endpoint diuji melalui feature dan API test.
3. PostgreSQL digunakan untuk integrasi kritikal.
4. Tenant isolation wajib pada setiap modul.
5. `stancl/tenancy` diuji pada domain, path, request data, dan manual initialization.
6. `laravelcm/laravel-subscriptions` diuji melalui wrapper dan business rule Sewantara.
7. Booking overlap diuji termasuk race condition.
8. Webhook payment harus idempotent.
9. Feature dan usage limit tidak boleh dapat dilewati secara concurrent.
10. Seluruh bug wajib memiliki regression test.
