Architecture
Sewantara — Universal Rental Management SaaS
Versi: 1.0
Status: Draft
Architecture Style: Modular Monolith
Pendekatan: Clean Architecture + DDD Lite
Backend utama: Laravel 12
Database: PostgreSQL
Komunikasi internal: Synchronous Service Call + Domain Event
Tenant Management: stancl/tenancy
Subscription Management: laravelcm/laravel-subscriptions

1. Tujuan Dokumen
   Dokumen ini menjelaskan arsitektur pengembangan aplikasi Sewantara.

Tujuan utama arsitektur adalah:

Menjaga kode tetap modular.

Memisahkan business logic dari framework.

Mempermudah pengujian.

Menghindari controller dan model yang terlalu besar.

Mempermudah pengembangan oleh banyak developer.

Menjaga konsistensi antar-modul.

Mendukung pertumbuhan aplikasi tanpa langsung menggunakan microservices.

Mempermudah ekstraksi modul menjadi service terpisah ketika diperlukan.

Menjamin aturan bisnis rental tidak tersebar di banyak tempat.

Dokumen ini menjadi acuan wajib bagi:

Backend developer

Frontend developer

Mobile developer

QA engineer

DevOps engineer

Technical lead

AI coding assistant

2. Architecture Principles
   Sewantara menggunakan prinsip berikut:

2.1 Business Logic First
Business logic tidak boleh bergantung langsung pada:

Controller

HTTP request

Eloquent model

Queue

Payment gateway

Storage provider

Framework helper

Business logic harus dapat diuji tanpa menjalankan HTTP server.

2.2 Dependency Rule
Arah dependency bergerak dari luar ke dalam.

Presentation
↓
Application
↓
Domain
↑
Infrastructure
Domain tidak boleh bergantung pada:

Laravel

Database

Redis

HTTP

Midtrans

Firebase

Cloudflare R2

Infrastructure boleh bergantung pada domain melalui interface.

2.3 High Cohesion
Kode yang berkaitan dengan satu domain ditempatkan dalam modul yang sama.

Contoh:

Booking
├── Create Booking
├── Confirm Booking
├── Cancel Booking
├── Availability
├── Allocation
└── Booking Status
Logic booking tidak boleh tersebar di:

Controllers
Models
Helpers
Observers
Jobs
Middleware
2.4 Low Coupling
Modul tidak boleh saling mengakses tabel atau model secara sembarangan.

Contoh:

Payment tidak boleh langsung mengubah Booking Model.
Payment harus berkomunikasi melalui:

Application service

Contract

Command

Domain event

2.5 Explicit Business Process
Proses penting dibuat eksplisit dalam bentuk Action atau Use Case.

Contoh:

CreateBookingAction
ConfirmBookingAction
AllocateBookingUnitAction
CheckOutBookingAction
ReturnBookingAction
RefundDepositAction
Hindari business process besar di dalam controller.

2.6 Transactional Consistency
Proses yang mengubah beberapa tabel harus menggunakan transaction.

Contoh:

Create booking
Create booking items
Allocate inventory
Create invoice
Create status history
Semua langkah tersebut dianggap satu transaksi bisnis.

2.7 Eventual Extensibility
Sistem dirancang sebagai modular monolith, tetapi batas modul dibuat cukup jelas agar dapat diekstrak menjadi microservice pada masa depan.

Contoh kandidat ekstraksi:

Notification

Payment

Reporting

Subscription

Search

Media processing

3. Modular Monolith
   3.1 Definisi
   Modular Monolith adalah aplikasi yang dijalankan sebagai satu deployment, tetapi kode dibagi menjadi modul bisnis yang terisolasi.

Sewantara tidak langsung menggunakan microservices karena:

MVP perlu dikembangkan cepat.

Tim awal kemungkinan masih kecil.

Transaksi booking membutuhkan konsistensi tinggi.

Operasional microservices lebih kompleks.

Observability dan deployment lebih mahal.

Banyak modul masih saling berubah selama tahap awal.

3.2 Keuntungan
Development lebih cepat.

Debugging lebih mudah.

Deployment lebih sederhana.

Database transaction lebih mudah.

Tidak memerlukan distributed transaction.

Biaya infrastruktur lebih rendah.

Tetap dapat menjaga modularitas.

Mudah diekstrak saat kebutuhan meningkat.

3.3 Batas Modul
Modul utama Sewantara:

Auth
Tenant
Subscription
Organization
Customer
Catalog
Inventory
Booking
Pricing
Fulfillment
Payment
Deposit
Maintenance
Notification
Reporting
Audit
Settings
Setiap modul memiliki:

Domain model

Use case

Repository contract

Repository implementation

DTO

Event

Exception

Policy

Test

3.4 Aturan Antar-Modul
Satu modul tidak boleh:

Mengakses repository internal modul lain secara langsung.

Mengubah tabel modul lain tanpa use case.

Menggunakan model Eloquent modul lain untuk business logic.

Mengimpor class internal yang tidak dipublikasikan.

Komunikasi antar-modul dilakukan menggunakan:

Public application service

Interface atau contract

Domain event

Integration event

Read model

4. Clean Architecture
   4.1 Layer Architecture
   Setiap modul dibagi menjadi empat layer:

Presentation
Application
Domain
Infrastructure
4.2 Presentation Layer
Presentation menangani komunikasi dari luar aplikasi.

Komponen:

HTTP Controller

Form Request

API Resource

Console Command

Queue Consumer

Webhook Controller

Tanggung jawab:

Menerima input.

Melakukan validasi format.

Mengubah request menjadi DTO.

Memanggil Action atau Use Case.

Mengubah hasil menjadi response.

Menentukan HTTP status code.

Presentation tidak boleh:

Menulis query database.

Menghitung harga.

