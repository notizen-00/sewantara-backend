Module Guide

Sewantara — Universal Rental Management SaaS

Versi: 1.1Status: DraftArsitektur: Modular MonolithPendekatan: Clean Architecture + DDD LiteTujuan dokumen: Menjelaskan batas tanggung jawab, dependency, data, API, event, dan aturan setiap modul.

Package tenancy: stancl/tenancy — single database, shared schemaPackage subscription: laravelcm/laravel-subscriptions

1. Tujuan Dokumen

Dokumen ini menjadi peta utama seluruh modul dalam aplikasi Sewantara.

Setiap modul harus memiliki:

Tujuan yang jelas.

Batas tanggung jawab.

Entity dan aggregate utama.

Tabel database yang digunakan.

API publik.

Action atau use case.

Event yang diterbitkan.

Event yang dikonsumsi.

Dependency terhadap modul lain.

Business rule utama.

Hak akses.

Skenario pengujian.

Dokumen ini digunakan oleh:

Backend developer

Frontend developer

Mobile developer

QA engineer

Product owner

Technical lead

DevOps engineer

AI coding assistant

2. Daftar Modul

Modul utama Sewantara:

Auth
Tenant
Organization
Inventory
Booking
Customer
Payment
Notification
Report
Subscription
Maintenance
Fulfillment
Settings
Audit

Dokumen ini berfokus pada:

Auth
Inventory
Booking
Customer
Payment
Notification
Report
Subscription

Modul pendukung dijelaskan secukupnya agar hubungan antar-modul tetap jelas.

3. Aturan Umum Antar-Modul

3.1 Isolasi Modul

Setiap modul harus memiliki folder dan namespace sendiri.

Contoh:

app/Modules/Auth
app/Modules/Inventory
app/Modules/Booking
app/Modules/Customer
app/Modules/Payment
app/Modules/Notification
app/Modules/Report
app/Modules/Subscription

Satu modul tidak boleh:

Mengubah model modul lain secara langsung.

Mengakses tabel internal modul lain tanpa contract.

Memanggil controller modul lain.

Menjalankan query lintas-modul tanpa alasan jelas.

Menggunakan internal class modul lain yang tidak dipublikasikan.

Komunikasi antar-modul dilakukan melalui:

Application service.

Public contract.

Domain event.

Integration event.

Query service.

Read model.

3.2 Struktur Standar Modul

Setiap modul menggunakan struktur:

Module/
├── Application/
│ ├── Actions/
│ ├── Commands/
│ ├── Queries/
│ ├── DTOs/
│ └── Services/
├── Domain/
│ ├── Entities/
│ ├── Aggregates/
│ ├── ValueObjects/
│ ├── Events/
│ ├── Exceptions/
│ ├── Repositories/
│ ├── Services/
│ └── Specifications/
├── Infrastructure/
│ ├── Persistence/
│ ├── Providers/
│ ├── Adapters/
│ └── Queue/
├── Presentation/
│ ├── Http/
│ ├── Console/
│ └── Routes/
└── Tests/

Tidak semua folder wajib diisi jika modul masih sederhana.

4. Module Dependency Map

Tenant Context (stancl/tenancy)
├──► Auth
├──► Organization
├──► Settings
└──► Seluruh modul tenant

Plan & Subscription (laravelcm/laravel-subscriptions)
└──► Feature Entitlement dan Usage Limit
├──► Auth
├──► Organization
├──► Inventory
├──► Booking
├──► Notification
└──► Report

Customer ───────────────┐
▼
Inventory ───────────► Booking
│
├──► Payment
├──► Fulfillment
├──► Notification
└──► Report

Maintenance ─────────► Inventory
Notification ◄──────── Semua modul
Report ◄────────────── Semua modul
Audit ◄─────────────── Semua modul

Aturan dependency:

Booking boleh membaca data publik Customer dan Inventory.

Payment tidak boleh mengubah booking secara langsung.

Notification hanya menangani pengiriman pesan.

Report membaca projection atau read model.

Subscription menggunakan plan, feature, subscription, dan usage dari laravelcm/laravel-subscriptions untuk mengatur entitlement tenant.

Auth tidak menyimpan business rule rental.

Inventory menjadi sumber kebenaran ketersediaan barang.

4.1 Aturan Tenancy Package

Sewantara menggunakan stancl/tenancy dalam mode single database, shared schema.

Aturan implementasi:

Tenant model
├── extends Stancl\Tenancy\Database\Models\Tenant
├── uses HasDomains bila domain/subdomain digunakan
└── uses HasPlanSubscriptions sebagai subscriber SaaS

Model operasional yang langsung memiliki tenant_id disebut primary tenant model dan wajib memakai:

Stancl\Tenancy\Database\Concerns\BelongsToTenant

Contoh primary tenant model:

User
Branch
Customer
Category
Product
ProductUnit
InventoryStock
Booking
Payment
Invoice
AuditLog

Aturan tambahan:

DatabaseTenancyBootstrapper dinonaktifkan karena tidak ada perpindahan database per tenant.

Job pembuatan, migrasi, dan penghapusan database tenant tidak digunakan.

Tenant diidentifikasi melalui domain, subdomain, path, atau request data sesuai route group.

Untuk kombinasi custom domain dan subdomain gunakan InitializeTenancyByDomainOrSubdomain.

Tabel domain menggunakan tabel package domains, bukan tabel custom tenant_domains.

Central route dan tenant route harus dipisahkan.

Tenant route wajib menginisialisasi tenancy sebelum controller atau service tenant dijalankan.

Queue job, cache key, filesystem path, notification, dan report export wajib membawa konteks tenant.

Validasi relasi harus memakai scoped validation atau pemeriksaan tenant yang sama.

Super admin central tidak otomatis berada dalam tenant context.

4.2 Aturan Subscription Package

Sewantara menggunakan model package berikut:

Plan
Feature
Subscription
SubscriptionUsage

Tabel utama package:

plans
plan_features
plan_subscriptions
plan_subscription_usage

Tenant menjadi subscriber polymorphic menggunakan:

Laravelcm\Subscriptions\Traits\HasPlanSubscriptions

Nama subscription utama tenant:

main

Contoh pemeriksaan fitur:

$tenant->planSubscription('main')?->canUseFeature('multi_branch');

Contoh pencatatan usage:

$tenant->planSubscription('main')
?->recordFeatureUsage('monthly_bookings');

Package subscription tidak menangani pembayaran. Billing, invoice SaaS, webhook gateway, dan reconciliation tetap menjadi tanggung jawab modul Subscription Billing melalui tabel aplikasi sendiri.

5. Auth Module

5.1 Tujuan

Auth Module mengelola autentikasi, identitas pengguna, session, token, role, dan permission.

Modul ini memastikan hanya pengguna yang sah dan memiliki hak akses yang dapat menggunakan fitur sistem.

5.2 Cakupan

Fitur Auth Module:

Login.

Logout.

Registrasi owner.

Verifikasi email.

Lupa password.

Reset password.

Refresh atau revoke token.

Manajemen session.

Role.

Permission.

Assignment user ke role.

Scope akses cabang.

Riwayat login.

Device management.

Optional two-factor authentication.

5.3 Actor

