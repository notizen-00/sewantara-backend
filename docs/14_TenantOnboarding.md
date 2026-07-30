# Tenant Onboarding

## Overview

Tenant Onboarding tidak hanya bertugas melakukan registrasi tenant, tetapi juga melakukan provisioning seluruh kebutuhan bisnis agar tenant siap menerima booking pertama tanpa konfigurasi manual yang rumit.

Target utama onboarding adalah mengubah proses:

```
Register
↓
Login
```

menjadi

```
Register
↓
Business Setup
↓
Rental Configuration
↓
Inventory Setup
↓
Booking Configuration
↓
Payment Configuration
↓
Go Live
```

Setelah onboarding selesai, tenant dapat langsung menerima booking dari customer.

---

# Philosophy

Sewantara bukan platform khusus rental mobil atau rental PlayStation.

Sewantara adalah **Rental Engine** yang dapat menangani berbagai model bisnis rental menggunakan konfigurasi, bukan hardcode.

Contoh:

| Business      | Rental Model | Booking Strategy |
| ------------- | ------------ | ---------------- |
| Rental Mobil  | Per Day      | Date Range       |
| Rental Motor  | Per Day      | Date Range       |
| Rental Kamera | Per Day      | Date Range       |
| Rental PS     | Per Hour     | Queue            |
| Karaoke       | Per Hour     | Queue            |
| Lapangan      | Session      | Session          |
| Gedung        | Session      | Session          |

Frontend maupun backend akan menyesuaikan berdasarkan konfigurasi tenant.

---

# Onboarding Flow

## Step 1

## Business Setup

Tenant mengisi informasi bisnis.

| Field           | Description          |
| --------------- | -------------------- |
| Business Name   | Nama usaha           |
| Business Type   | Template bisnis      |
| Timezone        | Default Asia/Jakarta |
| Currency        | Default IDR          |
| Branch Name     | Cabang utama         |
| Operating Hours | Jam operasional      |

---

## Step 2

## Select Business Template

Tenant memilih template usaha.

Contoh:

```
🚗 Rental Mobil

🏍 Rental Motor

🎮 Rental PlayStation

📷 Rental Kamera

🏕 Rental Camping

🏢 Rental Gedung

⚽ Lapangan

🎤 Studio Musik

🛠 Custom
```

Template hanya digunakan sebagai default configuration.

Tenant tetap dapat mengubah konfigurasi kapan saja.

---

## Step 3

## Rental Configuration

Setelah memilih template, sistem otomatis membuat konfigurasi bisnis.

Contoh:

Rental PlayStation

```
Rental Model

PER_HOUR

Booking Strategy

QUEUE

Allocation Strategy

AUTO_ASSIGN

Waiting List

Enabled

Realtime Availability

Enabled

Extend Booking

Enabled
```

Rental Mobil

```
Rental Model

PER_DAY

Booking Strategy

DATE_RANGE

Allocation Strategy

AUTO_ASSIGN

Waiting List

Disabled

Realtime Availability

Enabled

Extend Booking

Disabled
```

Tenant dapat mengubah konfigurasi ini sebelum Go Live.

---

## Step 4

## Inventory Setup

Tenant membuat unit pertama.

Contoh Rental PS

```
PS 1

PS 2

PS 3

PS VIP
```

Contoh Rental Mobil

```
Avanza

Brio

Innova
```

---

## Step 5

## Pricing

Tenant menentukan harga.

Contoh

Rental PS

```
Weekday

10000 / hour

Weekend

15000 / hour
```

Rental Mobil

```
350000 / day
```

---

## Step 6

## Booking Configuration

Tenant memilih bagaimana customer melakukan booking.

| Configuration  | Description                                    |
| -------------- | ---------------------------------------------- |
| Online Booking | Customer dapat booking melalui aplikasi        |
| Walk In        | Booking langsung di lokasi                     |
| Waiting List   | Customer masuk antrean jika penuh              |
| Auto Assign    | Sistem memilih unit otomatis                   |
| Manual Assign  | Admin memilih unit                             |
| Auto Reminder  | Reminder sebelum booking dimulai               |
| Auto Cancel    | Booking otomatis dibatalkan jika tidak dibayar |

---

## Step 7

## Payment Configuration

Tenant menentukan metode pembayaran.

Contoh

```
Cash

Transfer

Midtrans

Deposit

Pay Later
```

---

## Step 8

## Go Live

Sistem melakukan validasi.

Checklist

