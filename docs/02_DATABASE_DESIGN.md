Database Design

Sewantara — Universal Rental Management SaaS

Versi: 1.2Status: Draft MVPDatabase utama: PostgreSQLModel: Central Database + Schema per TenantPrimary key: Mixed UUID dan BIGINTTimezone default: Asia/JakartaCurrency default: IDR

Package tenancy: stancl/tenancy (multi-database tenancy)Package subscription: laravelcm/laravel-subscriptions

1. Tujuan Dokumen

Dokumen ini menjelaskan rancangan database Sewantara sebagai aplikasi SaaS rental multi-bisnis.

Database harus dapat mendukung:

Banyak tenant

Banyak cabang

Banyak pengguna

Rental berbagai jenis barang

Inventaris berdasarkan unit

Inventaris berdasarkan jumlah

Booking berbasis waktu

Pencegahan double booking

Pembayaran parsial

Deposit

Pengembalian

Denda

Maintenance

Audit aktivitas

Subscription SaaS

Rancangan database harus tetap fleksibel untuk digunakan pada:

Rental mobil

Rental motor

Rental kamera

Rental PlayStation

Rental tenda

Rental alat acara

Rental alat berat

Rental pakaian

Rental alat kesehatan

Rental perlengkapan bayi

Rental alat camping

Rental lainnya

2. Prinsip Desain Database

2.1 Multi-Tenant dengan stancl/tenancy

Sewantara menggunakan satu central database PostgreSQL dengan schema terpisah untuk setiap tenant. Model Tenant mengikuti model database-aware stancl/tenancy. Tabel operasional berada pada schema tenant; kolom tenant_id tetap dipakai sebagai defense-in-depth.

Model primary tenant seperti Branch, Customer, Category, Product, ProductUnit, Booking, dan Payment wajib memakai:

use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

Trait tersebut memberi global scope berdasarkan tenant aktif dan otomatis mengisi tenant_id saat model dibuat dalam tenant context.

Aturan wajib:

Tenant harus diinisialisasi sebelum query tenant dijalankan.

Query Builder/DB::table() tetap wajib menambahkan tenant_id manual.

Unique/exists validation harus di-scope ke tenant aktif.

Model central seperti Tenant, Domain, Plan, dan subscription tidak memakai BelongsToTenant.

DatabaseTenancyBootstrapper aktif. Database tenant dibuat dan migration tenant dijalankan setelah pembayaran subscription terverifikasi, bukan langsung saat record tenant dibuat.

2.2 Central dan Tenant-Scoped Tables

database: sewantara_app
schema: public

Central/global tables:

tenants
domains
plans
plan_features
plan_subscriptions
plan_subscription_usage

Tenant-scoped tables:

users
branches
customers
categories
products
product_units
bookings
payments
invoices
maintenance_records
... seluruh tabel operasional rental

Single-database tenancy memiliki kompleksitas scoping lebih tinggi. Karena itu, Eloquent scope, raw query, validation, queue, policy, dan automated test isolasi tenant wajib menjadi bagian arsitektur.

2.3 Strategi Primary Key

Sewantara menggunakan strategi primary key campuran.

Master data tenant menggunakan BIGINT auto-increment:

roles
permissions
branches
customers
categories
products
product_units
inventory_stocks

Identitas central, user tenant, dan data transaksi tetap menggunakan UUID,
termasuk tenants, users, bookings, booking_items, payments, invoices, dan tabel
histori transaksi. Foreign key wajib mengikuti tipe primary key tabel tujuan.

Kolom referensi polymorphic yang dapat menunjuk master maupun transaksi disimpan
sebagai varchar agar dapat menampung BIGINT dan UUID.

2.4 Soft Delete

Soft delete digunakan untuk data master dan transaksi tertentu.

Tabel yang menggunakan soft delete:

tenants

users

branches

categories

products

product_units

customers

bookings

Data pembayaran, audit, dan movement tidak boleh dihapus secara normal.

2.5 Snapshot Data Transaksi

Data transaksi harus menyimpan snapshot dari informasi produk pada saat booking.

Contoh data snapshot:

product_name
unit_price
pricing_type
deposit_amount
discount_amount

Tujuannya agar transaksi lama tidak berubah ketika:

Nama produk diperbarui

Harga rental berubah

Deposit produk berubah

Produk dinonaktifkan

Produk dihapus secara soft delete

2.6 Nilai Uang

Semua nilai uang menggunakan:

numeric(18,2)

Contoh:

daily_price numeric(18,2)
total_amount numeric(18,2)
deposit_amount numeric(18,2)

Nilai uang tidak boleh menggunakan tipe float atau double.

2.7 Waktu

Semua tanggal dan waktu transaksi menggunakan:

timestamp with time zone

Di Laravel dapat disimpan sebagai timestamp dan dikonversi berdasarkan timezone tenant.

Kolom seperti tanggal pembelian dapat menggunakan:

date

3. Entity Relationship Diagram

3.1 ERD Tingkat Tinggi

Plan (`plans`)
│
▼
Plan Subscription ───► Tenant (subscriber)
│
├───────────────┐
│ │
▼ ▼
Users Branches
│ │
│ ├─────────────┐
│ │ │
▼ ▼ ▼
Roles Product Units Bookings
│ │ │
▼ │ ▼
Permissions │ Booking Items
│ │
▼ ▼
Categories ───────► Products ──► Unit Allocations
│ │
▼ ▼
Product Images Product Units
│
├── Maintenance Records
├── Product Movements
└── Inspection Records

Customers ───────────────────────► Bookings
│
├── Payments
├── Deposit Transactions
├── Booking Charges
├── Deliveries
└── Invoices

3.2 Relasi Multi-Tenant

Tenant
├── Users
├── Branches
├── Roles
├── Customers
├── Categories
├── Products
├── Product Units
├── Bookings
├── Payments
├── Invoices
├── Maintenance Records
├── Audit Logs
└── Settings

Semua data tersebut harus terhubung ke satu tenant.

3.3 Relasi Inventory

Category
│
└── Product
│
├── Product Images
├── Product Prices
├── Product Units
└── Inventory Stocks

product_units digunakan untuk barang serialized.

inventory_stocks digunakan untuk barang quantity-based.

3.4 Relasi Booking

Customer
│
└── Booking
│
├── Booking Items
│ └── Booking Unit Allocations
│
├── Payments
├── Deposit Transactions
├── Booking Charges
├── Delivery
├── Invoice
└── Inspections

