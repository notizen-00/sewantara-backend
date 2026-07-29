# Development Guide

## Sewantara — Universal Rental Management SaaS

**Versi:** 1.0  
**Status:** Draft  
**Backend:** Laravel 12  
**Frontend:** Nuxt 4  
**Mobile:** Flutter  
**Database:** PostgreSQL

---

# 1. Tujuan

Dokumen ini menjadi pedoman standar pengembangan seluruh aplikasi Sewantara.

Seluruh developer wajib mengikuti standar ini agar:

- Konsisten.
- Mudah dipelihara.
- Mudah diuji.
- Mudah direview.
- Mudah dikembangkan.
- Memiliki kualitas kode yang tinggi.
- Mendukung kerja tim.

Dokumen ini berlaku untuk:

- Backend Developer
- Frontend Developer
- Mobile Developer
- QA Engineer
- DevOps Engineer
- Technical Lead

---

# 2. Coding Standard

## 2.1 PSR-12

Seluruh kode PHP wajib mengikuti standar **PSR-12**.

Referensi:

https://www.php-fig.org/psr/psr-12/

Aturan utama:

- Menggunakan 4 spasi.
- Tidak menggunakan tab.
- Maksimal satu class per file.
- Namespace sesuai struktur folder.
- Satu statement per baris.
- Brace mengikuti PSR-12.
- Menggunakan `declare(strict_types=1);`.

Contoh:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