Mengubah status booking langsung.

Menjalankan business rule.

Mengakses payment gateway langsung.

Contoh:

final class CreateBookingController
{
public function \_\_invoke(
CreateBookingRequest $request,
        CreateBookingAction $action
    ): BookingResource {
        $booking = $action->execute(
            CreateBookingData::fromRequest($request)
);

        return new BookingResource($booking);
    }

}
Controller harus tipis.

4.3 Application Layer
Application mengatur alur sebuah use case.

Komponen:

Action

Command

Query

Handler

DTO

Application Service

Transaction manager

Authorization orchestration

Tanggung jawab:

Mengatur langkah proses.

Memanggil domain service.

Memanggil repository.

Menjalankan transaction.

Menerbitkan event.

Mengorkestrasi beberapa aggregate.

Contoh:

final class CreateBookingAction
{
public function \_\_construct(
private BookingRepository $bookings,
private ProductAvailabilityService $availability,
private PricingService $pricing,
private UnitOfWork $unitOfWork,
) {
}

    public function execute(CreateBookingData $data): Booking
    {
        return $this->unitOfWork->transaction(function () use ($data) {
            $this->availability->ensureAvailable(
                tenantId: $data->tenantId,
                items: $data->items,
                period: $data->period,
            );

            $booking = Booking::createDraft(
                customerId: $data->customerId,
                period: $data->period,
            );

            foreach ($data->items as $item) {
                $booking->addItem(
                    $this->pricing->calculate($item, $data->period)
                );
            }

            $this->bookings->save($booking);

            return $booking;
        });
    }

}
4.4 Domain Layer
Domain adalah inti aplikasi.

Komponen:

Entity

Aggregate Root

Value Object

Domain Service

Domain Event

Specification

Domain Exception

Repository Interface

Enum atau State

Domain bertanggung jawab terhadap:

Aturan bisnis.

Validitas state.

Transisi status.

Perhitungan inti.

Invariant.

Kebijakan domain.

Domain tidak boleh bergantung pada Laravel.

Contoh:

final class Booking
{
private BookingStatus $status;

    public function confirm(): void
    {
        if (!$this->status->canTransitionTo(BookingStatus::Confirmed)) {
            throw InvalidBookingTransition::from(
                $this->status,
                BookingStatus::Confirmed
            );
        }

        if ($this->items->isEmpty()) {
            throw BookingMustHaveItems::create();
        }

        $this->status = BookingStatus::Confirmed;

        $this->recordEvent(
            new BookingConfirmed($this->id)
        );
    }

}
4.5 Infrastructure Layer
Infrastructure menangani detail teknis.

Komponen:

Eloquent repository

Redis cache

Payment gateway

Storage

Email provider

Firebase

Queue implementation

Search engine

External API client

Contoh:

final class EloquentBookingRepository implements BookingRepository
{
public function find(BookingId $id): Booking
    {
        $model = BookingModel::query()
            ->with(['items', 'allocations'])
            ->findOrFail($id->value);

        return BookingMapper::toDomain($model);
    }

    public function save(Booking $booking): void
    {
        BookingMapper::persist($booking);
    }

}
Infrastructure mengimplementasikan contract dari domain atau application.

5. DDD Lite
   5.1 Definisi
   DDD Lite berarti Sewantara menggunakan konsep penting Domain-Driven Design tanpa menerapkan seluruh kompleksitas tactical dan strategic DDD.

Konsep yang digunakan:

Bounded context

Entity

Aggregate root

Value object

Domain service

Domain event

Repository contract

Domain exception

Ubiquitous language

Konsep yang tidak wajib digunakan secara berlebihan:

Event sourcing

Full aggregate persistence

Saga untuk seluruh proses

Separate database per bounded context

Distributed bounded context

5.2 Bounded Context
Bounded context utama:

Tenant Context
Mengelola:

Tenant

Domain

Subscription

Settings

Inventory Context
Mengelola:

Category

Product

Product Unit

Stock

Movement

Availability

Booking Context
Mengelola:

Booking

Booking Item

Allocation

Booking Status

Booking Timeline

Payment Context
Mengelola:

Payment

Invoice

Refund

Gateway transaction

Fulfillment Context
Mengelola:

Check-out

Check-in

Delivery

Inspection

Checklist

Maintenance Context
Mengelola:

Service

Repair

Cleaning

Calibration

5.3 Ubiquitous Language
Tim harus menggunakan istilah yang konsisten.

Contoh:

Istilah Makna
Tenant Bisnis rental pengguna SaaS
Product Jenis atau model barang
Product Unit Unit fisik individual
Quantity Stock Stok barang berdasarkan jumlah
Booking Transaksi rental
Allocation Pemesanan unit untuk periode tertentu
Check-out Barang diserahkan kepada customer
Check-in Barang dikembalikan
Deposit Dana jaminan
Charge Biaya tambahan atau denda
Settlement Penyelesaian pembayaran atau deposit
Hindari penggunaan istilah berbeda untuk hal yang sama.

5.4 Entity
Entity memiliki identitas dan siklus hidup.

Contoh:

Booking

Product

Product Unit

Customer

Payment

Tenant

5.5 Value Object
Value object tidak memiliki identitas dan bersifat immutable.

Contoh:

Money
RentalPeriod
BookingNumber
ProductUnitCode
EmailAddress
PhoneNumber
TenantId
BookingId
Contoh:

final readonly class RentalPeriod
{
public function \_\_construct(
public DateTimeImmutable $startAt,
        public DateTimeImmutable $endAt,
    ) {
        if ($startAt >= $endAt) {
throw InvalidRentalPeriod::create();
}
}

    public function overlaps(self $other): bool
    {
        return $this->startAt < $other->endAt
            && $this->endAt > $other->startAt;
    }

}
5.6 Aggregate Root
Aggregate root menjaga konsistensi sekumpulan entity.