4. Daftar Tabel

4.1 SaaS, Tenant, dan Subscription Package

tenants
domains
plans
plan_features
plan_subscriptions
plan_subscription_usage
tenant_settings
subscription_payments
subscription_invoices

4.2 User dan Hak Akses

users
roles
permissions
role_permissions
user_roles

4.3 Organisasi

branches
branch_users

4.4 Customer

customers
customer_documents
customer_addresses
customer_blacklists

4.5 Inventory

categories
products
product_images
product_prices
product_units
inventory_stocks
inventory_stock_movements

4.6 Booking

bookings
booking_items
booking_unit_allocations
booking_status_histories

4.7 Pembayaran

payments
payment_transactions
invoices
invoice_items
deposits
deposit_transactions
booking_charges
refunds

4.8 Fulfillment

deliveries
delivery_status_histories
booking_checklists
booking_checklist_items
inspection_records
inspection_photos

4.9 Maintenance

maintenance_records
maintenance_photos
product_movements

4.10 Sistem

notifications
audit_logs
activity_logs
failed_jobs
jobs
personal_access_tokens

5. Detail Tabel

5.1 plans — Package Managed

Gunakan tabel plans bawaan laravelcm/laravel-subscriptions. Jangan membuat plans karena akan menduplikasi model package.

Kolom

Tipe

Keterangan

id

bigint

PK bawaan package

name

json/jsonb

Nama paket

slug

varchar

Kode unik

description

json/jsonb nullable

Deskripsi

is_active

boolean

Status paket

price

numeric

Harga per siklus

signup_fee

numeric

Biaya aktivasi

currency

char(3)

Default IDR

trial_period

smallint

Durasi trial

trial_interval

varchar

Interval trial

invoice_period

smallint

Durasi billing

invoice_interval

varchar

Interval billing

grace_period

smallint

Masa tenggang

grace_interval

varchar

Interval tenggang

active_subscribers_limit

integer nullable

Batas subscriber

sort_order

integer

Urutan

created_at / updated_at / deleted_at

timestamp

Timestamp package

Batas cabang, user, produk, dan unit tidak disimpan sebagai kolom custom di plans. Gunakan plan_features.

branches.limit
users.limit
products.limit
units.limit
custom_domain.enabled
reports.export.enabled
api_access.enabled

5.2 plan_features — Package Managed

Kolom

Tipe

Keterangan

id

bigint

PK package

plan_id

bigint

FK ke plans

name

json/jsonb

Nama feature

slug

varchar

Identifier feature

description

json/jsonb nullable

Deskripsi

value

varchar

Nilai limit/boolean

resettable_period

smallint

Periode reset

resettable_interval

varchar

Interval reset

sort_order

integer

Urutan

created_at / updated_at / deleted_at

timestamp

Timestamp package

plan_subscription_usage cocok untuk kuota konsumsi seperti export/API call. Untuk batas kapasitas record seperti cabang dan user, hitung jumlah record aktif lalu bandingkan dengan getFeatureValue().

5.3 tenants — Stancl Central Model dan Subscriber

tenants adalah central table stancl/tenancy. Model Tenant juga menjadi subscriber laravelcm/laravel-subscriptions menggunakan HasPlanSubscriptions.

Kolom

Tipe

Keterangan

id

uuid/string

ID tenant Stancl

name

varchar(150)

Nama bisnis

slug

varchar(150)

Identifier internal

business_type

varchar(100) nullable

Jenis rental

email

varchar(150) nullable

Email

phone

varchar(30) nullable

Telepon

address

text nullable

Alamat

logo_path

varchar(255) nullable

Logo

timezone

varchar(50)

Asia/Jakarta

currency

char(3)

IDR

status

varchar(30)

active, suspended, closed

data

jsonb nullable

Virtual attributes Stancl

activated_at

timestamptz nullable

Aktivasi

suspended_at

timestamptz nullable

Suspend

created_at / updated_at / deleted_at

timestamptz

Timestamp

Hapus kolom berikut dari tenants karena menjadi tanggung jawab subscription package:

subscription_plan_id
trial_ends_at
billing_cycle
subscription_end_at
auto_renew

class Tenant extends \Stancl\Tenancy\Database\Models\Tenant
{
use \Stancl\Tenancy\Database\Concerns\HasDomains;
use \Stancl\Tenancy\Database\Concerns\HasScopedValidationRules;
use \Laravelcm\Subscriptions\Traits\HasPlanSubscriptions;
use \Illuminate\Database\Eloquent\SoftDeletes;

    public static function getCustomColumns(): array
    {
        return [
            'id', 'name', 'slug', 'business_type', 'email', 'phone',
            'address', 'logo_path', 'timezone', 'currency', 'status',
            'activated_at', 'suspended_at', 'created_at', 'updated_at',
            'deleted_at',
        ];
    }

}

id wajib ada pada getCustomColumns().

5.4 domains — Stancl Package Table

Gunakan tabel domains, bukan domains.

Kolom

Tipe

Keterangan

id

bigint/uuid

PK sesuai published migration

domain

varchar(255)

Domain/subdomain unik global

tenant_id

uuid/string

FK ke tenant

created_at / updated_at

timestamp

Timestamp

Dengan InitializeTenancyByDomainOrSubdomain, nilai tanpa titik seperti acme diperlakukan sebagai subdomain, sedangkan nilai bertitik seperti rental.acme.com diperlakukan sebagai hostname/custom domain. Kolom type tidak diperlukan.

Kolom aplikasi opsional:

is_primary
verification_status
verification_token
verified_at
ssl_status

CREATE UNIQUE INDEX domains_one_primary_per_tenant
ON domains (tenant_id)
WHERE is_primary = true;

Path identification (/{tenant}) tidak membutuhkan record domain untuk tenant yang hanya memakai path.

5.4.1 plan_subscriptions — Package Managed

Menggantikan tabel custom plan_subscriptions. Tenant terhubung secara polymorphic.

Kolom

Tipe

Keterangan

id

bigint

PK package

subscriber_type

varchar

Morph type tenant

subscriber_id

uuid/string

Harus sama tipe dengan tenants.id

plan_id

bigint

FK ke plans

name

json/jsonb

Nama subscription, misalnya main

slug

varchar

Slug subscription

description