Super Admin.

Owner.

Admin.

Kasir.

Staff gudang.

Driver.

Customer pada fase lanjutan.

5.4 Entity Utama

User
Role
Permission
UserSession
AccessToken
PasswordReset
TenantContext

5.5 Tabel Database

users
roles
permissions
role_permissions
user_roles
personal_access_tokens
password_reset_tokens
user_sessions
login_histories

5.6 Use Case Utama

RegisterTenantOwnerAction
LoginUserAction
LogoutUserAction
RequestPasswordResetAction
ResetPasswordAction
VerifyEmailAction
AssignRoleToUserAction
RevokeUserSessionAction
DeactivateUserAction

5.7 Query Utama

GetCurrentUserQuery
ListUsersQuery
GetUserDetailQuery
ListRolesQuery
ListPermissionsQuery
GetActiveSessionsQuery

5.8 API

POST /api/v1/auth/register
POST /api/v1/auth/login
POST /api/v1/auth/logout
POST /api/v1/auth/forgot-password
POST /api/v1/auth/reset-password
POST /api/v1/auth/verify-email
GET /api/v1/auth/me
GET /api/v1/users
POST /api/v1/users
GET /api/v1/users/{id}
PATCH /api/v1/users/{id}
DELETE /api/v1/users/{id}
GET /api/v1/roles
POST /api/v1/users/{id}/roles

5.9 Event yang Diterbitkan

UserRegistered
UserLoggedIn
UserLoggedOut
UserEmailVerified
UserPasswordReset
UserRoleAssigned
UserDeactivated
SuspiciousLoginDetected

5.10 Event yang Dikonsumsi

TenancyInitialized
TenantSuspended
PlanSubscriptionEnded

Contoh respons:

Ketika tenant suspended, user tenant tidak dapat login.

Ketika tenant expired, hak akses dapat berubah menjadi read-only.

5.11 Business Rule

AUTH-001
Email user harus unik dalam satu tenant.

AUTH-002
Super admin dapat memiliki tenant_id null.

AUTH-003
User nonaktif tidak boleh login.

AUTH-004
User tenant suspended tidak boleh menjalankan operasi write.

AUTH-005
Token harus dapat dicabut.

AUTH-006
Role harus berasal dari tenant yang sama.

AUTH-007
User tidak boleh memberikan permission yang lebih tinggi dari haknya sendiri.

AUTH-008
Password harus disimpan menggunakan secure hashing.

AUTH-009
Login gagal berulang harus terkena rate limit.

AUTH-010
Session harus dapat dicabut per device.

5.12 Permission

user.view
user.create
user.update
user.delete
user.activate
user.assign_role
role.view
role.create
role.update
role.delete
permission.view
session.revoke

5.13 Dependency

Auth bergantung pada:

Tenant Context dari stancl/tenancy.

Subscription entitlement dari Tenant::planSubscription('main').

Audit module.

Notification module untuk reset password dan verifikasi email.

Auth tidak bergantung pada:

Inventory.

Booking.

Payment.

5.14 Test Utama

Login berhasil.

Login dengan password salah.

Login user nonaktif.

Login tenant suspended.

Tenant isolation pada daftar user.

Assignment role tenant berbeda ditolak.

Revoke token.

Password reset hanya sekali.

Rate limit login.

Permission endpoint.

6. Inventory Module

6.1 Tujuan

Inventory Module mengelola kategori, produk, unit fisik, stok berdasarkan jumlah, harga, status, pergerakan barang, dan ketersediaan rental.

Inventory menjadi sumber utama informasi barang yang dapat disewa.

6.2 Cakupan

Kategori dan subkategori.

Produk.

Foto produk.

Harga produk.

Product unit.

Quantity inventory.

Cabang penyimpanan.

QR Code.

Barcode.

Status barang.

Kondisi barang.

Stock movement.

Transfer cabang.

Availability.

Stock opname.

Low stock monitoring.

Custom attributes pada fase berikutnya.

6.3 Jenis Inventory

Serialized Inventory

Setiap unit dicatat individual.

Contoh:

Mobil
Motor
Kamera
Laptop
Drone
PlayStation

Quantity Inventory

Stok dikelola berdasarkan jumlah.

Contoh:

Kursi
Meja
Tenda
Kabel
Peralatan dekorasi

6.4 Entity Utama

Category
Product
ProductPrice
ProductUnit
InventoryStock
InventoryMovement

Aggregate utama:

Product Aggregate
Inventory Stock Aggregate
Product Unit Aggregate

6.5 Tabel Database

categories
products
product_images
product_prices
product_units
inventory_stocks
inventory_stock_movements
product_movements

6.6 Use Case Utama

CreateCategoryAction
CreateProductAction
UpdateProductAction
ActivateProductAction
DeactivateProductAction
CreateProductUnitAction
UpdateProductUnitStatusAction
AdjustInventoryStockAction
TransferProductUnitAction
TransferQuantityStockAction
GenerateProductQrCodeAction
PerformStockOpnameAction
ReserveInventoryAction
ReleaseInventoryReservationAction

6.7 Query Utama

ListCategoriesQuery
ListProductsQuery
GetProductDetailQuery
ListProductUnitsQuery
GetInventoryAvailabilityQuery
GetInventoryMovementQuery
GetLowStockQuery
GetStockByBranchQuery

6.8 API

GET /api/v1/categories
POST /api/v1/categories
PATCH /api/v1/categories/{id}
DELETE /api/v1/categories/{id}

GET /api/v1/products
POST /api/v1/products
GET /api/v1/products/{id}
PATCH /api/v1/products/{id}
DELETE /api/v1/products/{id}

GET /api/v1/products/{id}/units
POST /api/v1/products/{id}/units
PATCH /api/v1/product-units/{id}
POST /api/v1/product-units/{id}/transfer
POST /api/v1/product-units/{id}/status

GET /api/v1/inventory/availability
POST /api/v1/inventory/adjustments
POST /api/v1/inventory/stock-opname
GET /api/v1/inventory/movements

6.9 Event yang Diterbitkan

ProductCreated
ProductUpdated
ProductActivated
ProductDeactivated
ProductUnitCreated
ProductUnitStatusChanged
InventoryReserved
InventoryReservationReleased
InventoryStockAdjusted
InventoryTransferred
InventoryLowStockDetected
ProductUnitMarkedDamaged
ProductUnitMarkedLost

6.10 Event yang Dikonsumsi

BookingConfirmed
BookingCancelled
BookingExpired
BookingCheckedOut
BookingReturned
MaintenanceScheduled
MaintenanceCompleted

Contoh:

BookingConfirmed memicu reservasi inventory.

BookingCancelled melepaskan reservasi.

BookingCheckedOut mengubah reserved menjadi rented.

BookingReturned mengubah rented menjadi available, cleaning, atau maintenance.

6.11 Business Rule

INV-001
Produk serialized harus memiliki product unit.

INV-002
Produk quantity harus memiliki inventory stock per cabang.

INV-003
Unit maintenance tidak boleh disewa.

INV-004
Unit damaged, lost, atau inactive tidak boleh tersedia.

INV-005
Stok tidak boleh bernilai negatif.