```
✓ Business

✓ Inventory

✓ Pricing

✓ Booking

✓ Payment

✓ Branch

✓ Subscription
```

Jika seluruh validasi berhasil maka tenant berstatus

```
ACTIVE
```

dan dashboard dapat digunakan.

---

# Business Templates

Business Template merupakan preset konfigurasi.

Template bukan modul.

Template hanya mengisi konfigurasi awal tenant.

Misalnya

Rental Mobil

```
Rental Model

PER_DAY

Booking Strategy

DATE_RANGE

Allocation

AUTO_ASSIGN

Availability

REALTIME
```

Rental PlayStation

```
Rental Model

PER_HOUR

Booking Strategy

QUEUE

Allocation

AUTO_ASSIGN

Slot Duration

60 Minutes

Waiting List

Enabled

Realtime Availability

Enabled
```

Lapangan

```
Rental Model

SESSION

Booking Strategy

SESSION

Session Duration

120 Minutes

Allocation

MANUAL
```

---

# Rental Engine

Seluruh proses booking menggunakan satu engine.

Engine membaca konfigurasi tenant.

Contoh

```
Rental Model

PER_DAY

↓

Date Range Booking
```

atau

```
Rental Model

PER_HOUR

↓

Hourly Booking
```

atau

```
Rental Model

SESSION

↓

Session Booking
```

Backend tidak boleh melakukan pengecekan berdasarkan nama kategori produk.

Contoh yang SALAH

```php
if ($category == 'ps') {
    ...
}
```

Gunakan konfigurasi tenant.

Contoh yang BENAR

```php
switch ($tenant->booking_strategy) {
    case BookingStrategy::QUEUE:
        ...
        break;

    case BookingStrategy::DATE_RANGE:
        ...
        break;

    case BookingStrategy::SESSION:
        ...
        break;
}
```

---

# Suggested Database Structure

## business_templates

Preset bawaan Sewantara.

| Column      | Type   |
| ----------- | ------ |
| id          | bigint |
| code        | string |
| name        | string |
| description | text   |

---

## tenant_business_profiles

Informasi bisnis tenant.

| Column          | Type   |
| --------------- | ------ |
| tenant_id       | uuid   |
| template_id     | bigint |
| business_name   | string |
| timezone        | string |
| currency        | string |
| operating_hours | json   |

---

## rental_configurations

Konfigurasi utama Rental Engine.

| Column                | Type    |
| --------------------- | ------- |
| tenant_id             | uuid    |
| rental_model          | enum    |
| booking_strategy      | enum    |
| allocation_strategy   | enum    |
| slot_duration         | integer |
| enable_waiting_list   | boolean |
| allow_walk_in         | boolean |
| allow_online_booking  | boolean |
| allow_extend_booking  | boolean |
| realtime_availability | boolean |

Rental Engine hanya membaca tabel ini.

Tidak boleh ada pengecekan berdasarkan kategori produk.

---

# Future Business Templates

Template dapat terus bertambah tanpa mengubah Rental Engine.

Contoh:

- Rental Mobil
- Rental Motor
- Rental PS
- Rental Kamera
- Rental Drone
- Rental Camping
- Rental Sound System
- Rental Studio
- Rental Gedung
- Rental Coworking Space
- Rental Alat Kesehatan
- Rental Bayi
- Rental Wedding
- Custom Template

Seluruh template hanya menghasilkan konfigurasi awal pada `rental_configurations`.

---

# Implementation Decision

Implementasi Sewantara menggunakan pemisahan berikut:

- `business_templates` berada di central database sebagai preset platform.
- `tenant_business_profiles` menyimpan snapshot profil dan template pada
  database tenant.
- `rental_configurations` menjadi satu-satunya sumber keputusan Rental Engine.
- `tenant_onboarding` menyimpan current step, completed steps, status, dan
  waktu Go Live.
- `tenant_payment_methods` menyimpan metode pembayaran tenant; configuration
  sensitif disimpan terenkripsi.

Tenant baru berstatus `onboarding` setelah provisioning. Owner dapat login dan
mengakses endpoint setup, tetapi customer, booking, payment operasional,
maintenance, availability, dan reporting tetap membutuhkan status `active`.

Rental Engine saat ini memutuskan:

- pricing type dari rental model;
- period date range atau slot;
- validasi durasi queue/session;
- izin online atau walk-in booking;
- realtime availability;
- manual atau automatic unit allocation.

Business template hanya menyalin default configuration. Perubahan template
platform di masa depan tidak mengubah konfigurasi tenant yang sudah berjalan.