json/jsonb nullable

Deskripsi

timezone

varchar nullable

Timezone

trial_ends_at

timestamp nullable

Akhir trial

starts_at

timestamp nullable

Mulai

ends_at

timestamp nullable

Akhir periode

canceled_at

timestamp nullable

Pembatalan

created_at / updated_at / deleted_at

timestamp

Timestamp package

Migration bawaan memakai $table->morphs('subscriber'), yang biasanya menghasilkan bigint. Untuk UUID tenant:

$table->uuidMorphs('subscriber');

Jika tenants.id berupa varchar, buat subscriber_id sebagai string dan tambahkan composite index bersama subscriber_type.

Relation::enforceMorphMap([
'tenant' => \App\Models\Tenant::class,
]);

Package tidak menangani pembayaran. Simpan transaksi recurring SaaS pada subscription_payments, lalu hubungkan ke plan_subscriptions.id.

5.4.2 plan_subscription_usage — Package Managed

Kolom

Tipe

Keterangan

id

bigint

PK package

subscription_id

bigint

FK subscription

feature_id

bigint

FK feature

used

integer

Penggunaan

timezone

varchar nullable

Timezone reset

valid_until

timestamp nullable

Berlaku sampai

created_at / updated_at / deleted_at

timestamp

Timestamp package

Cocok untuk:

reports.export.monthly
api.requests.monthly
notifications.sms.monthly

5.5 tenant_settings

Menyimpan konfigurasi fleksibel tenant.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

group

varchar(100)

Kelompok pengaturan

key

varchar(150)

Nama pengaturan

value

jsonb

Nilai

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Unique:

tenant_id + group + key

Contoh:

booking.auto_confirm
payment.minimum_down_payment
invoice.prefix
rental.grace_period_minutes

5.6 users

Menyimpan pengguna internal platform dan tenant.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid nullable

Tenant

name

varchar(150)

Nama

email

varchar(150)

Email

phone

varchar(30) nullable

Nomor telepon

password

varchar(255)

Password hash

avatar_path

varchar(255) nullable

Avatar

is_active

boolean

Status

email_verified_at

timestamp nullable

Verifikasi email

last_login_at

timestamp nullable

Login terakhir

remember_token

varchar(100) nullable

Remember token

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Unique:

tenant_id + email

Catatan:

tenant_id nullable untuk super admin SaaS.

5.7 roles

Menyimpan role pengguna.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid nullable

Tenant

name

varchar(100)

Nama role

code

varchar(100)

Kode role

is_system

boolean

Role bawaan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Contoh role:

super_admin
owner
admin
cashier
warehouse
driver

5.8 permissions

Menyimpan permission sistem.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

name

varchar(150)

Nama permission

code

varchar(150)

Kode unik

module

varchar(100)

Nama modul

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Contoh:

booking.view
booking.create
booking.confirm
payment.refund
product.manage
report.export

5.9 role_permissions

Pivot role dan permission.

Kolom

Tipe

Keterangan

role_id

bigint

Role

permission_id

bigint

Permission

Primary key komposit:

role_id + permission_id

5.10 user_roles

Pivot user dan role.

Kolom

Tipe

Keterangan

user_id

uuid

User

role_id

bigint

Role

branch_id

bigint nullable

Scope cabang

Primary key komposit:

user_id + role_id + branch_id

5.11 branches

Menyimpan cabang tenant.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid

Tenant

name

varchar(150)

Nama cabang

code

varchar(50)

Kode cabang

email

varchar(150) nullable

Email

phone

varchar(30) nullable

Telepon

address

text nullable

Alamat

latitude

numeric(10,7) nullable

Latitude

longitude

numeric(10,7) nullable

Longitude

is_active

boolean

Status

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Unique:

tenant_id + code

5.12 customers

Menyimpan data pelanggan tenant.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid

Tenant

name

varchar(150)

Nama

email

varchar(150) nullable

Email

phone

varchar(30)

Nomor telepon

birth_date

date nullable

Tanggal lahir

gender

varchar(20) nullable

Jenis kelamin

status

varchar(30)

Status

notes

text nullable

Catatan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Status:

active
inactive
blacklisted

Index:

tenant_id + phone
tenant_id + email
tenant_id + status

5.13 customer_documents

Menyimpan identitas customer.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

customer_id

bigint

Customer

document_type

varchar(30)

Jenis dokumen

document_number

varchar(100) nullable

Nomor dokumen

front_path

varchar(255) nullable

Foto depan

back_path

varchar(255) nullable

Foto belakang

expired_at

date nullable

Masa berlaku

is_verified

boolean

Status verifikasi

verified_by

uuid nullable

User verifier

verified_at

timestamp nullable

Waktu verifikasi

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Jenis dokumen:

ktp
sim
passport
npwp
other

5.14 customer_addresses

Menyimpan beberapa alamat customer.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

customer_id

bigint

Customer

label

varchar(100)

Label alamat

recipient_name

varchar(150)

Nama penerima

phone

varchar(30)

Telepon

address

text

Alamat

city

varchar(100) nullable

Kota

province

varchar(100) nullable

Provinsi

postal_code

varchar(20) nullable

Kode pos

latitude

numeric(10,7) nullable

Latitude

longitude

numeric(10,7) nullable

Longitude

is_primary

boolean

Alamat utama

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

5.15 categories

Menyimpan kategori dan subkategori produk.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid

Tenant

parent_id

bigint nullable

Parent category

name

varchar(150)

Nama

slug

varchar(150)

Slug

description

text nullable

Deskripsi

image_path

varchar(255) nullable

Gambar

sort_order

integer

Urutan

is_active

boolean

Status

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Unique:

tenant_id + slug

Constraint:

parent_id tidak boleh sama dengan id sendiri
parent category harus berasal dari tenant yang sama

5.16 products

Menyimpan jenis atau model barang rental.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid

Tenant

category_id

bigint nullable

Kategori

name

varchar(200)

Nama produk

slug

varchar(200)

Slug

sku

varchar(100) nullable

SKU

brand

varchar(100) nullable

Merek

model

varchar(100) nullable

Model

description

text nullable

Deskripsi

inventory_type

varchar(30)

Jenis inventory

default_pricing_type

varchar(30)

Jenis harga

minimum_rental_duration

integer

Minimum durasi

deposit_amount

numeric(18,2)

Deposit

late_fee_amount

numeric(18,2)