Contoh aggregate:

Booking Aggregate
Booking
├── Booking Item
├── Allocation
└── Status History
Perubahan booking harus melalui Booking Aggregate atau Application Service.

Product Aggregate
Product
├── Product Price
├── Product Image
└── Product Attribute
Deposit Aggregate
Deposit
└── Deposit Transactions
5.7 Domain Service
Domain service digunakan apabila sebuah logic:

Tidak cocok menjadi method entity.

Melibatkan lebih dari satu entity.

Merupakan konsep domain penting.

Contoh:

BookingAvailabilityService
PricingService
DepositSettlementService
LateFeeCalculator
UnitAllocationService 6. Repository Pattern
6.1 Tujuan
Repository memisahkan domain atau application layer dari database.

Repository bertugas:

Mengambil aggregate.

Menyimpan aggregate.

Menjalankan query domain.

Menyembunyikan detail ORM.

Repository bukan tempat seluruh business logic.

6.2 Contract
interface BookingRepository
{
public function find(BookingId $id): Booking;

    public function save(Booking $booking): void;

    public function nextBookingNumber(
        TenantId $tenantId,
        DateTimeImmutable $date
    ): BookingNumber;

}
6.3 Implementation
final class EloquentBookingRepository implements BookingRepository
{
public function find(BookingId $id): Booking
{
// Eloquent implementation
}

    public function save(Booking $booking): void
    {
        // Persistence implementation
    }

}
6.4 Aturan Repository
Repository tidak boleh:

Mengembalikan HTTP response.

Menerima Form Request.

Mengirim notifikasi.

Menjalankan payment gateway.

Menentukan status booking.

Mengandung business workflow besar.

Repository boleh:

Query.

Persistence.

Locking.

Pagination.

Projection.

Aggregate hydration.

6.5 Repository Tidak Wajib untuk Semua Model
Repository digunakan untuk:

Aggregate penting.

Query kompleks.

Domain yang membutuhkan isolasi persistence.

Proses yang memerlukan locking.

Modul yang kemungkinan diekstrak.

Untuk tabel sederhana seperti lookup statis, Eloquent query langsung melalui Query Object masih diperbolehkan.

7. Action Pattern
   7.1 Tujuan
   Action merepresentasikan satu use case bisnis.

Contoh:

CreateBookingAction
ConfirmBookingAction
CancelBookingAction
AllocateUnitAction
CheckOutBookingAction
ReturnBookingAction
RecordPaymentAction
RefundDepositAction
7.2 Bentuk Action
final class ConfirmBookingAction
{
public function execute(
ConfirmBookingData $data
): Booking {
// Orchestration
}
}
Action sebaiknya:

Memiliki satu public method.

Memiliki satu tanggung jawab.

Mudah diuji.

Tidak bergantung pada HTTP.

Menggunakan transaction bila diperlukan.

7.3 Action vs Service
Gunakan Action untuk proses spesifik:

CreateBookingAction
CancelBookingAction
Gunakan Service untuk kapabilitas yang digunakan oleh banyak action:

PricingService
AvailabilityService
InvoiceNumberGenerator 8. DTO Pattern
8.1 Tujuan
DTO memindahkan data antar-layer secara eksplisit.

DTO mencegah:

Mengirim Form Request ke application layer.

Menggunakan array tanpa struktur.

Parameter method terlalu banyak.

Ketergantungan terhadap framework.

8.2 Contoh DTO
final readonly class CreateBookingData
{
/\*\*
_ @param list<CreateBookingItemData> $items
_/
public function \_\_construct(
public string $tenantId,
public string $branchId,
public string $customerId,
public DateTimeImmutable $startAt,
public DateTimeImmutable $endAt,
public array $items,
public string $fulfillmentType,
public ?string $notes,
) {
}
}
8.3 DTO Rules
DTO harus:

Immutable.

Typed.

Tidak menjalankan query.

Tidak memiliki business workflow.

Tidak mengirim notification.

Tidak mengakses container.

DTO boleh memiliki:

Factory dari request.

Factory dari array.

Normalisasi nilai sederhana.

Serialization.

9. Strategy Pattern
   9.1 Tujuan
   Strategy digunakan ketika terdapat beberapa algoritma yang dapat dipilih berdasarkan konfigurasi atau tipe.

9.2 Pricing Strategy
interface PricingStrategy
{
public function calculate(
ProductPricingContext $context
): Money;
}
Implementasi:

HourlyPricingStrategy
DailyPricingStrategy
WeeklyPricingStrategy
MonthlyPricingStrategy
EventPricingStrategy
CustomPricingStrategy
Resolver:

final class PricingStrategyResolver
{
public function resolve(PricingType $type): PricingStrategy
    {
        return match ($type) {
PricingType::Hourly => app(HourlyPricingStrategy::class),
PricingType::Daily => app(DailyPricingStrategy::class),
PricingType::Weekly => app(WeeklyPricingStrategy::class),
PricingType::Monthly => app(MonthlyPricingStrategy::class),
PricingType::Event => app(EventPricingStrategy::class),
PricingType::Custom => app(CustomPricingStrategy::class),
};
}
}
9.3 Payment Gateway Strategy
MidtransPaymentGateway
XenditPaymentGateway
DuitkuPaymentGateway
ManualPaymentGateway
Semua mengimplementasikan:

interface PaymentGateway
{
public function createCharge(PaymentRequest $request): GatewayCharge;

    public function refund(RefundRequest $request): GatewayRefund;

    public function verifyWebhook(array $payload, array $headers): bool;

}
9.4 Notification Strategy
EmailNotificationChannel
WhatsAppNotificationChannel
PushNotificationChannel
InAppNotificationChannel 10. State Pattern
10.1 Tujuan
State Pattern digunakan untuk menjaga transisi status agar tidak tersebar pada banyak if dan switch.