INV-006
Jumlah reserved dan rented tidak boleh melebihi total stok.

INV-007
Unit hanya boleh berada pada satu cabang aktif.

INV-008
Transfer unit harus mencatat cabang asal dan tujuan.

INV-009
Barcode dan unit code harus unik per tenant.

INV-010
Produk yang memiliki histori booking tidak boleh dihapus permanen.

INV-011
Status tersedia tidak menjamin tersedia pada semua periode.

INV-012
Ketersediaan harus dihitung berdasarkan periode rental.

INV-013
Barang yang overlap dengan booking aktif tidak tersedia.

INV-014
Perubahan stok wajib membuat movement.

INV-015
Stock adjustment wajib mencatat alasan dan user.

6.12 Permission

category.view
category.create
category.update
category.delete
product.view
product.create
product.update
product.delete
product.activate
product_unit.view
product_unit.create
product_unit.update
product_unit.transfer
inventory.view
inventory.adjust
inventory.opname
inventory.export

6.13 Dependency

Inventory bergantung pada:

Tenant.

Organization atau branch.

Audit.

Media storage.

Maintenance untuk status maintenance.

Inventory menyediakan data kepada:

Booking.

Fulfillment.

Report.

Customer website.

6.14 Test Utama

Membuat serialized product.

Membuat quantity product.

Unit code unik per tenant.

Tenant isolation.

Stok tidak bisa negatif.

Unit maintenance tidak tersedia.

Availability berdasarkan periode.

Booking overlap.

Transfer cabang.

Stock adjustment menghasilkan movement.

Release reservation.

Concurrent reservation.

7. Booking Module

7.1 Tujuan

Booking Module mengelola seluruh siklus transaksi rental, mulai dari draft, cek ketersediaan, konfirmasi, alokasi barang, pengambilan, pengembalian, hingga selesai.

Booking merupakan modul inti Sewantara.

7.2 Cakupan

Booking manual oleh admin.

Booking online oleh customer.

Booking item.

Durasi rental.

Cek ketersediaan.

Unit allocation.

Quantity reservation.

Kalender booking.

Status transition.

Booking history.

Pembatalan.

Perpanjangan.

Reschedule.

Expiration.

Internal notes.

Customer notes.

Harga snapshot.

Promo dan voucher pada fase lanjutan.

7.3 Entity Utama

Booking
BookingItem
BookingUnitAllocation
BookingStatusHistory
RentalPeriod
BookingNumber

Aggregate utama:

Booking Aggregate
├── Booking Items
├── Unit Allocations
└── Status History

7.4 Status Booking

draft
pending
confirmed
preparing
ready
ongoing
returned
completed
cancelled
rejected
expired

Alur utama:

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

7.5 Tabel Database

bookings
booking_items
booking_unit_allocations
booking_status_histories
booking_extensions
booking_cancellations

Tabel tambahan dapat ditambahkan sesuai kebutuhan implementasi.

7.6 Use Case Utama

CreateBookingAction
SubmitBookingAction
ConfirmBookingAction
RejectBookingAction
CancelBookingAction
ExpireBookingAction
PrepareBookingAction
MarkBookingReadyAction
CheckOutBookingAction
ReturnBookingAction
CompleteBookingAction
RescheduleBookingAction
ExtendBookingAction
AllocateProductUnitAction
ReleaseBookingAllocationAction

7.7 Query Utama

ListBookingsQuery
GetBookingDetailQuery
GetBookingCalendarQuery
GetBookingTimelineQuery
GetUpcomingBookingsQuery
GetOverdueBookingsQuery
GetBookingAvailabilityQuery

7.8 API

GET /api/v1/bookings
POST /api/v1/bookings
GET /api/v1/bookings/{id}
PATCH /api/v1/bookings/{id}

POST /api/v1/bookings/{id}/submit
POST /api/v1/bookings/{id}/confirm
POST /api/v1/bookings/{id}/reject
POST /api/v1/bookings/{id}/cancel
POST /api/v1/bookings/{id}/prepare
POST /api/v1/bookings/{id}/ready
POST /api/v1/bookings/{id}/checkout
POST /api/v1/bookings/{id}/return
POST /api/v1/bookings/{id}/complete
POST /api/v1/bookings/{id}/extend
POST /api/v1/bookings/{id}/reschedule

GET /api/v1/bookings/calendar
GET /api/v1/bookings/availability

7.9 Event yang Diterbitkan

BookingCreated
BookingSubmitted
BookingConfirmed
BookingRejected
BookingCancelled
BookingExpired
BookingPrepared
BookingReady
BookingCheckedOut
BookingReturned
BookingCompleted
BookingRescheduled
BookingExtended
BookingAllocationCreated
BookingAllocationReleased

7.10 Event yang Dikonsumsi

PaymentReceived
PaymentFailed
DepositHeld
InventoryReserved
InventoryReservationFailed
CustomerBlacklisted
TenantSuspended

Contoh:

PaymentReceived dapat mengonfirmasi booking bila syarat DP terpenuhi.

InventoryReservationFailed membatalkan proses konfirmasi.

TenantSuspended mencegah booking baru.

7.11 Business Rule

BKG-001
Booking wajib memiliki minimal satu item.

BKG-002
Tanggal mulai harus lebih kecil dari tanggal selesai.

BKG-003
Customer blacklisted tidak boleh membuat booking.

BKG-004
Produk nonaktif tidak boleh ditambahkan.

BKG-005
Barang harus tersedia pada seluruh periode booking.

BKG-006
Unit tidak boleh memiliki booking aktif yang overlap.

BKG-007
Harga disimpan sebagai snapshot.

BKG-008
Booking tidak boleh dikonfirmasi tanpa inventory reservation.

BKG-009
Booking confirmed dapat memerlukan pembayaran DP.

BKG-010
Booking ongoing hanya dapat berasal dari ready.

BKG-011
Booking completed harus sudah returned.

BKG-012
Booking completed harus memenuhi aturan pembayaran tenant.

BKG-013
Cancelled booking harus melepaskan reservation.

BKG-014
Expired booking harus melepaskan reservation.

BKG-015
Reschedule wajib mengecek ketersediaan ulang.

BKG-016
Extension wajib mengecek overlap setelah end_at lama.

BKG-017
Status transition harus mengikuti transition map.

BKG-018
Booking tenant lain tidak boleh diakses.

BKG-019
Perubahan status wajib mencatat history.

BKG-020
Proses create dan confirm harus transactional.

7.12 Permission

booking.view
booking.create
booking.update
booking.submit
booking.confirm
booking.reject
booking.cancel
booking.prepare
booking.checkout
booking.return
booking.complete
booking.extend
booking.reschedule
booking.export
booking.view_calendar

7.13 Dependency

Booking bergantung pada:

Customer.

Inventory.

Pricing.

Tenant settings.

Organization atau branch.

Payment untuk aturan pembayaran.

Audit.

Booking menyediakan event kepada:

Payment.

Notification.

Report.

Fulfillment.

Inventory.

7.14 Test Utama

Create booking.

Booking tanpa item ditolak.

Customer blacklist ditolak.

Unit overlap ditolak.

Quantity tidak cukup ditolak.

Concurrent booking tidak double reserve.