Denda terlambat

is_featured

boolean

Produk unggulan

is_active

boolean

Status

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Inventory type:

serialized
quantity

Pricing type:

hourly
daily
weekly
monthly
event
custom

Unique:

tenant_id + slug
tenant_id + sku

5.17 product_images

Menyimpan foto produk.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

product_id

bigint

Produk

image_path

varchar(255)

Path gambar

alt_text

varchar(255) nullable

Alt text

is_primary

boolean

Gambar utama

sort_order

integer

Urutan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Constraint:

satu produk hanya boleh memiliki satu gambar utama

5.18 product_prices

Menyimpan beberapa harga produk.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

product_id

bigint

Produk

branch_id

bigint nullable

Cabang

pricing_type

varchar(30)

Tipe harga

duration

integer

Durasi

price

numeric(18,2)

Harga

start_at

timestamp nullable

Awal berlaku

end_at

timestamp nullable

Akhir berlaku

is_active

boolean

Status

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Contoh:

daily, duration 1, Rp350.000
weekly, duration 1, Rp1.800.000
hourly, duration 6, Rp500.000

5.19 product_units

Menyimpan setiap unit fisik untuk produk serialized.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid

Tenant

product_id

bigint

Produk

branch_id

bigint nullable

Cabang

unit_code

varchar(100)

Kode unit

barcode

varchar(150) nullable

Barcode

qr_code

varchar(150) nullable

QR Code

serial_number

varchar(150) nullable

Serial number

plate_number

varchar(50) nullable

Nomor polisi

condition

varchar(30)

Kondisi

status

varchar(30)

Status

purchase_date

date nullable

Tanggal pembelian

purchase_price

numeric(18,2) nullable

Harga pembelian

current_meter

bigint nullable

Kilometer atau hour meter

meter_unit

varchar(30) nullable

Satuan meter

notes

text nullable

Catatan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Status:

available
reserved
rented
maintenance
cleaning
damaged
lost
inactive

Condition:

new
good
fair
damaged

Unique:

tenant_id + unit_code
tenant_id + barcode
tenant_id + qr_code

5.20 inventory_stocks

Menyimpan stok barang quantity-based per cabang.

Kolom

Tipe

Keterangan

id

bigint auto-increment

Primary key

tenant_id

uuid

Tenant

product_id

bigint

Produk

branch_id

bigint

Cabang

quantity_total

integer

Total stok

quantity_reserved

integer

Stok dipesan

quantity_rented

integer

Stok disewa

quantity_maintenance

integer

Stok maintenance

quantity_damaged

integer

Stok rusak

quantity_lost

integer

Stok hilang

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Available dihitung:

quantity_available =
quantity_total

- quantity_reserved
- quantity_rented
- quantity_maintenance
- quantity_damaged
- quantity_lost

Unique:

tenant_id + product_id + branch_id

Constraint:

semua quantity >= 0
jumlah status tidak boleh melebihi quantity_total

5.21 inventory_stock_movements

Mencatat perubahan stok quantity-based.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

product_id

bigint

Produk

branch_id

bigint

Cabang

booking_id

uuid nullable

Booking

type

varchar(50)

Jenis movement

quantity

integer

Jumlah

balance_before

integer

Saldo sebelumnya

balance_after

integer

Saldo setelah

reference_type

varchar(100) nullable

Tipe referensi

reference_id

uuid nullable

ID referensi

notes

text nullable

Catatan

created_by

uuid nullable

User

occurred_at

timestamp

Waktu kejadian

created_at

timestamp

Waktu dibuat

Type:

stock_in
stock_out
reservation
reservation_release
rental_out
rental_return
maintenance_out
maintenance_return
damaged
lost
adjustment
transfer_in
transfer_out

5.22 bookings

Menyimpan transaksi rental utama.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

branch_id

bigint nullable

Cabang

customer_id

bigint

Customer

created_by

uuid nullable

User pembuat

booking_number

varchar(100)

Nomor booking

start_at

timestamp

Mulai rental

end_at

timestamp

Selesai rental

actual_start_at

timestamp nullable

Waktu keluar aktual

actual_end_at

timestamp nullable

Waktu kembali aktual

status

varchar(30)

Status booking

fulfillment_type

varchar(30)

Pickup atau delivery

subtotal

numeric(18,2)

Subtotal

discount_amount

numeric(18,2)

Diskon

tax_amount

numeric(18,2)

Pajak

delivery_fee

numeric(18,2)

Ongkir

deposit_amount

numeric(18,2)

Deposit

charge_amount

numeric(18,2)

Denda tambahan

total_amount

numeric(18,2)

Total

paid_amount

numeric(18,2)

Telah dibayar

remaining_amount

numeric(18,2)

Sisa

payment_status

varchar(30)

Status pembayaran

customer_notes

text nullable

Catatan customer

internal_notes

text nullable

Catatan internal

confirmed_at

timestamp nullable

Waktu konfirmasi

cancelled_at

timestamp nullable

Waktu pembatalan

completed_at

timestamp nullable

Waktu selesai

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

deleted_at

timestamp nullable

Soft delete

Status booking:

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

Payment status:

unpaid
partial
paid
refunded
partially_refunded

Unique:

tenant_id + booking_number

5.23 booking_items

Menyimpan produk dalam booking.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

product_id

bigint

Produk

product_name

varchar(200)

Snapshot nama

sku

varchar(100) nullable

Snapshot SKU

inventory_type

varchar(30)

Jenis inventory

pricing_type

varchar(30)

Jenis harga

quantity

integer

Jumlah

duration

integer

Durasi

unit_price

numeric(18,2)

Harga satuan

subtotal

numeric(18,2)

Subtotal

discount_amount

numeric(18,2)

Diskon

deposit_amount

numeric(18,2)

Deposit

total_amount

numeric(18,2)

Total

notes

text nullable

Catatan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Constraint:

quantity > 0
duration > 0
unit_price >= 0
total_amount >= 0

5.24 booking_unit_allocations

Menghubungkan booking item dengan unit fisik.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

booking_item_id

uuid

Booking item

product_unit_id

bigint

Unit

start_at

timestamp

Awal alokasi

end_at

timestamp

Akhir alokasi

status

varchar(30)

Status

allocated_at

timestamp

Waktu alokasi

checked_out_at

timestamp nullable

Waktu keluar

returned_at

timestamp nullable