10.2 Booking State
Status:

Draft
Pending
Confirmed
Preparing
Ready
Ongoing
Returned
Completed
Cancelled
Rejected
Expired
Transisi utama:

Draft
↓
Pending
↓
Confirmed
↓
Preparing
↓
Ready
↓
Ongoing
↓
Returned
↓
Completed
Transisi alternatif:

Draft → Cancelled
Pending → Cancelled
Pending → Rejected
Pending → Expired
Confirmed → Cancelled
10.3 Contoh State Contract
interface BookingState
{
public function confirm(Booking $booking): void;

    public function cancel(Booking $booking): void;

    public function prepare(Booking $booking): void;

    public function checkOut(Booking $booking): void;

    public function return(Booking $booking): void;

    public function complete(Booking $booking): void;

}
10.4 Pendekatan Sederhana
Untuk MVP, State Pattern dapat diterapkan menggunakan:

Enum.

Transition map.

Domain method.

Exception.

Contoh:

enum BookingStatus: string
{
case Draft = 'draft';
case Pending = 'pending';
case Confirmed = 'confirmed';
case Preparing = 'preparing';
case Ready = 'ready';
case Ongoing = 'ongoing';
case Returned = 'returned';
case Completed = 'completed';
case Cancelled = 'cancelled';
case Rejected = 'rejected';
case Expired = 'expired';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Draft => [
                self::Pending,
                self::Cancelled,
            ],
            self::Pending => [
                self::Confirmed,
                self::Cancelled,
                self::Rejected,
                self::Expired,
            ],
            self::Confirmed => [
                self::Preparing,
                self::Cancelled,
            ],
            self::Preparing => [
                self::Ready,
            ],
            self::Ready => [
                self::Ongoing,
            ],
            self::Ongoing => [
                self::Returned,
            ],
            self::Returned => [
                self::Completed,
            ],
            default => [],
        }, true);
    }

}
Pendekatan full class-per-state baru digunakan jika behavior status semakin kompleks.

11. CQRS Lite
    11.1 Definisi
    CQRS Lite memisahkan proses perubahan data dan pembacaan data tanpa menggunakan dua database berbeda.

Command → Mengubah state
Query → Membaca data
11.2 Command
Command digunakan untuk operasi write.

Contoh:

CreateBookingCommand
ConfirmBookingCommand
CancelBookingCommand
CheckOutBookingCommand
RecordPaymentCommand
Command diproses oleh:

CreateBookingHandler
ConfirmBookingHandler
Pada implementasi Laravel, Action dapat berperan sebagai Command Handler.

11.3 Query
Query digunakan untuk membaca data.

Contoh:

GetBookingDetailQuery
ListBookingsQuery
GetAvailabilityQuery
GetDashboardSummaryQuery
GetRevenueReportQuery
Query tidak boleh mengubah state.

11.4 Read Model
Read model boleh dibuat khusus untuk kebutuhan tampilan.

Contoh:

BookingCalendarView
DashboardSummary
ProductAvailabilityView
RevenueReportView
Read model dapat menggunakan:

Query builder.

Database view.

Materialized view.

Cached projection.

Denormalized query.

11.5 CQRS Lite Rules
Tidak menggunakan database write dan read terpisah pada MVP.

Tidak menggunakan event sourcing.

Tidak membuat command class untuk CRUD sederhana.

Digunakan pada proses bisnis penting.

Query kompleks dipisahkan dari aggregate persistence.

Read model tidak dipakai untuk menjalankan business rule.

12. Event-Driven Architecture
    12.1 Tujuan
    Event digunakan untuk memisahkan proses utama dari side effect.

Contoh:

BookingConfirmed
PaymentReceived
BookingCheckedOut
BookingReturned
DepositRefunded
UnitMarkedDamaged
MaintenanceScheduled
TenantSubscriptionExpired
12.2 Domain Event
Domain event menandakan sesuatu yang penting telah terjadi di domain.

Contoh:

final readonly class BookingConfirmed
{
public function \_\_construct(
public string $bookingId,
public string $tenantId,
public DateTimeImmutable $occurredAt,
) {
}
}
12.3 Event Handler
Contoh ketika BookingConfirmed diterbitkan:

ReserveInventoryHandler
GenerateInvoiceHandler
SendBookingConfirmationHandler
WriteAuditLogHandler
UpdateBookingProjectionHandler
12.4 Synchronous vs Asynchronous Event
Gunakan synchronous event untuk:

Proses yang harus berhasil dalam transaction.

Menjaga invariant.

Memperbarui state yang wajib konsisten.

Contoh:

Reserve Inventory
Create Allocation
Gunakan asynchronous event untuk:

Email.

WhatsApp.

Push notification.

Analytics.

PDF generation.

Search indexing.

Contoh:

SendBookingConfirmation
GenerateInvoicePdf
UpdateAnalytics
12.5 Transactional Outbox
Pada tahap lanjutan, event penting yang diproses asynchronous menggunakan Transactional Outbox.

Alur:

BEGIN TRANSACTION
Create Booking
Save Domain Event to Outbox
COMMIT

Worker membaca Outbox
Publish Event
Mark as Processed
Tujuannya mencegah kondisi:

Database berhasil disimpan
tetapi event gagal dikirim
12.6 Idempotent Event Handler
Setiap handler asynchronous harus aman jika dijalankan lebih dari sekali.

Contoh idempotency key:

event_id + handler_name
Handler tidak boleh:

Membuat invoice dua kali.

Mengirim refund dua kali.

Mengurangi stok dua kali.

Memproses webhook dua kali.

13. Folder Structure Backend
    Rekomendasi struktur Laravel:

app/
├── Modules/
│ ├── Booking/
│ │ ├── Application/
│ │ │ ├── Actions/
│ │ │ ├── Commands/
│ │ │ ├── Queries/
│ │ │ ├── DTOs/
│ │ │ └── Services/
│ │ │
│ │ ├── Domain/
│ │ │ ├── Entities/
│ │ │ ├── Aggregates/
│ │ │ ├── ValueObjects/
│ │ │ ├── Events/
│ │ │ ├── Exceptions/
│ │ │ ├── Repositories/
│ │ │ ├── Services/
│ │ │ └── Specifications/
│ │ │
│ │ ├── Infrastructure/
│ │ │ ├── Persistence/
│ │ │ │ ├── Eloquent/
│ │ │ │ │ ├── Models/
│ │ │ │ │ ├── Repositories/
│ │ │ │ │ └── Mappers/
│ │ │ ├── Providers/
│ │ │ ├── Queue/
│ │ │ └── Cache/
│ │ │
│ │ └── Presentation/
│ │ ├── Http/
│ │ │ ├── Controllers/
│ │ │ ├── Requests/
│ │ │ └── Resources/
│ │ ├── Console/
│ │ └── Routes/
│ │
│ ├── Inventory/
│ ├── Customer/
│ ├── Payment/
│ ├── Fulfillment/
│ ├── Maintenance/
│ ├── Notification/
│ └── Subscription/
│
├── Shared/
│ ├── Domain/
│ ├── Application/
│ ├── Infrastructure/
│ └── Presentation/
│
└── Providers/ 14. Shared Kernel
Shared Kernel hanya berisi konsep yang benar-benar digunakan lintas-modul.

Contoh:

Money
TenantId
UserId
DateRange
Pagination
Clock
UnitOfWork
DomainEvent
IdempotencyKey
Shared Kernel tidak boleh menjadi tempat menumpuk helper acak.

Hindari folder:

Helpers
Utils
Common
Misc
tanpa batas tanggung jawab yang jelas.

15. Multi-Tenancy Architecture
    15.1 Package
    Sewantara menggunakan package:

Tenant Management:
stancl/tenancy

Subscription Management:
laravelcm/laravel-subscriptions
stancl/tenancy digunakan untuk:

Mengidentifikasi tenant.

Menginisialisasi tenant context.

Memisahkan central route dan tenant route.

Mendukung domain, subdomain, path, request data, dan manual initialization.

Menjalankan tenant-aware queue, command, webhook, dan scheduled task.

laravelcm/laravel-subscriptions digunakan untuk:

Plan SaaS.

Subscription tenant.

Feature entitlement.

Usage limit.

Trial.

Upgrade dan downgrade.

Masa aktif dan expiration subscription.

Subscription melekat pada Tenant, bukan pada User.

15.2 Tenant Identification Strategy
Sewantara menggunakan metode identifikasi tenant yang berbeda sesuai jenis client.

Client Metode Middleware
Landing page mitra Domain atau subdomain InitializeTenancyByDomainOrSubdomain
Website booking tenant Domain atau subdomain InitializeTenancyByDomainOrSubdomain
Custom domain tenant Domain InitializeTenancyByDomainOrSubdomain
Flutter owner app Path tenant InitializeTenancyByPath
Flutter staff app Path tenant InitializeTenancyByPath
Mobile customer app Path tenant InitializeTenancyByPath
Internal service Request header InitializeTenancyByRequestData
Queue dan scheduler Manual initialization tenancy()->initialize()
Central SaaS Tanpa tenant context Tanpa tenancy middleware
15.3 Central Application
Central application berjalan tanpa tenant context.

Central application digunakan untuk:

Landing page utama Sewantara.

Registrasi tenant.

Login Super Admin.

Daftar paket.

Tenant provisioning.

Subscription SaaS.

Billing SaaS.

Tenant activation.

Tenant suspension.

Verifikasi custom domain.

Contoh central domain:

sewantara.id
www.sewantara.id
api.sewantara.id
admin.sewantara.id
Konfigurasi:

'central_domains' => [
'sewantara.id',
'www.sewantara.id',
'api.sewantara.id',
'admin.sewantara.id',
],
Central route tidak menggunakan tenancy middleware.

15.4 Landing Page Tenant
Landing page mitra menggunakan domain atau subdomain.

Middleware:

use Stancl\Tenancy\Middleware\InitializeTenancyByDomainOrSubdomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
Route:

Route::middleware([
'web',
InitializeTenancyByDomainOrSubdomain::class,
PreventAccessFromCentralDomains::class,
])->group(function () {
Route::get('/', LandingPageController::class);
Route::get('/produk', ProductCatalogController::class);
Route::get('/produk/{slug}', ProductDetailController::class);
Route::post('/cek-ketersediaan', CheckPublicAvailabilityController::class);
Route::post('/booking', CreatePublicBookingController::class);
});
Aturan domain:

Record tanpa titik dianggap subdomain.

Record yang mengandung titik dianggap domain atau hostname penuh.

Contoh subdomain:

rentalkamera
tendamakmur
mobiljember
Akses:

https://rentalkamera.sewantara.id
Contoh custom domain:

booking.rentalmakmur.com
sewa.kamerajember.id
Akses:

https://booking.rentalmakmur.com
Landing page tenant digunakan untuk:

Branding tenant.

Katalog produk.

Cek ketersediaan.

Booking publik.

SEO tenant.

Custom domain.

Customer self-service.

15.5 Mobile Application Tenant
Mobile app menggunakan tenant identification berdasarkan path.

Middleware:

use Stancl\Tenancy\Middleware\InitializeTenancyByPath;
Format URL:

/api/v1/{tenant}/...
Rekomendasi identifier:

Tenant UUID
Contoh:

GET /api/v1/8aa487e7-5e13-4f56-a63d-82ce54da2f04/products
POST /api/v1/8aa487e7-5e13-4f56-a63d-82ce54da2f04/bookings
GET /api/v1/8aa487e7-5e13-4f56-a63d-82ce54da2f04/customers
Route:

Route::prefix('api/v1/{tenant}')
->middleware([
InitializeTenancyByPath::class,
'auth:sanctum',
EnsureUserBelongsToTenant::class,
EnsureTenantSubscriptionActive::class,
])
->group(function () {
Route::get('/me', CurrentUserController::class);
Route::apiResource('products', ProductController::class);
Route::apiResource('customers', CustomerController::class);
Route::apiResource('bookings', BookingController::class);
Route::get('/reports/dashboard', DashboardReportController::class);
});
Alasan mobile menggunakan path:

Satu base URL untuk seluruh tenant.

Flutter tidak perlu mengganti domain.

Tenant context eksplisit.

Lebih mudah untuk debugging.

Cocok untuk owner, staff, dan customer mobile.

Lebih sederhana untuk staging dan local development.

Contoh Flutter:

const baseUrl = 'https://api.sewantara.id/api/v1';

final endpoint = '$baseUrl/$tenantId/bookings';
15.6 User dan Tenant Validation
Tenant resolution tidak menggantikan authorization.

Backend wajib memastikan:

Authenticated user tenant_id
harus sama dengan
tenant yang berhasil diinisialisasi
Middleware:

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
Request berikut harus ditolak:

Token Tenant A
mengakses path Tenant B
Response:

{
"success": false,
"error": {
"code": "TENANT_ACCESS_DENIED",
"message": "Anda tidak memiliki akses ke tenant ini."
}
}
15.7 Request Data Identification
Request data identification hanya digunakan untuk internal service.

Middleware:

use Stancl\Tenancy\Middleware\InitializeTenancyByRequestData;
Konfigurasi header:

InitializeTenancyByRequestData::$header = 'X-Tenant-ID';
InitializeTenancyByRequestData::$queryParameter = null;
Header:

X-Tenant-ID: tenant-uuid
Digunakan untuk:

Internal service.

Data synchronization.

ETL.

Internal reporting.

Trusted integration.

Internal callback.

Tidak digunakan untuk:

Landing page.

Public website.

Mobile client biasa.

Customer booking website.

Header tenant tidak boleh menjadi satu-satunya mekanisme authorization.

15.8 Manual Tenant Initialization
Manual initialization digunakan pada:

Queue job.

Scheduled command.

Webhook.

Import data.

Batch processing.

Report generation.

Notification worker.

Contoh:

$tenant = Tenant::findOrFail($tenantId);

tenancy()->initialize($tenant);

try {
app(GenerateReportAction::class)->execute($tenantId);
} finally {
tenancy()->end();
}
Queue job wajib membawa:

tenant_id
Tidak boleh bergantung pada:

auth()->user()
karena authentication context tidak tersedia pada queue.

15.9 Tenant Resolution onFail
Landing page tenant yang tidak ditemukan diarahkan ke central domain.

InitializeTenancyByDomainOrSubdomain::$onFail =
    function ($exception, $request, $next) {
return redirect(
'https://sewantara.id/tenant-not-found'
);
};
Mobile API harus mengembalikan JSON.

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
Internal request data:

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
15.10 Subscription Architecture
Subscription management menggunakan:

laravelcm/laravel-subscriptions
Struktur:

Tenant
↓
Subscription
↓
Plan
↓
Plan Features
Contoh fitur:

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
Contoh limit:

max_branches
max_users
max_products
max_product_units
max_monthly_bookings
max_storage_mb
Middleware subscription dijalankan setelah tenancy dan authentication.

Urutan mobile API:

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
Contoh route:

Route::post('/branches', CreateBranchController::class)
->middleware('subscription.limit:branches');

Route::get('/reports/revenue', RevenueReportController::class)
->middleware('subscription.feature:advanced_reports');
Feature dan limit harus diperiksa di backend, bukan hanya disembunyikan di UI.

15.11 Cache dan Tenant Context
Semua cache key tenant wajib memiliki tenant_id.

Contoh:

tenant:{tenantId}:product:{productId}
tenant:{tenantId}:dashboard:{period}
tenant:{tenantId}:permissions:{userId}
tenant:{tenantId}:subscription
Cache tenant tidak boleh digunakan lintas tenant.

15.12 Route Structure
Rekomendasi:

routes/
├── central.php
├── tenant-web.php
├── tenant-api.php
├── public-tenant-api.php
└── internal.php
central.php: tanpa tenancy middleware.

tenant-web.php: InitializeTenancyByDomainOrSubdomain.

tenant-api.php: InitializeTenancyByPath.

public-tenant-api.php: domain atau subdomain tenant.

internal.php: InitializeTenancyByRequestData.

15.13 Tenant Security Rules
Tenant identifier dari client dianggap tidak terpercaya.

Domain harus terdaftar dan terhubung ke tenant aktif.

User harus menjadi anggota tenant yang aktif.

Token harus sesuai dengan tenant context.

Subscription harus valid untuk operasi write.

Route model binding wajib tenant-aware.

Queue wajib membawa tenant ID.

Cache key wajib memiliki tenant ID.

Request header tenant hanya boleh untuk internal service.

Tenant resolution tidak menggantikan permission dan authorization.

16. Transaction Pattern
    Gunakan Unit of Work atau transaction boundary pada application layer.

interface UnitOfWork
{
public function transaction(callable $callback): mixed;
}
Implementation:

final class LaravelUnitOfWork implements UnitOfWork
{
public function transaction(callable $callback): mixed
    {
        return DB::transaction($callback, attempts: 3);
}
}
Controller tidak boleh mengatur transaction.