Status transition invalid.

Cancel release inventory.

Expired release inventory.

Reschedule cek ulang.

Extension overlap.

Snapshot harga tetap.

Tenant isolation.

Booking completion rule.

8. Customer Module

8.1 Tujuan

Customer Module mengelola identitas pelanggan, dokumen, alamat, histori rental, status verifikasi, catatan, dan blacklist.

8.2 Cakupan

Customer profile.

Kontak.

Alamat.

Dokumen identitas.

Verifikasi identitas.

Customer history.

Blacklist.

Notes.

Customer tags.

Customer portal pada fase lanjutan.

Customer account pada fase lanjutan.

8.3 Entity Utama

Customer
CustomerDocument
CustomerAddress
CustomerBlacklist
CustomerTag

8.4 Tabel Database

customers
customer_documents
customer_addresses
customer_blacklists
customer_tags
customer_tag_assignments

8.5 Use Case Utama

CreateCustomerAction
UpdateCustomerAction
VerifyCustomerDocumentAction
BlacklistCustomerAction
RemoveCustomerBlacklistAction
AddCustomerAddressAction
MergeDuplicateCustomerAction
DeactivateCustomerAction

8.6 Query Utama

ListCustomersQuery
GetCustomerDetailQuery
GetCustomerRentalHistoryQuery
GetCustomerDocumentsQuery
SearchCustomerQuery
GetBlacklistedCustomersQuery

8.7 API

GET /api/v1/customers
POST /api/v1/customers
GET /api/v1/customers/{id}
PATCH /api/v1/customers/{id}
DELETE /api/v1/customers/{id}

POST /api/v1/customers/{id}/documents
POST /api/v1/customers/{id}/documents/{documentId}/verify
POST /api/v1/customers/{id}/blacklist
DELETE /api/v1/customers/{id}/blacklist

GET /api/v1/customers/{id}/bookings
GET /api/v1/customers/{id}/payments

8.8 Event yang Diterbitkan

CustomerCreated
CustomerUpdated
CustomerDocumentUploaded
CustomerDocumentVerified
CustomerBlacklisted
CustomerBlacklistRemoved
CustomerDeactivated

8.9 Event yang Dikonsumsi

BookingCompleted
PaymentFailed
BookingOverdue
ChargeCreated

Event tersebut dapat digunakan untuk memperbarui:

Statistik customer.

Rental count.

Total spending.

Risk indicator.

Catatan keterlambatan.

8.10 Business Rule

CUS-001
Customer harus memiliki nama dan nomor telepon.

CUS-002
Nomor telepon tidak wajib unik global.

CUS-003
Satu customer dapat terdaftar di beberapa tenant.

CUS-004
Customer blacklisted tidak boleh booking.

CUS-005
Blacklist harus memiliki alasan.

CUS-006
Dokumen sensitif tidak boleh ditampilkan tanpa permission.

CUS-007
Dokumen hanya dapat diverifikasi user berwenang.

CUS-008
Customer dengan histori transaksi tidak boleh hard delete.

CUS-009
Status customer tenant A tidak memengaruhi tenant B.

CUS-010
Data customer harus mengikuti kebijakan retensi dan privasi.

8.11 Permission

customer.view
customer.create
customer.update
customer.delete
customer.verify_document
customer.blacklist
customer.remove_blacklist
customer.view_sensitive_data
customer.export

8.12 Dependency

Customer bergantung pada:

Tenant.

Media storage.

Audit.

Auth untuk verifier.

Customer menyediakan data kepada:

Booking.

Payment.

Report.

Notification.

8.13 Test Utama

Create customer.

Tenant isolation.

Blacklist dengan alasan.

Booking customer blacklist ditolak.

Verifikasi dokumen.

Permission data sensitif.

Soft delete customer.

Riwayat booking customer.

Duplicate phone tenant sama.

Customer lintas tenant.

9. Payment Module

9.1 Tujuan

Payment Module mengelola pembayaran rental, uang muka, pelunasan, deposit, denda, refund, invoice, dan integrasi payment gateway.

9.2 Cakupan

Pembayaran manual.

Pembayaran gateway.

Down payment.

Full payment.

Partial payment.

Deposit.

Refund.

Charge.

Invoice.

Payment webhook.

Payment reconciliation.

Payment expiration.

Payment proof.

Settlement.

Idempotency.

9.3 Entity Utama

Payment
PaymentTransaction
Invoice
InvoiceItem
Deposit
DepositTransaction
Refund
BookingCharge

Aggregate utama:

Payment Aggregate
Invoice Aggregate
Deposit Aggregate

9.4 Tabel Database

payments
payment_transactions
invoices
invoice_items
deposits
deposit_transactions
booking_charges
refunds
payment_webhook_logs

9.5 Use Case Utama

CreatePaymentAction
RecordManualPaymentAction
CreateGatewayPaymentAction
ProcessPaymentWebhookAction
MarkPaymentPaidAction
ExpirePaymentAction
CreateInvoiceAction
GenerateInvoicePdfAction
HoldDepositAction
DeductDepositAction
RefundDepositAction
CreateBookingChargeAction
RefundPaymentAction
ReconcilePaymentAction

9.6 Query Utama

ListPaymentsQuery
GetPaymentDetailQuery
GetInvoiceDetailQuery
ListOutstandingInvoicesQuery
GetDepositDetailQuery
GetPaymentReconciliationQuery
GetRevenueSummaryQuery

9.7 API

GET /api/v1/payments
POST /api/v1/payments
GET /api/v1/payments/{id}
POST /api/v1/payments/{id}/verify
POST /api/v1/payments/{id}/cancel
POST /api/v1/payments/{id}/refund

GET /api/v1/invoices
GET /api/v1/invoices/{id}
GET /api/v1/invoices/{id}/pdf

POST /api/v1/bookings/{id}/charges
POST /api/v1/bookings/{id}/deposit/hold
POST /api/v1/bookings/{id}/deposit/deduct
POST /api/v1/bookings/{id}/deposit/refund

POST /api/v1/webhooks/payments/{gateway}

9.8 Payment Gateway Adapter

Contract:

PaymentGateway

Implementasi:

MidtransPaymentGateway
XenditPaymentGateway
DuitkuPaymentGateway
ManualPaymentGateway

Gateway tidak boleh dipanggil langsung dari controller.

9.9 Event yang Diterbitkan

PaymentCreated
PaymentReceived
PaymentFailed
PaymentExpired
PaymentCancelled
PaymentRefunded
InvoiceIssued
InvoicePaid
InvoiceOverdue
DepositHeld
DepositDeducted
DepositRefunded
DepositForfeited
BookingChargeCreated

9.10 Event yang Dikonsumsi

BookingCreated
BookingSubmitted
BookingConfirmed
BookingReturned
BookingCompleted
BookingCancelled

Contoh:

BookingCreated dapat membuat draft invoice.

BookingReturned dapat menghitung charge dan deposit settlement.

BookingCancelled dapat memicu refund sesuai kebijakan.

9.11 Business Rule

PAY-001
Nilai pembayaran harus lebih dari nol.

PAY-002
Paid amount tidak boleh melebihi total kewajiban tanpa alasan overpayment.