Waktu kembali

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Status:

reserved
checked_out
returned
cancelled

Index utama:

tenant_id + product_unit_id + start_at + end_at

5.25 booking_status_histories

Menyimpan histori status booking.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

from_status

varchar(30) nullable

Status sebelumnya

to_status

varchar(30)

Status baru

notes

text nullable

Catatan

changed_by

uuid nullable

User

created_at

timestamp

Waktu perubahan

5.26 payments

Menyimpan pembayaran booking.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

payment_number

varchar(100)

Nomor pembayaran

type

varchar(30)

Jenis pembayaran

method

varchar(30)

Metode

amount

numeric(18,2)

Nilai

status

varchar(30)

Status

gateway

varchar(50) nullable

Payment gateway

gateway_reference

varchar(255) nullable

Referensi gateway

proof_path

varchar(255) nullable

Bukti pembayaran

paid_at

timestamp nullable

Waktu dibayar

expired_at

timestamp nullable

Waktu kedaluwarsa

notes

text nullable

Catatan

created_by

uuid nullable

User

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Type:

down_payment
rental_payment
deposit
late_fee
damage_fee
other

Method:

cash
bank_transfer
qris
virtual_account
credit_card
payment_gateway
other

Status:

pending
paid
failed
expired
cancelled
refunded

5.27 payment_transactions

Menyimpan detail request dan response gateway.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

payment_id

uuid

Payment

gateway

varchar(50)

Gateway

transaction_id

varchar(255) nullable

ID transaksi

request_payload

jsonb nullable

Request

response_payload

jsonb nullable

Response

callback_payload

jsonb nullable

Callback

signature_valid

boolean nullable

Validasi signature

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

5.28 deposits

Menyimpan saldo deposit booking.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

amount

numeric(18,2)

Deposit diterima

deducted_amount

numeric(18,2)

Total potongan

refunded_amount

numeric(18,2)

Total refund

remaining_amount

numeric(18,2)

Sisa

status

varchar(30)

Status

held_at

timestamp nullable

Waktu ditahan

refunded_at

timestamp nullable

Waktu refund

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Status:

pending
held
partially_deducted
refunded
forfeited

Unique:

tenant_id + booking_id

5.29 deposit_transactions

Menyimpan histori deposit.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

deposit_id

uuid

Deposit

type

varchar(30)

Jenis transaksi

amount

numeric(18,2)

Nilai

reason

text nullable

Alasan

reference_type

varchar(100) nullable

Tipe referensi

reference_id

uuid nullable

ID referensi

processed_by

uuid nullable

User

created_at

timestamp

Waktu dibuat

Type:

hold
deduction
refund
forfeit
adjustment

5.30 booking_charges

Menyimpan denda atau biaya tambahan.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

booking_item_id

uuid nullable

Item booking

type

varchar(50)

Jenis biaya

description

text

Deskripsi

quantity

numeric(12,2)

Jumlah

unit_amount

numeric(18,2)

Nilai satuan

total_amount

numeric(18,2)

Total

is_deducted_from_deposit

boolean

Dipotong deposit

created_by

uuid nullable

User

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Type:

late_fee
damage_fee
lost_item_fee
missing_accessory_fee
cleaning_fee
fuel_fee
over_mileage_fee
custom

5.31 invoices

Menyimpan invoice booking.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

invoice_number

varchar(100)

Nomor invoice

issue_date

date

Tanggal terbit

due_date

date nullable

Jatuh tempo

subtotal

numeric(18,2)

Subtotal

discount_amount

numeric(18,2)

Diskon

tax_amount

numeric(18,2)

Pajak

total_amount

numeric(18,2)

Total

paid_amount

numeric(18,2)

Dibayar

remaining_amount

numeric(18,2)

Sisa

status

varchar(30)

Status

pdf_path

varchar(255) nullable

File PDF

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Status:

draft
issued
partial
paid
overdue
cancelled
refunded

Unique:

tenant_id + invoice_number

5.32 deliveries

Menyimpan pengiriman dan pengambilan.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

driver_id

uuid nullable

Driver

type

varchar(30)

Delivery atau pickup

address

text

Alamat

latitude

numeric(10,7) nullable

Latitude

longitude

numeric(10,7) nullable

Longitude

scheduled_at

timestamp nullable

Jadwal

completed_at

timestamp nullable

Selesai

recipient_name

varchar(150) nullable

Penerima

recipient_phone

varchar(30) nullable

Telepon penerima

proof_path

varchar(255) nullable

Bukti

status

varchar(30)

Status

notes

text nullable

Catatan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

5.33 booking_checklists

Menyimpan sesi checklist barang.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid

Booking

type

varchar(30)

Check-out atau check-in

completed_by

uuid nullable

User

completed_at

timestamp nullable

Waktu selesai

notes

text nullable

Catatan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Type:

checkout
checkin
inspection

5.34 booking_checklist_items

Menyimpan item checklist.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_checklist_id

uuid

Checklist

product_unit_id

bigint nullable

Unit

name

varchar(200)

Nama pemeriksaan

type

varchar(30)

Tipe input

expected_value

text nullable

Nilai seharusnya

actual_value

text nullable

Nilai aktual

is_passed

boolean nullable

Hasil

notes

text nullable

Catatan

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

5.35 inspection_records

Menyimpan pemeriksaan kondisi barang.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

booking_id

uuid nullable

Booking

product_unit_id

bigint

Unit

type

varchar(30)

Jenis pemeriksaan

previous_condition

varchar(30) nullable

Kondisi sebelumnya

current_condition

varchar(30)

Kondisi terbaru

meter_value

bigint nullable

Kilometer atau hour meter

fuel_level

numeric(5,2) nullable

Persentase bahan bakar

notes

text nullable

Catatan

inspected_by

uuid nullable

User

inspected_at

timestamp

Waktu pemeriksaan

created_at

timestamp

Waktu dibuat

5.36 maintenance_records

Menyimpan aktivitas maintenance unit.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

product_unit_id

bigint

Unit

type

varchar(30)

Jenis maintenance

title

varchar(200)

Judul

description

text nullable

Deskripsi

vendor

varchar(150) nullable

Vendor

cost

numeric(18,2)

Biaya

scheduled_at

timestamp nullable

Jadwal

started_at

timestamp nullable

Mulai

completed_at

timestamp nullable

Selesai

status

varchar(30)