17. Specification Pattern
    Specification digunakan untuk business rule yang dapat dikombinasikan.

Contoh:

CustomerCanBookSpecification
ProductCanBeRentedSpecification
UnitAvailableForPeriodSpecification
BookingCanBeConfirmedSpecification
DepositCanBeRefundedSpecification
Contoh:

final class BookingCanBeConfirmedSpecification
{
public function isSatisfiedBy(Booking $booking): bool
    {
        return $booking->hasItems()
            && !$booking->isExpired()
&& $booking->customer()->isAllowedToBook()
&& $booking->hasRequiredPayment();
}
}
Specification jangan digunakan untuk setiap validasi kecil.

18. Factory Pattern
    Factory digunakan ketika pembuatan object memerlukan aturan atau variasi.

Contoh:

BookingFactory
PaymentGatewayFactory
PricingStrategyFactory
NotificationChannelFactory
ProductInventoryFactory
Contoh:

final class ProductInventoryFactory
{
public function create(Product $product): InventoryManager
    {
        return match ($product->inventoryType()) {
InventoryType::Serialized =>
new SerializedInventoryManager(),

            InventoryType::Quantity =>
                new QuantityInventoryManager(),
        };
    }

} 19. Adapter Pattern
Adapter digunakan untuk mengisolasi layanan eksternal.

Contoh contract:

interface ObjectStorage
{
public function put(string $path, resource $contents): StoredFile;

    public function delete(string $path): void;

}
Implementation:

S3ObjectStorage
R2ObjectStorage
MinioObjectStorage
LocalObjectStorage
Layanan eksternal tidak boleh dipanggil langsung dari domain.

20. Policy Pattern
    Policy digunakan untuk authorization.

Contoh:

BookingPolicy
ProductPolicy
PaymentPolicy
CustomerPolicy
ReportPolicy
Policy memeriksa:

Tenant.

Role.

Permission.

Branch scope.

Ownership.

Status resource.

Business eligibility tidak seluruhnya ditempatkan di policy.

Contoh:

Apakah user boleh refund? → Policy
Apakah deposit dapat direfund? → Domain rule 21. Error Handling
Gunakan exception per layer.

Domain Exception
BookingOverlapDetected
UnitNotAvailable
InvalidBookingTransition
CustomerBlacklisted
DepositInsufficient
Application Exception
BookingCreationFailed
PaymentProcessingFailed
Infrastructure Exception
PaymentGatewayUnavailable
StorageUploadFailed
ExternalNotificationFailed
Presentation layer mengubah exception menjadi API error.

Contoh:

{
"success": false,
"error": {
"code": "BOOKING_UNIT_NOT_AVAILABLE",
"message": "Unit tidak tersedia pada periode yang dipilih.",
"details": {
"product_unit_id": "..."
}
}
} 22. Cross-Cutting Concerns
Cross-cutting concern ditempatkan di infrastructure atau middleware.

Contoh:

Logging

Tracing

Audit

Cache

Rate limiting

Authentication

Authorization

Tenant resolution

Idempotency

Monitoring

Business logic tidak boleh bergantung pada logger untuk bekerja.

23. Caching Strategy
    Cache digunakan untuk:

Tenant settings.

Permission.

Product catalog.

Availability projection.

Dashboard summary.

Report hasil berat.

Cache tidak menjadi source of truth.

Aturan:

Gunakan cache key dengan tenant_id.

Tentukan TTL.

Invalidasi melalui event.

Hindari cache untuk saldo pembayaran kritikal.

Hindari mengandalkan cache untuk mencegah double booking.

Contoh cache key:

tenant:{tenantId}:product:{productId}
tenant:{tenantId}:dashboard:{period}
tenant:{tenantId}:permissions:{userId} 24. Queue Strategy
Queue digunakan untuk:

Email.

WhatsApp.

Push notification.

Generate PDF.

Export report.

Image processing.

Import data.

Search indexing.

Analytics projection.

Queue job wajib:

Menyimpan tenant ID.

Idempotent.

Memiliki retry policy.

Memiliki timeout.

Menangani failure.

Tidak menyimpan object Eloquent besar dalam payload.

25. API Architecture
    API menggunakan REST dengan versioning.

Central API:

/api/v1
Tenant mobile API:

/api/v1/{tenant}
Landing page tenant menggunakan domain atau subdomain melalui InitializeTenancyByDomainOrSubdomain.

Controller memanggil Action atau Query.

HTTP Request
↓
Form Request
↓
DTO
↓
Action / Query
↓
Domain
↓
Repository / Adapter
↓
API Resource
↓
HTTP Response
API tidak mengembalikan Eloquent model langsung.

26. Testing Architecture
    Domain Test
    Menguji:

Entity.

Value object.

State transition.

Domain service.

Specification.

Pricing strategy.

Tanpa database.

Application Test
Menguji:

Action.

Orchestration.

Transaction.

Event publishing.

Repository contract menggunakan fake.

Infrastructure Test
Menguji:

Eloquent repository.

Redis adapter.

Payment gateway adapter.

Storage adapter.

Feature Test
Menguji:

API endpoint.

Authentication.

Tenant isolation.

Permission.

Validation.

Database persistence.

Architecture Test
Tambahkan test untuk memastikan:

Domain tidak bergantung pada Laravel.

Controller tidak mengakses Eloquent model langsung.

Module tidak mengakses internal module lain.

Action naming konsisten.

DTO immutable.

Tenant-aware model memiliki tenant scope.

27. Anti-Pattern yang Dilarang
    Fat Controller
    Dilarang:

public function store(Request $request)
{
// validasi
// query
// hitung harga
// buat booking
// alokasi unit
// payment
// notifikasi
}
Fat Model
Dilarang menempatkan semua business logic dalam Eloquent model hingga model menjadi terlalu besar.