PAY-003
Refund tidak boleh melebihi pembayaran yang berhasil.

PAY-004
Deposit bukan revenue.

PAY-005
Potongan deposit tidak boleh melebihi saldo deposit.

PAY-006
Payment webhook wajib diverifikasi signature.

PAY-007
Webhook harus idempotent.

PAY-008
Gateway reference harus unik bila tersedia.

PAY-009
Pembayaran partial harus mengubah status booking menjadi partial.

PAY-010
Pembayaran lunas mengubah payment status menjadi paid.

PAY-011
Invoice lama tidak berubah ketika harga produk berubah.

PAY-012
Payment paid tidak boleh dihapus.

PAY-013
Refund harus memiliki alasan dan actor.

PAY-014
Booking charge harus dapat ditelusuri ke booking.

PAY-015
Deposit hanya direfund setelah proses pemeriksaan selesai.

PAY-016
Payment operation kritikal harus transactional.

PAY-017
Currency pembayaran harus sama dengan currency tenant.

PAY-018
Callback gateway tidak boleh mempercayai nominal dari client.

9.12 Permission

payment.view
payment.create
payment.verify
payment.cancel
payment.refund
invoice.view
invoice.issue
invoice.download
deposit.view
deposit.hold
deposit.deduct
deposit.refund
charge.create
payment.reconcile

9.13 Dependency

Payment bergantung pada:

Booking.

Tenant settings.

Customer.

Payment gateway adapter.

Audit.

Notification.

Payment menyediakan event kepada:

Booking.

Notification.

Report.

Subscription.

9.14 Test Utama

Manual payment.

Partial payment.

Full payment.

Duplicate webhook.

Invalid webhook signature.

Refund melebihi pembayaran.

Deposit deduction.

Deposit refund.

Currency mismatch.

Invoice snapshot.

Payment tenant isolation.

Concurrent webhook.

Gateway timeout.

Idempotency.

10. Notification Module

10.1 Tujuan

Notification Module menangani pengiriman pesan sistem melalui berbagai channel tanpa memasukkan logic pengiriman ke modul bisnis.

10.2 Cakupan

In-app notification.

Email.

WhatsApp.

Push notification.

SMS pada fase lanjutan.

Template notification.

Notification preference.

Queue.

Retry.

Delivery log.

Failed delivery.

Scheduled reminder.

Broadcast.

Tenant branding.

10.3 Entity Utama

Notification
NotificationTemplate
NotificationPreference
NotificationDelivery
DeviceToken

10.4 Tabel Database

notifications
notification_templates
notification_preferences
notification_deliveries
device_tokens
notification_schedules

10.5 Channel Adapter

EmailNotificationChannel
WhatsAppNotificationChannel
PushNotificationChannel
InAppNotificationChannel
SmsNotificationChannel

Provider dapat berupa:

Resend
SMTP
Firebase Cloud Messaging
OneSignal
WhatsApp Cloud API
Third-party WhatsApp provider

10.6 Use Case Utama

SendNotificationAction
ScheduleNotificationAction
RetryFailedNotificationAction
MarkNotificationReadAction
RegisterDeviceTokenAction
UpdateNotificationPreferenceAction
RenderNotificationTemplateAction

10.7 Query Utama

ListNotificationsQuery
GetUnreadNotificationCountQuery
GetNotificationDeliveryStatusQuery
ListNotificationTemplatesQuery

10.8 API

GET /api/v1/notifications
POST /api/v1/notifications/{id}/read
POST /api/v1/notifications/read-all
GET /api/v1/notification-preferences
PATCH /api/v1/notification-preferences
POST /api/v1/device-tokens
DELETE /api/v1/device-tokens/{id}

GET /api/v1/notification-templates
PATCH /api/v1/notification-templates/{id}

10.9 Event yang Diterbitkan

NotificationQueued
NotificationSent
NotificationDelivered
NotificationFailed
NotificationRead
DeviceTokenRegistered

10.10 Event yang Dikonsumsi

UserRegistered
BookingCreated
BookingConfirmed
BookingCancelled
BookingReady
BookingCheckedOut
BookingOverdue
BookingReturned
PaymentReceived
PaymentFailed
InvoiceOverdue
DepositRefunded
MaintenanceScheduled
TenantSubscriptionExpiring
TenantSubscriptionExpired

10.11 Business Rule

NOT-001
Notifikasi asynchronous harus diproses melalui queue.

NOT-002
Job harus membawa tenant_id.

NOT-003
Handler harus idempotent.

NOT-004
Template dapat memiliki override per tenant.

NOT-005
Pesan sensitif tidak boleh dimasukkan penuh ke log.

NOT-006
User preference harus dihormati, kecuali notifikasi wajib.

NOT-007
Notification gagal harus memiliki retry policy.

NOT-008
Provider gagal tidak boleh membatalkan transaksi bisnis utama.

NOT-009
Notifikasi tidak boleh dikirim kepada tenant suspended kecuali notifikasi akun.

NOT-010
Nomor telepon dan email harus divalidasi sebelum pengiriman.

NOT-011
Delivery log harus menyimpan provider reference.

NOT-012
Reminder tidak boleh terkirim dua kali untuk jadwal yang sama.

10.12 Permission

notification.view
notification.mark_read
notification.manage_template
notification.send_manual
notification.view_delivery_log
notification.retry
notification.manage_preference

10.13 Dependency

Notification bergantung pada:

Auth.

Tenant settings.

External provider adapter.

Queue.

Audit.

Notification dikonsumsi oleh seluruh modul melalui event.

Notification tidak boleh mengubah state booking atau pembayaran.

10.14 Test Utama

Event menghasilkan notification job.

Tenant branding.

User preference.

Retry failure.

Idempotent send.

Provider timeout.

Invalid device token.

Mark as read.

Notification tenant isolation.

Template rendering.

Reminder duplicate prevention.

11. Report Module

11.1 Tujuan

Report Module menyediakan laporan operasional, keuangan, inventory, customer, dan performa tenant.

Report berfokus pada query dan analytics, bukan transaction processing.

11.2 Cakupan

Dashboard summary.

Booking report.

Revenue report.

Payment report.

Outstanding payment.

Inventory report.

Product utilization.

Customer report.

Deposit report.

Charge report.

Maintenance cost.

Branch performance.

Export PDF.

Export Excel.

Export CSV.

Scheduled report pada fase lanjutan.

11.3 Read Model Utama

DashboardSummary
BookingReport
RevenueReport
InventoryUtilizationReport
CustomerActivityReport
DepositLiabilityReport
BranchPerformanceReport
MaintenanceCostReport

11.4 Data Source

Report dapat membaca:

Read replica.

Database view.

Materialized view.

Projection table.

Cache.

Query builder.

Data warehouse pada fase lanjut.

Report tidak boleh menjadi sumber utama untuk aturan bisnis.

11.5 Tabel Database

Report dapat menggunakan tabel transaksi seluruh modul secara read-only.

Projection tambahan:

daily_revenue_summaries
daily_booking_summaries
product_utilization_summaries
customer_activity_summaries
branch_performance_summaries
report_exports

11.6 Use Case Utama