Status

created_by

uuid nullable

User

created_at

timestamp

Waktu dibuat

updated_at

timestamp

Waktu diperbarui

Status:

scheduled
in_progress
completed
cancelled

Type:

service
repair
cleaning
inspection
calibration
other

5.37 product_movements

Menyimpan histori perubahan unit serialized.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid

Tenant

product_unit_id

bigint

Unit

booking_id

uuid nullable

Booking

type

varchar(50)

Tipe movement

from_status

varchar(30) nullable

Status lama

to_status

varchar(30) nullable

Status baru

from_branch_id

bigint nullable

Cabang asal

to_branch_id

bigint nullable

Cabang tujuan

notes

text nullable

Catatan

occurred_at

timestamp

Waktu kejadian

created_by

uuid nullable

User

created_at

timestamp

Waktu dibuat

5.38 audit_logs

Menyimpan audit aktivitas sistem.

Kolom

Tipe

Keterangan

id

uuid

Primary key

tenant_id

uuid nullable

Tenant

user_id

uuid nullable

User

action

varchar(100)

Aksi

auditable_type

varchar(150) nullable

Tipe object

auditable_id

uuid nullable

ID object

old_values

jsonb nullable

Data lama

new_values

jsonb nullable

Data baru

ip_address

varchar(45) nullable

IP

user_agent

text nullable

User agent

created_at

timestamp

Waktu kejadian

Audit log tidak boleh diedit atau dihapus melalui aplikasi biasa.

6. Migration Order

Urutan migration harus mengikuti dependensi foreign key.

6.1 Central Foundation dan Package Migrations

001_publish_create_tenants_table
002_publish_create_domains_table
003_publish_create_plans_table
004_publish_create_plan_features_table
005_publish_create_plan_subscriptions_table
006_publish_create_plan_subscription_usage_table
007_create_tenant_settings_table
008_create_subscription_payments_table

Catatan:

Jangan membuat ulang plans atau plan_subscriptions.

Publish migration package dan review tipe PK/FK sebelum migration pertama.

plan_subscriptions.subscriber_id wajib kompatibel dengan tenants.id.

Aktifkan DatabaseTenancyBootstrapper. CreateDatabase dan tenant migration dipicu oleh provisioning setelah pembayaran terverifikasi; jangan memicunya langsung pada TenantCreated.

6.2 Authentication dan Authorization

006_create_users_table
007_create_roles_table
008_create_permissions_table
009_create_role_permissions_table
010_create_user_roles_table
011_create_personal_access_tokens_table

6.3 Organization

012_create_branches_table
013_create_branch_users_table

Catatan:

Jika users memiliki default branch, foreign key default_branch_id dapat ditambahkan setelah tabel branches dibuat.

6.4 Customer

014_create_customers_table
015_create_customer_documents_table
016_create_customer_addresses_table
017_create_customer_blacklists_table

6.5 Inventory

018_create_categories_table
019_create_products_table
020_create_product_images_table
021_create_product_prices_table
022_create_product_units_table
023_create_inventory_stocks_table
024_create_inventory_stock_movements_table

6.6 Booking

025_create_bookings_table
026_create_booking_items_table
027_create_booking_unit_allocations_table
028_create_booking_status_histories_table

6.7 Payment

029_create_payments_table
030_create_payment_transactions_table
031_create_deposits_table
032_create_deposit_transactions_table
033_create_booking_charges_table
034_create_refunds_table
035_create_invoices_table
036_create_invoice_items_table

6.8 Fulfillment

037_create_deliveries_table
038_create_delivery_status_histories_table
039_create_booking_checklists_table
040_create_booking_checklist_items_table
041_create_inspection_records_table
042_create_inspection_photos_table

6.9 Maintenance dan Movement

043_create_maintenance_records_table
044_create_maintenance_photos_table
045_create_product_movements_table

6.10 System

046_create_notifications_table
047_create_audit_logs_table
048_create_jobs_table
049_create_failed_jobs_table

7. Relationship

7.1 Tenant dan Subscription Relationship

plans 1 ─── N plan_features
plans 1 ─── N plan_subscriptions
tenants 1 ─── N plan_subscriptions # polymorphic subscriber
tenants 1 ─── N domains
tenants 1 ─── N users
tenants 1 ─── N branches
tenants 1 ─── N customers
tenants 1 ─── N categories
tenants 1 ─── N products
tenants 1 ─── N bookings
plan_subscriptions 1 ─── N plan_subscription_usage
plan_features 1 ─── N plan_subscription_usage

7.2 Product Relationship

categories 1 ─── N products
products 1 ─── N product_images
products 1 ─── N product_prices
products 1 ─── N product_units
products 1 ─── N inventory_stocks
branches 1 ─── N product_units
branches 1 ─── N inventory_stocks

7.3 Booking Relationship

customers 1 ─── N bookings
branches 1 ─── N bookings
users 1 ─── N bookings
bookings 1 ─── N booking_items
booking_items 1 ─── N booking_unit_allocations
product_units 1 ─── N booking_unit_allocations
bookings 1 ─── N booking_status_histories

7.4 Payment Relationship

bookings 1 ─── N payments
payments 1 ─── N payment_transactions
bookings 1 ─── 1 deposits
deposits 1 ─── N deposit_transactions
bookings 1 ─── N booking_charges
bookings 1 ─── N invoices
invoices 1 ─── N invoice_items

7.5 Maintenance Relationship

product_units 1 ─── N maintenance_records
product_units 1 ─── N inspection_records
product_units 1 ─── N product_movements

8. Index Strategy

Index harus dibuat berdasarkan pola query, bukan hanya foreign key.

8.1 Tenant Index

Semua tabel tenant wajib memiliki index:

INDEX tenant_id

Untuk tabel besar, gunakan composite index berdasarkan query utama.

8.2 Booking Index

INDEX bookings_tenant_status
(tenant_id, status)

INDEX bookings_tenant_period
(tenant_id, start_at, end_at)

INDEX bookings_tenant_customer
(tenant_id, customer_id)

INDEX bookings_tenant_branch_period
(tenant_id, branch_id, start_at, end_at)

UNIQUE bookings_tenant_number
(tenant_id, booking_number)

8.3 Unit Allocation Index

Index paling penting untuk availability check:

INDEX allocations_unit_period
(tenant_id, product_unit_id, start_at, end_at)