God Service
Hindari:

RentalService
AppService
CommonService
GeneralService
yang menangani banyak domain.

Static Helper untuk Business Logic
Hindari:

RentalHelper::calculateEverything();
Gunakan service atau value object dengan dependency yang eksplisit.

Direct External API Call
Dilarang memanggil Midtrans atau Firebase langsung dari controller atau domain.

Cross-Module Database Access
Dilarang:

Payment module langsung update bookings table.
Gunakan contract, action, atau event.

Hidden Side Effect pada Observer
Observer tidak boleh menjalankan workflow kritikal yang sulit dilacak.

Observer hanya digunakan untuk:

Audit sederhana.

Cache invalidation ringan.

Timestamp pendukung.

Workflow utama harus eksplisit di Action.

28. Kapan Pattern Digunakan
    Jangan menggunakan semua pattern pada seluruh fitur.

Kebutuhan Pattern
Proses bisnis spesifik Action
Transfer data antar-layer DTO
Akses aggregate atau query kompleks Repository
Algoritma yang bisa diganti Strategy
Transisi status kompleks State
Aturan yang dapat dikombinasikan Specification
Pembuatan object berdasarkan tipe Factory
Layanan eksternal Adapter
Side effect terpisah Event
Read dan write berbeda CQRS Lite
Konsistensi proses multi-table Unit of Work
Prinsip utama:

Pattern digunakan untuk menyelesaikan masalah,
bukan untuk memperbanyak folder dan class. 29. Tahapan Implementasi Arsitektur
Fase 1 — MVP
Gunakan:

Modular Monolith

Feature-based module

Action

DTO

Repository untuk domain penting

Enum state transition

Domain event

Transaction

Adapter external service

Fase 2 — Growth
Tambahkan:

Read model.

CQRS Lite lebih luas.

Transactional outbox.

Materialized view.

Advanced specification.

Projection.

Search service.

Fase 3 — Scale
Pertimbangkan ekstraksi service jika:

Modul memiliki pola scaling berbeda.

Tim berbeda bertanggung jawab penuh.

Deployment independen dibutuhkan.

Beban tinggi hanya terjadi pada modul tertentu.

Batas domain sudah stabil.

Kandidat:

Notification Service
Payment Service
Report Service
Search Service
Media Service
Microservice tidak dibuat hanya karena jumlah kode bertambah.

30. Definition of Done Arsitektur
    Sebuah fitur dianggap sesuai arsitektur jika:

Memiliki modul yang jelas.

Controller tipis.

Input dikonversi menjadi DTO.

Business process berada pada Action.

Business rule inti berada pada domain.

Query persistence tidak tersebar.

Layanan eksternal menggunakan adapter.

Transaksi kritikal menggunakan transaction.

Event digunakan untuk side effect.

Tenant context diterapkan.

Error code konsisten.

Unit test business logic tersedia.

Feature test tenant isolation tersedia.

Tidak ada dependency terbalik.

Tidak ada direct cross-module write.

31. Contoh Alur Create Booking
    POST /api/v1/{tenant}/bookings
    ↓
    CreateBookingRequest
    ↓
    CreateBookingData
    ↓
    CreateBookingAction
    ↓
    CustomerCanBookSpecification
    ↓
    BookingAvailabilityService
    ↓
    PricingStrategy
    ↓
    Booking Aggregate
    ↓
    BookingRepository
    ↓
    Domain Event: BookingCreated
    ↓
    Generate Invoice
    Reserve Inventory
    Send Notification
    Transaction utama:

Create booking
Create items
Create allocations
Reserve inventory
Create status history
Create outbox events
Side effect asynchronous:

Generate invoice PDF
Send WhatsApp
Send email
Update analytics 32. Contoh Alur Payment Webhook
Payment Gateway Webhook
↓
Webhook Controller
↓
Verify Signature Adapter
↓
ProcessPaymentWebhookAction
↓
Check Idempotency
↓
Update Payment
↓
Update Booking Payment Summary
↓
Domain Event: PaymentReceived
↓
Send Receipt
Confirm Booking bila aturan terpenuhi
Webhook tidak boleh mempercayai payload tanpa verifikasi signature.

33. Contoh Alur Return Booking
    ReturnBookingRequest
    ↓
    ReturnBookingData
    ↓
    ReturnBookingAction
    ↓
    Load Booking Aggregate
    ↓
    Validate Status
    ↓
    Inspection Service
    ↓
    Late Fee Strategy
    ↓
    Damage Charge
    ↓
    Deposit Settlement Service
    ↓
    Update Unit State
    ↓
    Save Booking
    ↓
    BookingReturned Event
34. Kesimpulan
    Arsitektur Sewantara menggunakan:

Modular Monolith
Clean Architecture
DDD Lite
Repository Pattern
Action Pattern
DTO Pattern
Strategy Pattern
State Pattern
Specification Pattern
Factory Pattern
Adapter Pattern
Policy Pattern
CQRS Lite
Event-Driven Architecture
stancl/tenancy
laravelcm/laravel-subscriptions
Struktur utama:

Presentation
↓
Application
↓
Domain
↑
Infrastructure
Prinsip implementasi:

Controller harus tipis.

Use case dibuat sebagai Action.

Data antar-layer menggunakan DTO.

Domain menyimpan business rule.

Repository menyembunyikan persistence.

Strategy menangani algoritma yang berubah.

State menjaga transisi status.

Event memisahkan side effect.

CQRS Lite memisahkan query dan command.

Tenant Context wajib di seluruh proses menggunakan stancl/tenancy.

Transaksi kritikal menggunakan database transaction.

Pattern digunakan seperlunya, bukan dipaksakan.