final class CreateBookingAction
{
    public function execute(): void
    {
    }
}
```

---

## 2.2 Strict Types

Seluruh file PHP wajib menggunakan:

```php
declare(strict_types=1);
```

Tidak diperbolehkan menghilangkan strict type.

---

## 2.3 Type Hint

Seluruh parameter harus memiliki type.

Contoh:

```php
public function execute(
    CreateBookingData $data
): Booking
```

Tidak diperbolehkan:

```php
public function execute($data)
```

---

## 2.4 Return Type

Semua method wajib memiliki return type.

Contoh:

```php
public function calculate(): Money
```

atau

```php
public function store(): void
```

---

## 2.5 Constructor Property Promotion

Gunakan Constructor Property Promotion.

Contoh:

```php
public function __construct(
    private BookingRepository $repository,
    private PricingService $pricing,
) {
}
```

---

## 2.6 Readonly

Gunakan `readonly` jika object immutable.

```php
final readonly class Money
{
}
```

---

# 3. SOLID Principle

Seluruh business logic mengikuti SOLID.

---

## 3.1 Single Responsibility Principle (SRP)

Satu class hanya memiliki satu tanggung jawab.

✔ Benar

```text
CreateBookingAction
```

❌ Salah

```text
BookingService
```

yang:

- create booking
- payment
- notification
- report
- invoice

---

## 3.2 Open Closed Principle (OCP)

Class terbuka untuk extension.

Tetapi tertutup untuk modification.

Contoh:

```text
PricingStrategy
```

Implementasi:

- HourlyPricing
- DailyPricing
- WeeklyPricing

Tidak perlu mengubah kode lama.

---

## 3.3 Liskov Substitution Principle (LSP)

Implementasi interface harus dapat saling menggantikan.

Contoh:

```text
PaymentGateway
```

Implementasi:

```text
MidtransGateway
XenditGateway
ManualGateway
```

Semuanya dapat digunakan tanpa mengubah kode.

---

## 3.4 Interface Segregation Principle (ISP)

Interface kecil lebih baik daripada interface besar.

✔

```php
interface PaymentGateway
{
    public function createCharge();
}
```

❌

```php
interface EverythingGateway
{
    ...
}
```

---

## 3.5 Dependency Inversion Principle (DIP)

Business layer bergantung pada interface.

Bukan implementation.

✔

```php
BookingRepository
```

❌

```php
EloquentBookingRepository
```

langsung pada Action.

---

# 4. Clean Code

Developer wajib mengikuti Clean Code.

---

## Penamaan

Gunakan nama yang jelas.

✔

```text
CreateBookingAction
```

❌

```text
DoBooking
```

---

## Function

Satu fungsi satu tujuan.

Ideal:

```text
10-30 baris
```

---

## Variable

Gunakan nama yang jelas.

✔

```php
$bookingTotal
```

❌

```php
$t
```

---

## Magic Number

Dilarang.

✔

```php
const MAX_UPLOAD = 5;
```

❌

```php
if ($size > 5)
```

---

## Comment

Comment hanya digunakan bila benar-benar diperlukan.

Kode harus dapat menjelaskan dirinya sendiri.

---

# 5. Laravel Best Practice

Gunakan:

- Form Request
- API Resource
- Action
- DTO
- Repository
- Policy
- Event
- Queue

Hindari:

- Fat Controller
- Fat Model
- Helper untuk business logic

---

# 6. Git Flow

Repository menggunakan **Git Flow**.

---

## Branch

```text
main
```

Production.

```text
develop
```

Development.

```text
feature/*
```

Fitur baru.

```text
bugfix/*
```

Perbaikan bug.

```text
hotfix/*
```

Perbaikan production.

```text
release/*
```

Persiapan release.

---

## Contoh

```text
feature/booking
feature/payment
feature/subscription
feature/product-category

bugfix/login
bugfix/payment

hotfix/payment-timeout
```

---

## Merge

Feature

↓

Develop

↓

Release

↓

Main

---

Tidak diperbolehkan merge langsung ke:

```text
main
```

---

# 7. Conventional Commit

Seluruh commit mengikuti Conventional Commit.

Format:

```text
type(scope): message
```

---

## feat

```text
feat(booking): add booking allocation
```

---

## fix

```text
fix(payment): fix webhook validation
```

---

## refactor

```text
refactor(inventory): simplify allocation service
```

---

## perf

```text
perf(report): optimize dashboard query
```

---

## docs

```text
docs(api): update booking endpoint
```

---

## test

```text
test(booking): add booking feature tests
```

---

## style

```text
style(product): apply pint formatting
```

---

## chore

```text
chore(ci): update github actions
```

---

## build

```text
build(docker): update php image
```

---

# 8. Pull Request Standard

Setiap PR wajib:

- Menggunakan template.
- Memiliki deskripsi.
- Menjelaskan perubahan.
- Menyertakan screenshot (UI).
- Menyertakan migration bila ada.
- Menyertakan test.

Checklist:

```text
☐ PSR-12
☐ Pint Passed
☐ PHPUnit Passed
☐ Pint Format
☐ No Debug Code
☐ No TODO
☐ Documentation Updated
☐ Migration Included
```

---

# 9. Code Review

Reviewer memeriksa:

- Arsitektur.
- SOLID.
- Security.
- Query.
- Validation.
- Tenant Isolation.
- Subscription Validation.
- Test.
- Naming.
- Performance.

Minimal:

```text
1 Approval
```

Sebelum merge.

---

# 10. Testing Standard

Minimal coverage:

| Layer        | Test             |
| ------------ | ---------------- |
| Domain       | Unit Test        |
| Action       | Unit Test        |
| Repository   | Integration Test |
| API          | Feature Test     |
| Payment      | Feature Test     |
| Tenant       | Feature Test     |
| Subscription | Feature Test     |

Semua bug yang ditemukan wajib memiliki regression test.

---

# 11. Code Formatting

Backend:

```text
Laravel Pint
```

Frontend:

```text
ESLint
Prettier
```

Flutter:

```text
dart format
flutter analyze
```

CI akan gagal apabila formatting tidak sesuai.

---

# 12. Security Guideline

Developer wajib:

- Validasi seluruh input.
- Gunakan Policy.
- Gunakan Permission.
- Gunakan Tenant Middleware.
- Gunakan Subscription Middleware.
- Hindari SQL Injection.
- Hindari Mass Assignment.
- Hindari Hardcoded Secret.

Semua endpoint tenant wajib memastikan:

```text
Authenticated User
↓

Tenant Validation
↓

Subscription Validation
↓

Permission Validation
↓

Business Logic
```

---

# 13. Documentation

Setiap fitur baru wajib memperbarui:

- PRD
- API Specification
- Database Design
- Architecture
- Module Guide
- README
- Changelog

---

# 14. Development Workflow

```text
Create Issue
      ↓
Create Feature Branch
      ↓
Development
      ↓
Unit Test
      ↓
Feature Test
      ↓
Laravel Pint
      ↓
Commit (Conventional Commit)
      ↓
Push
      ↓
Pull Request
      ↓
Code Review
      ↓
Merge to Develop
      ↓
Release
      ↓
Merge to Main
      ↓
Production Deploy
```

---

# 15. Definition of Done

Sebuah fitur dianggap selesai apabila:

- Mengikuti PSR-12.
- Mengikuti SOLID.
- Mengikuti Clean Architecture.
- Menggunakan Action Pattern.
- Menggunakan DTO jika diperlukan.
- Menggunakan Repository pada domain penting.
- Menggunakan Tenant Context (`stancl/tenancy`).
- Menggunakan Subscription Validation (`laravelcm/laravel-subscriptions`).
- Unit Test lulus.
- Feature Test lulus.
- Pint lulus.
- Dokumentasi diperbarui.
- Pull Request disetujui.
- Tidak ada TODO atau debug code yang tertinggal.