INDEX allocations_booking
(tenant_id, booking_id)

INDEX allocations_item
(tenant_id, booking_item_id)

INDEX allocations_active_status
(tenant_id, product_unit_id, status)

Pada PostgreSQL dapat digunakan partial index:

CREATE INDEX booking_allocations_active_idx
ON booking_unit_allocations (
tenant_id,
product_unit_id,
start_at,
end_at
)
WHERE status IN ('reserved', 'checked_out');

8.4 Product Index

INDEX products_tenant_category
(tenant_id, category_id)

INDEX products_tenant_active
(tenant_id, is_active)

UNIQUE products_tenant_slug
(tenant_id, slug)

UNIQUE products_tenant_sku
(tenant_id, sku)

8.5 Product Unit Index

INDEX product_units_tenant_product
(tenant_id, product_id)

INDEX product_units_tenant_branch_status
(tenant_id, branch_id, status)

INDEX product_units_tenant_product_status
(tenant_id, product_id, status)

UNIQUE product_units_tenant_code
(tenant_id, unit_code)

UNIQUE product_units_tenant_barcode
(tenant_id, barcode)

8.6 Customer Index

INDEX customers_tenant_phone
(tenant_id, phone)

INDEX customers_tenant_email
(tenant_id, email)

INDEX customers_tenant_status
(tenant_id, status)

INDEX customers_tenant_name
(tenant_id, name)

Untuk pencarian nama yang besar, gunakan trigram index PostgreSQL.

CREATE EXTENSION IF NOT EXISTS pg_trgm;

CREATE INDEX customers_name_trgm_idx
ON customers
USING gin (name gin_trgm_ops);

8.7 Payment Index

INDEX payments_tenant_booking
(tenant_id, booking_id)

INDEX payments_tenant_status
(tenant_id, status)

INDEX payments_gateway_reference
(gateway, gateway_reference)

UNIQUE payments_tenant_number
(tenant_id, payment_number)

8.8 Audit Index

INDEX audit_logs_tenant_created
(tenant_id, created_at)

INDEX audit_logs_user_created
(user_id, created_at)

INDEX audit_logs_auditable
(auditable_type, auditable_id)

9. Constraint

9.1 Foreign Key Constraint

Setiap child record harus memiliki parent yang valid.

Contoh:

products.category_id → categories.id
bookings.customer_id → customers.id
booking_items.booking_id → bookings.id
payments.booking_id → bookings.id

9.2 Delete Behavior

Cascade Delete

Digunakan jika child tidak memiliki arti tanpa parent.

Contoh:

booking_items → bookings
booking_status_histories → bookings
product_images → products
role_permissions → roles

Restrict Delete

Digunakan jika parent memiliki histori transaksi.

Contoh:

products tidak boleh hard delete jika sudah digunakan booking
customers tidak boleh hard delete jika memiliki booking
product_units tidak boleh hard delete jika memiliki movement

Null on Delete

Digunakan untuk referensi yang sifatnya opsional.

Contoh:

created_by
verified_by
driver_id
branch_id tertentu

9.3 Check Constraint

Contoh validasi database:

CHECK (total_amount >= 0);
CHECK (paid_amount >= 0);
CHECK (remaining_amount >= 0);
CHECK (quantity > 0);
CHECK (duration > 0);
CHECK (start_at < end_at);
CHECK (deposit_amount >= 0);

Untuk inventory:

CHECK (quantity_total >= 0);
CHECK (quantity_reserved >= 0);
CHECK (quantity_rented >= 0);
CHECK (quantity_maintenance >= 0);
CHECK (quantity_damaged >= 0);
CHECK (quantity_lost >= 0);

9.4 Unique Constraint

Contoh:

tenant_id + booking_number
tenant_id + invoice_number
tenant_id + payment_number
tenant_id + product slug
tenant_id + product SKU
tenant_id + unit_code
tenant_id + branch code
tenant_id + role code

9.5 Tenant Consistency Constraint

Semua relasi dalam transaksi harus berasal dari tenant yang sama.

Contoh:

booking.tenant_id harus sama dengan customer.tenant_id
booking_item.tenant_id harus sama dengan booking.tenant_id
product_unit.tenant_id harus sama dengan booking.tenant_id
payment.tenant_id harus sama dengan booking.tenant_id

Karena foreign key standar tidak selalu memvalidasi tenant yang sama, konsistensi harus dijaga melalui:

Application service

Tenant context

Policy

Database transaction

Composite foreign key pada tabel kritikal

Automated test

10. Pencegahan Double Booking

10.1 Serialized Inventory

Satu unit tidak boleh memiliki alokasi aktif pada periode yang bertabrakan.

Rumus overlap:

existing.start_at < requested.end_at
AND
existing.end_at > requested.start_at

Query:

SELECT EXISTS (
SELECT 1
FROM booking_unit_allocations
WHERE tenant_id = :tenant_id
AND product_unit_id = :product_unit_id
AND status IN ('reserved', 'checked_out')
AND start_at < :requested_end
AND end_at > :requested_start
);

10.2 Quantity Inventory

Untuk quantity-based inventory:

available quantity =
total stock

- total quantity booked pada periode overlap
- maintenance
- damaged
- lost

Query harus menjumlahkan quantity booking aktif pada periode yang bertabrakan.

10.3 Race Condition

Pengecekan biasa belum cukup apabila dua booking dibuat bersamaan.

Gunakan:

Database transaction

Row-level locking

SELECT ... FOR UPDATE

Recheck availability sebelum commit

Idempotency key

Retry saat deadlock

Contoh alur:

BEGIN TRANSACTION

Lock inventory atau unit

Check availability

Create booking

Create booking item

Create allocation

Update stock

COMMIT

10.4 PostgreSQL Exclusion Constraint

Untuk perlindungan lebih kuat pada serialized inventory, PostgreSQL dapat menggunakan exclusion constraint.

CREATE EXTENSION IF NOT EXISTS btree_gist;

ALTER TABLE booking_unit_allocations
ADD CONSTRAINT prevent_overlapping_unit_booking
EXCLUDE USING gist (
product_unit_id WITH =,
tstzrange(start_at, end_at, '[)') WITH &&
)
WHERE (status IN ('reserved', 'checked_out'));

Constraint ini mencegah overlap langsung pada level database.

11. Status Transition

11.1 Booking Status