GenerateBookingReportAction
GenerateRevenueReportAction
GenerateInventoryReportAction
GenerateCustomerReportAction
GenerateDepositReportAction
ExportReportAction
ScheduleReportAction
RefreshReportProjectionAction

11.7 Query Utama

GetDashboardSummaryQuery
GetRevenueReportQuery
GetBookingReportQuery
GetInventoryReportQuery
GetProductUtilizationQuery
GetCustomerReportQuery
GetDepositLiabilityQuery
GetBranchPerformanceQuery

11.8 API

GET /api/v1/dashboard
GET /api/v1/reports/bookings
GET /api/v1/reports/revenue
GET /api/v1/reports/payments
GET /api/v1/reports/inventory
GET /api/v1/reports/products
GET /api/v1/reports/customers
GET /api/v1/reports/deposits
GET /api/v1/reports/charges
GET /api/v1/reports/maintenance
GET /api/v1/reports/branches

POST /api/v1/reports/exports
GET /api/v1/reports/exports/{id}

11.9 Event yang Diterbitkan

ReportExportRequested
ReportExportCompleted
ReportExportFailed
ReportProjectionRefreshed

11.10 Event yang Dikonsumsi

BookingCreated
BookingConfirmed
BookingCompleted
BookingCancelled
PaymentReceived
PaymentRefunded
DepositHeld
DepositRefunded
InventoryStockAdjusted
ProductUnitStatusChanged
MaintenanceCompleted
CustomerCreated

Event digunakan untuk memperbarui projection atau cache.

11.11 Business Rule

RPT-001
Semua laporan harus dibatasi tenant_id.

RPT-002
Deposit held tidak dihitung sebagai revenue.

RPT-003
Refund mengurangi revenue sesuai tanggal akuntansi yang ditentukan.

RPT-004
Cancelled booking tidak dihitung sebagai booking sukses.

RPT-005
Report besar harus diproses melalui queue.

RPT-006
Export harus memiliki masa berlaku.

RPT-007
User hanya boleh melihat cabang sesuai scope.

RPT-008
Data sensitif customer harus disamarkan sesuai permission.

RPT-009
Dashboard boleh menggunakan cache.

RPT-010
Cache harus dipisahkan per tenant dan filter.

RPT-011
Report tidak boleh mengubah data transaksi.

RPT-012
Timezone tenant harus digunakan untuk grouping harian.

RPT-013
Currency tenant harus ditampilkan secara konsisten.

RPT-014
Projection harus dapat dibangun ulang.

11.12 Permission

report.view_dashboard
report.view_booking
report.view_revenue
report.view_payment
report.view_inventory
report.view_customer
report.view_deposit
report.view_maintenance
report.export
report.view_all_branches

11.13 Dependency

Report bergantung secara read-only pada:

Booking.

Inventory.

Customer.

Payment.

Subscription.

Maintenance.

Organization.

Report tidak boleh menjadi dependency domain utama.

11.14 Test Utama

Tenant isolation.

Branch scope.

Deposit bukan revenue.

Refund mengurangi revenue.

Cancelled booking tidak dihitung.

Timezone grouping.

Export queue.

Large dataset pagination.

Cache key per tenant.

Projection rebuild.

Sensitive data masking.

12. Subscription Module

12.1 Tujuan

Subscription Module mengelola katalog plan SaaS, feature entitlement, trial, lifecycle subscription, usage limit, billing SaaS, aktivasi, upgrade, downgrade, renewal, cancellation, grace policy, dan pembatasan akses tenant.

Modul ini wajib menggunakan laravelcm/laravel-subscriptions sebagai sumber kebenaran untuk plan, feature, subscription, dan usage. Pembayaran tetap dikelola oleh adapter billing Sewantara karena payment berada di luar cakupan package.

12.2 Cakupan

Plan SaaS.

Plan feature dan limit.

Trial period.

Subscription tenant.

Feature entitlement.

Resettable usage.

Upgrade dan downgrade.

Renewal dan cancellation.

Grace/read-only policy aplikasi.

Subscription billing invoice.

Subscription payment.

Payment webhook.

Usage reconciliation.

Super-admin override dengan audit.

12.3 Model dan Aggregate Utama

Model package:

Laravelcm\Subscriptions\Models\Plan
Laravelcm\Subscriptions\Models\Feature
Laravelcm\Subscriptions\Models\Subscription
Laravelcm\Subscriptions\Models\SubscriptionUsage

Subscriber aplikasi:

Tenant
└── HasPlanSubscriptions

Aggregate aplikasi:

Tenant SaaS Subscription Aggregate
├── Package Plan Subscription
├── Package Feature Usage
├── Subscription Billing Invoice
├── Subscription Payment
└── Subscription Status History

Gunakan custom model yang meng-extend model package hanya ketika Sewantara perlu menambahkan behavior, cast UUID, event, atau relasi billing.

12.4 Tabel Database

Tabel package:

plans
plan_features
plan_subscriptions
plan_subscription_usage

Tabel aplikasi untuk billing dan audit lifecycle:

subscription_invoices
subscription_invoice_items
subscription_payments
subscription_payment_transactions
subscription_status_histories
subscription_webhook_logs

Tabel berikut tidak dibuat karena menduplikasi package:

subscription_plans
subscription_features
tenant_subscriptions
subscription_usage
subscription_plan_features

12.5 Lifecycle Subscription

Status lifecycle package ditentukan dari kolom subscription seperti trial, cancellation, dan ends_at.

State operasional Sewantara dipetakan sebagai:

trial = subscription onTrial()
active = subscription active()
cancelled = subscription canceled(), tetapi dapat tetap aktif sampai akhir periode
ended = subscription ended()
past_due = status billing aplikasi
suspended = kebijakan tenant aplikasi akibat billing atau override admin

past_due, grace_period, dan suspended bukan pengganti lifecycle package. Status tersebut merupakan policy aplikasi yang disimpan pada billing/history atau metadata tenant.

12.6 Use Case Utama

CreatePlanAction
UpdatePlanAction
AttachPlanFeatureAction
StartTenantTrialAction
CreateTenantPlanSubscriptionAction
ChangeTenantPlanAction
RenewTenantPlanSubscriptionAction
CancelTenantPlanSubscriptionAction
RecordFeatureUsageAction
ReduceFeatureUsageAction
CheckFeatureEntitlementAction
CheckFeatureRemainingAction
CreateSubscriptionInvoiceAction
RecordSubscriptionPaymentAction
SuspendTenantWriteAccessAction
RestoreTenantWriteAccessAction

Mapping package:

Create subscription → newPlanSubscription('main', $plan)
Get subscription     → planSubscription('main')
Change plan          → changePlan($plan)
Renew → renew()
Cancel → cancel() atau cancel(true)
Can use feature → canUseFeature($feature)
Record usage         → recordFeatureUsage($feature, $quantity)
Reduce usage         → reduceFeatureUsage($feature, $quantity)

12.7 Query Utama

GetCurrentPlanSubscriptionQuery
ListPlansQuery
GetPlanFeaturesQuery
GetFeatureEntitlementQuery
GetFeatureUsageQuery
GetFeatureRemainingsQuery
GetSubscriptionBillingInvoiceQuery
ListEndingTrialsQuery
ListEndingSubscriptionsQuery

12.8 API

Tenant API:

GET /api/v1/subscription
GET /api/v1/subscription/plans
GET /api/v1/subscription/features
GET /api/v1/subscription/usage
POST /api/v1/subscription/change-plan
POST /api/v1/subscription/renew
POST /api/v1/subscription/cancel
GET /api/v1/subscription/invoices
GET /api/v1/subscription/invoices/{id}

Central super-admin API:

GET /api/v1/admin/plans
POST /api/v1/admin/plans
PATCH /api/v1/admin/plans/{id}
POST /api/v1/admin/plans/{id}/features
GET /api/v1/admin/plan-subscriptions
POST /api/v1/admin/tenants/{id}/change-plan
POST /api/v1/admin/tenants/{id}/suspend
POST /api/v1/admin/tenants/{id}/restore

Webhook billing:

POST /api/v1/webhooks/subscription-payments/{gateway}

Route plan management dan billing webhook berada pada central route. Route penggunaan fitur tenant berjalan setelah tenant identification.

12.9 Feature Entitlement

Feature package menggunakan slug stabil.

Boolean entitlement:

multi_branch
custom_domain
payment_gateway
whatsapp_notification
advanced_report
api_access
white_label
custom_role
mobile_staff

Limit atau usage feature:

branches
users
products
product_units
monthly_bookings
storage_mb

Aturan penggunaan:

Boolean feature diperiksa dengan canUseFeature().

Limit feature diperiksa melalui value, usage, dan remaining package.

Feature periodik harus menggunakan resettable period/interval.

Usage dicatat hanya setelah transaksi bisnis berhasil commit.

Operasi yang dibatalkan harus mengurangi atau mengompensasi usage bila memang sebelumnya tercatat.

Endpoint tetap wajib memeriksa entitlement meskipun menu disembunyikan di frontend.

12.10 Event yang Diterbitkan

Domain/integration event aplikasi:

TenantTrialStarted
TenantPlanSubscriptionCreated
TenantPlanChanged
TenantPlanSubscriptionRenewed
TenantPlanSubscriptionCancelled
TenantPlanSubscriptionEnded
TenantSubscriptionPastDue
TenantWriteAccessSuspended
TenantWriteAccessRestored
PlanFeatureUsageRecorded
PlanFeatureLimitReached
SubscriptionPaymentReceived
SubscriptionPaymentFailed

Jangan mengikat modul lain langsung pada internal event package. Terjemahkan event package/model lifecycle menjadi integration event Sewantara yang stabil.

12.11 Event yang Dikonsumsi

TenantCreated
SubscriptionPaymentReceived
SubscriptionPaymentFailed
UserCreated
UserDeactivated
BranchCreated
BranchDeleted
ProductCreated
ProductDeleted
ProductUnitCreated
ProductUnitDeleted
BookingConfirmed
BookingCancelled
StorageUsageChanged

Event digunakan untuk:

Membuat trial subscription.

Mencatat atau mengurangi usage.

Merekonsiliasi usage aktual.

Mengaktifkan atau membatasi write access.

Mengirim pengingat trial dan periode berakhir.

12.12 Business Rule

SUB-001
Tenant adalah subscriber, bukan User.

SUB-002
Subscription utama tenant menggunakan nama `main`.

SUB-003
Plan dan feature dikelola melalui model package.

SUB-004
Tenant tidak boleh memiliki lebih dari satu subscription `main` aktif secara logis.

SUB-005
Tenant baru hanya memperoleh trial sesuai kebijakan onboarding.

SUB-006
Feature entitlement harus diperiksa melalui subscription aktif tenant.

SUB-007
Usage feature harus menggunakan API usage package, bukan counter custom yang menduplikasi.

SUB-008
Downgrade ditolak bila usage aktual melebihi limit plan tujuan, kecuali ada strategi penyelesaian.

SUB-009
Change plan harus menggunakan `changePlan()` agar billing period dan usage mengikuti behavior package.

SUB-010
Cancel normal tetap aktif sampai akhir periode; cancel immediate hanya untuk kebutuhan khusus.

SUB-011
Renewal menggunakan `renew()` dan harus disinkronkan dengan pembayaran berhasil.

SUB-012
Payments, invoice, gateway, dan webhook bukan tanggung jawab package subscription.

SUB-013
Subscription payment webhook wajib terverifikasi dan idempotent.

SUB-014
Plan price dan feature value pada invoice SaaS harus disimpan sebagai snapshot.

SUB-015
`past_due`, grace, read-only, dan suspension adalah policy aplikasi di atas lifecycle package.

SUB-016
Tenant expired atau ended tidak langsung dihapus.

SUB-017
Super-admin override wajib memiliki alasan dan audit log.

SUB-018
Feature usage yang bersifat periodik harus memiliki reset interval yang jelas.

SUB-019
Usage recording harus aman dari race condition dan dijalankan dalam boundary transaksi yang tepat.

SUB-020
Semua lookup subscription tenant harus memverifikasi tenant context atau subscriber yang dituju.

12.13 Permission

Tenant permission:

subscription.view
subscription.change_plan
subscription.renew
subscription.cancel
subscription.view_invoice
subscription.view_usage

Central super-admin permission:

plan.view
plan.create
plan.update
plan.delete
plan_feature.manage
plan_subscription.view
plan_subscription.change
plan_subscription.cancel
tenant.suspend_write
subscription.override
subscription_billing.reconcile

12.14 Dependency

Subscription Management bergantung pada:

Tenant central model.

laravelcm/laravel-subscriptions.

Audit.

Notification.

Subscription Billing bergantung pada:

Payment gateway adapter.

Central payment webhook.

Subscription Management contract.

Subscription menyediakan entitlement kepada:

Auth.

Organization.

Inventory.

Booking.

Notification.

Report.

API access.

Modul bisnis tidak boleh membaca tabel plan_subscriptions secara langsung. Gunakan entitlement/query contract.

12.15 Test Utama

Tenant menggunakan trait HasPlanSubscriptions.

Membuat subscription main untuk tenant.

Trial aktif dianggap active.

canUseFeature() untuk boolean feature.

Record, reduce, dan reset usage.

Limit branch, user, product, unit, dan booking.

Change plan dengan billing interval sama.

Change plan dengan billing interval berbeda.

Downgrade saat usage terlalu tinggi.

Cancel akhir periode.

Cancel immediate.

Renew subscription.

Ended subscription membatasi write access.

Duplicate billing webhook.

Invalid billing signature.

Invoice snapshot.

Super-admin override diaudit.

Tenant isolation dan central-route isolation.

13. Modul Pendukung

13.1 Tenant Module

Tenant Module membungkus integrasi stancl/tenancy dan menjadi sumber tenant context.

Mengelola:

Model Tenant central.

Tabel package tenants.

Tabel package domains.

Profil dan metadata tenant.

Subdomain dan custom domain.

Branding.

Timezone.

Currency.

Status operasional tenant.

Tenant settings.

Inisialisasi dan penghentian tenant context.

Tenant identification yang didukung:

InitializeTenancyByDomainOrSubdomain
InitializeTenancyByPath
InitializeTenancyByRequestData

Pemilihan utama Sewantara:

Web tenant → domain atau subdomain
Custom domain → domain penuh pada tabel domains
Mobile/API → domain tenant atau X-Tenant header sesuai deployment

Tenant Module tidak membuat database per tenant karena Sewantara memakai shared database/shared schema.

13.2 Organization Module

Mengelola:

Cabang.

User cabang.

Scope akses cabang.

Lokasi.

Jam operasional.

13.3 Fulfillment Module

Mengelola:

Persiapan barang.

Pickup.

Delivery.

Check-out.

Check-in.

Checklist.

Inspection.

Bukti serah terima.

13.4 Maintenance Module

Mengelola:

Service.

Repair.

Cleaning.

Calibration.

Inspection.

Maintenance schedule.

Maintenance cost.

13.5 Settings Module

Mengelola:

Booking settings.

Payment settings.

Invoice settings.

Notification settings.

Tax settings.

Numbering settings.

Rental policy.

Tenant feature configuration.

13.6 Audit Module

Mengelola:

Audit log.

Activity log.

Actor.

IP address.

User agent.

Data lama.

Data baru.

Event penting.

14. Event Catalog Ringkas

Auth Events

UserRegistered
UserLoggedIn
UserDeactivated

Inventory Events

ProductCreated
InventoryReserved
InventoryReservationReleased
ProductUnitStatusChanged

Booking Events

BookingCreated
BookingConfirmed
BookingCancelled
BookingCheckedOut
BookingReturned
BookingCompleted

Customer Events

CustomerCreated
CustomerBlacklisted
CustomerDocumentVerified

Payment Events

PaymentReceived
PaymentFailed
PaymentRefunded
DepositHeld
DepositRefunded

Notification Events

NotificationSent
NotificationFailed

Report Events

ReportExportCompleted
ReportProjectionRefreshed

Subscription Events

TenantPlanSubscriptionCreated
TenantPlanChanged
TenantPlanSubscriptionEnded
TenantWriteAccessSuspended

15. Cross-Module Flow

15.1 Create Booking

Auth
↓
Customer Validation
↓
Inventory Availability
↓
Pricing
↓
Booking Created
↓
Inventory Reserved
↓
Invoice Created
↓
Notification Sent
↓
Report Projection Updated

15.2 Payment Flow

Payment Gateway
↓
Payment Webhook
↓
Signature Verification
↓
Payment Updated
↓
Booking Payment Summary Updated
↓
Booking Confirmed jika syarat terpenuhi
↓
Notification Sent
↓
Report Projection Updated

15.3 Return Flow

Booking Return
↓
Fulfillment Inspection
↓
Inventory Condition Updated
↓
Late Fee dan Damage Charge
↓
Deposit Settlement
↓
Booking Completed
↓
Customer History Updated
↓
Report Updated
↓
Notification Sent

15.4 Subscription Expiration

Plan Subscription Ended
↓
Subscription policy mengevaluasi grace/past-due
↓
Tenant Write Access Suspended bila diperlukan
↓
Auth dan endpoint tenant menolak operasi write
↓
Feature Entitlement Disabled
↓
Notification Sent
↓
Audit Recorded

16. Module Ownership

Setiap modul sebaiknya memiliki owner teknis.

Modul

Tanggung Jawab

Auth

Authentication dan permission

Inventory

Produk, unit, dan stok

Booking

Siklus transaksi rental

Customer

Profil dan verifikasi customer

Payment

Pembayaran, invoice, deposit

Notification

Pengiriman pesan

Report

Dashboard dan laporan

Subscription

Paket SaaS dan billing

Owner modul bertanggung jawab terhadap:

Kualitas kode.

API contract.

Database migration.

Test coverage.

Dokumentasi.

Event contract.

Backward compatibility.

Monitoring.

17. Definition of Done per Modul

Sebuah fitur modul dianggap selesai jika:

Requirement telah disetujui.

Business rule terdokumentasi.

Action atau use case tersedia.

DTO typed tersedia.

Authorization diterapkan.

Tenant isolation diterapkan.

Database migration tersedia.

Index dan constraint diperiksa.

API resource konsisten.

Domain event ditentukan.

Unit test tersedia.

Feature test tersedia.

Error code terdokumentasi.

Audit log tersedia untuk aksi penting.

Dokumentasi module guide diperbarui.

Observability tersedia.

Tidak ada direct cross-module write.

Tidak melanggar dependency rule.

18. Anti-Pattern Antar-Modul

Dilarang melakukan hal berikut:

Direct Model Mutation

Payment module langsung mengubah BookingModel.

Gunakan:

PaymentReceived event
atau
BookingPaymentService contract

Shared Database Tanpa Batas

Walaupun satu database digunakan, tabel tetap memiliki pemilik modul.

Contoh:

bookings dimiliki Booking Module
payments dimiliki Payment Module
products dimiliki Inventory Module

Modul lain hanya membaca melalui contract atau read model bila memungkinkan.

Event untuk Semua Hal

Event tidak digunakan untuk proses yang harus konsisten dalam satu transaction.

Contoh reservasi inventory saat konfirmasi booking dapat tetap synchronous.

Circular Dependency

Dilarang:

Booking bergantung pada Payment
dan
Payment bergantung langsung pada Booking implementation.

Gunakan contract dan event agar dependency satu arah.

God Module

Hindari modul:

Core
General
Common
Rental
Management

yang menampung seluruh logic aplikasi.

19. Prioritas Implementasi Modul

Fase 1 — Foundation

Tenant + stancl/tenancy
Auth
Organization
Settings
Audit
Plan Catalog + laravel-subscriptions

Fase 2 — Core Rental

Customer
Inventory
Booking

Fase 3 — Transaction

Payment
Invoice
Deposit
Fulfillment

Fase 4 — Operational Support

Notification
Maintenance
Report

Fase 5 — SaaS Growth

Subscription Billing Automation
Custom Domain Verification
White Label
Advanced Analytics
Usage Reconciliation

20. Kesimpulan

Sewantara dibagi menjadi modul bisnis yang jelas:

Auth
Inventory
Booking
Customer
Payment
Notification
Report
Subscription

Tanggung jawab utama:

Auth mengelola identitas dan akses.

Inventory mengelola barang, unit, stok, dan availability.

Booking mengelola transaksi rental.

Customer mengelola pelanggan dan blacklist.

Payment mengelola pembayaran, invoice, deposit, dan refund.

Notification mengirim pesan melalui berbagai channel.

Report menyediakan dashboard dan laporan.

Subscription memakai laravelcm/laravel-subscriptions untuk plan, feature, lifecycle, dan usage tenant; billing tetap dikelola Sewantara.

Prinsip utama implementasi:

Setiap tabel memiliki pemilik modul.

Cross-module write tidak dilakukan langsung.

Komunikasi menggunakan service contract atau event.

Tenant isolation wajib memakai tenant context Stancl dan BelongsToTenant pada primary tenant models.

Business rule berada di domain masing-masing.

Proses kritikal menggunakan transaction.

Side effect dijalankan asynchronous.

Report menggunakan read model.

Subscription entitlement diperiksa di backend melalui subscription main milik Tenant.

Setiap modul wajib memiliki test dan dokumentasi.