draft
↓
pending
↓
confirmed
↓
preparing
↓
ready
↓
ongoing
↓
returned
↓
completed

Jalur alternatif:

draft → cancelled
pending → rejected
pending → cancelled
confirmed → cancelled
pending → expired

Status tidak boleh berubah sembarangan.

Contoh:

completed tidak boleh kembali menjadi pending
cancelled tidak boleh menjadi ongoing
returned tidak boleh menjadi ready

11.2 Product Unit Status

available → reserved
reserved → rented
rented → cleaning
rented → available
rented → maintenance
maintenance → available
cleaning → available

Jalur khusus:

available → damaged
available → lost
maintenance → inactive
damaged → maintenance

11.3 Payment Status

pending → paid
pending → failed
pending → expired
paid → refunded
paid → partially_refunded

12. Transaction Boundary

Operasi berikut wajib menggunakan database transaction:

Membuat Booking

Create booking
Create booking item
Check availability
Allocate unit
Reserve inventory
Create invoice
Create status history

Konfirmasi Pembayaran

Update payment
Update booking paid amount
Update remaining amount
Update payment status
Update invoice
Create audit log

Check-Out

Validate booking
Validate payment
Update allocation
Update unit status
Create checklist
Create movement
Update booking status

Pengembalian

Update allocation
Update unit condition
Create inspection
Create charges
Calculate deposit
Update unit status
Update booking status
Create movement

13. Data Integrity Rules

Booking wajib memiliki minimal satu booking item.

Booking item quantity harus lebih dari nol.

Serialized product hanya boleh dialokasikan ke product unit dari produk yang sama.

Product unit harus berasal dari tenant yang sama.

Product unit maintenance tidak dapat dialokasikan.

Customer blacklisted tidak boleh membuat booking aktif.

Booking cancelled tidak boleh memiliki allocation aktif.

Deposit tidak dihitung sebagai revenue.

Refund tidak boleh melebihi pembayaran.

Potongan deposit tidak boleh melebihi saldo deposit.

Produk quantity wajib memiliki inventory stock.

Produk serialized tidak menggunakan quantity stock sebagai sumber utama.

Payment gateway callback harus idempotent.

Invoice number harus unik per tenant.

Booking number harus unik per tenant.

13.1 Package Integration Rules

Tenant adalah subscriber, bukan user owner.

Gunakan satu subscription utama bernama main per tenant.

Sumber plan aktif adalah plan_subscriptions.plan_id, bukan kolom pada tenants.

Limit plan disimpan sebagai plan_features.

Pembayaran subscription berada di luar scope package dan harus ditangani application service.

Webhook billing wajib idempotent dan menyimpan gateway event ID unik.

Queue tenant wajib membawa tenant_id dan menginisialisasi tenancy.

Test wajib mencakup tenant isolation, UUID morph, lifecycle subscription, dan feature limit.

14. Naming Convention

14.1 Table

Gunakan plural snake_case.

product_units
booking_items
payment_transactions

14.2 Column

Gunakan snake_case.

tenant_id
booking_number
start_at
total_amount

14.3 Foreign Key

Gunakan format:

{entity}\_id

Contoh:

tenant_id
booking_id
product_unit_id

14.4 Boolean

Gunakan awalan:

is*
has*
can\_

Contoh:

is_active
is_primary
is_verified

14.5 Timestamp

Gunakan akhiran:

\_at

Contoh:

confirmed_at
completed_at
refunded_at

15. Data Retention

Data Transaksi

Data berikut tidak boleh dihapus permanen secara normal:

Booking

Payment

Invoice

Deposit transaction

Product movement

Audit log

Inspection

Data Master

Data master menggunakan soft delete:

Product

Product unit

Customer

Category

Branch

User

Audit Log

Audit log minimal disimpan selama:

1–5 tahun

Tergantung kebijakan paket dan kebutuhan tenant.

16. Backup dan Recovery

Backup

Full backup database harian

Incremental backup bila tersedia

Point-in-time recovery untuk production

Backup file storage terpisah

Backup terenkripsi

Backup disimpan di lokasi berbeda

Retention

Contoh:

Backup harian: 14 hari
Backup mingguan: 8 minggu
Backup bulanan: 12 bulan

Recovery

Target awal:

RPO: 24 jam
RTO: 4 jam

Untuk enterprise dapat ditingkatkan.

17. Database Performance Guidelines

Semua query operasional wajib menggunakan tenant_id.

Hindari query tanpa pagination.

Gunakan eager loading untuk relasi yang diperlukan.

Hindari N+1 query.

Report berat dijalankan melalui queue.

Dashboard dapat menggunakan cache.

Gunakan materialized view untuk analytics besar.

Archive audit log lama bila volume meningkat.

Gunakan read replica jika trafik baca tinggi.

Gunakan database connection pooling.

18. MVP Database Scope

Tabel minimum untuk MVP:

tenants
users
roles
permissions
branches
customers
categories
products
product_images
product_prices
product_units
inventory_stocks
bookings
booking_items
booking_unit_allocations
booking_status_histories
payments
deposits
booking_charges
invoices
booking_checklists
booking_checklist_items
maintenance_records
product_movements
audit_logs

Tabel yang dapat ditunda:

domains
customer_addresses
payment_transactions
refunds
delivery_status_histories
inspection_photos
maintenance_photos
notifications

19. Kesimpulan

Database Sewantara menggunakan pendekatan:

PostgreSQL
Mixed UUID dan BIGINT Primary Key
Shared Database
Shared Schema
stancl/tenancy Single-Database Isolation
Tenant as Subscription Subscriber
laravelcm/laravel-subscriptions Plan & Feature Management
Transactional Booking
Snapshot Transaction Data
Serialized dan Quantity Inventory
Audit Trail
Soft Delete

Alur data utama:

Tenant
→ Branch
→ Product
→ Product Unit atau Stock
→ Customer
→ Booking
→ Booking Item
→ Allocation
→ Payment
→ Check-Out
→ Return
→ Inspection
→ Deposit Settlement
→ Completed

Prioritas utama implementasi database adalah:

Menjamin isolasi data tenant.

Mencegah double booking.

Menjaga histori transaksi.

Memisahkan deposit dari pendapatan.

Mendukung serialized dan quantity inventory.

Menjamin konsistensi melalui transaction dan constraint.

Menyediakan index sesuai pola query.

Menyimpan audit trail untuk aktivitas penting.
