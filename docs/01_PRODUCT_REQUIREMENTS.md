# Product Requirements Document (PRD)

# Sewantara

### Universal Rental Management SaaS

Version : 1.0

Status : Draft

Author : Sewantara Team

---

# Table of Contents

1. Introduction
2. Product Vision
3. Problem Statement
4. Goals
5. Success Metrics
6. Target Users
7. User Persona
8. Business Scope
9. Business Process
10. User Journey
11. Product Features
12. Functional Requirements
13. Non Functional Requirements
14. Module Breakdown
15. User Story
16. Acceptance Criteria
17. Business Rules
18. MVP Scope
19. Out of Scope
20. Product Roadmap

---

# 1. Introduction

## Background

Sewantara merupakan platform Software as a Service (SaaS) yang dirancang untuk membantu berbagai jenis bisnis rental mengelola operasional secara digital.

Berbeda dengan aplikasi rental yang hanya dibuat untuk satu industri tertentu, Sewantara menggunakan pendekatan modular sehingga dapat digunakan oleh:

- Rental Mobil
- Rental Motor
- Rental Kamera
- Rental Drone
- Rental PS
- Rental Tenda
- Rental Sound System
- Rental Alat Berat
- Rental Medical Equipment
- Rental Event
- Rental Camping
- dan berbagai jenis rental lainnya.

---

# 2. Product Vision

## Vision

Menjadi platform rental management terbaik di Indonesia yang dapat digunakan oleh seluruh jenis bisnis rental.

## Mission

- Mengurangi pencatatan manual
- Menghilangkan double booking
- Mempermudah pembayaran
- Mempermudah monitoring inventory
- Menyediakan dashboard bisnis
- Mempercepat proses operasional rental

---

# 3. Problem Statement

Saat ini mayoritas bisnis rental masih menggunakan:

- WhatsApp
- Microsoft Excel
- Buku Tulis
- Google Spreadsheet

Permasalahan yang muncul:

- Double Booking
- Barang hilang
- Jadwal tidak jelas
- Sulit mengetahui stok
- Tidak ada histori barang
- Tidak ada laporan
- Sulit mengelola cabang

---

# 4. Goals

## Business Goals

- Memiliki platform SaaS
- Mendukung Multi Tenant
- Mendukung White Label
- Mendukung Marketplace di masa depan

## Product Goals

- Inventory Management
- Booking Management
- Payment Management
- Customer Management
- Report Management

---

# 5. Success Metrics

## KPI

- Booking Success Rate > 98%

- Zero Double Booking

- Dashboard Loading < 3 detik

- Availability Check < 1 detik

- 95% transaksi tercatat dalam sistem

---

# 6. Target Users

## Super Admin

Mengelola platform.

## Owner

Mengelola bisnis rental.

## Admin

Mengelola transaksi.

## Warehouse

Mengelola inventory.

## Cashier

Mengelola pembayaran.

## Driver

Mengelola pengiriman.

## Customer

Melakukan booking.

---

# 7. User Persona

## Persona 1

Nama

Budi

Usaha

Rental Mobil

Masalah

Sering double booking.

Harapan

Booking otomatis.

---

## Persona 2

Nama

Andi

Usaha

Rental Kamera

Masalah

Sulit mengetahui kamera yang sedang disewa.

Harapan

Realtime Inventory.

---

## Persona 3

Nama

Rina

Usaha

Rental Tenda

Masalah

Jumlah stok tidak sinkron.

Harapan

Quantity Inventory.

---

# 8. Business Scope

## Included

- Booking
- Inventory
- Customer
- Payment
- Invoice
- QR
- Maintenance
- Dashboard
- Report

## Excluded

- Marketplace
- Accounting
- ERP
- CRM
- HR

---

# 9. Business Process

Customer

↓

Browse Product

↓

Check Availability

↓

Booking

↓

Payment

↓

Confirmation

↓

Check Out

↓

Rental

↓

Return

↓

Inspection

↓

Deposit Refund

↓

Completed

---

# 10. User Journey

## Owner

Login

↓

Dashboard

↓

Inventory

↓

Booking

↓

Report

---

## Customer

Browse

↓

Booking

↓

Payment

↓

Tracking

↓

Return

---

# 11. Product Features

## Authentication

- Login
- Register
- Forgot Password

---

## Tenant

- Create Tenant
- Subscription
- Domain

---

## User

- CRUD
- Role
- Permission

---

## Inventory

- Category
- Product
- Product Unit
- Barcode
- QR Code

---

## Booking

- Calendar
- Booking
- Allocation
- Availability

---

## Payment

- Invoice
- Deposit
- Refund
- Late Fee

---

## Customer

- CRUD
- History
- Blacklist

---

## Dashboard

- Revenue
- Booking
- Inventory

---

## Report

- Revenue
- Booking
- Inventory
- Customer

---

# 12. Functional Requirements

## Inventory

System harus dapat:

- Menambah barang
- Mengedit barang
- Menghapus barang
- Upload foto
- Generate QR
- Generate Barcode

---

## Booking

System harus dapat:

- Membuat booking

- Mengubah booking

- Cancel booking

- Check availability

- Auto Allocation

- Prevent Double Booking

---

## Payment

System harus dapat:

- DP

- Full Payment

- Refund

- Deposit

- Late Fee

---

# 13. Non Functional Requirements

Performance

Availability

Scalability

Security

Logging

Monitoring

Backup

Recovery

Accessibility

Responsive

---

# 14. Module Breakdown

Auth

Inventory

Booking

Payment

Customer

Maintenance

Notification

Report

Subscription

Settings

---

# 15. User Story

## Owner

Sebagai owner saya ingin melihat dashboard sehingga saya mengetahui kondisi bisnis.

---

## Admin

Sebagai admin saya ingin membuat booking sehingga transaksi dapat diproses.

---

## Customer

Sebagai customer saya ingin melihat barang yang tersedia sehingga saya dapat melakukan booking.

---

# 16. Acceptance Criteria

Booking berhasil dibuat.

↓

Tidak terjadi overlap.

↓

Inventory otomatis berubah.

↓

Invoice otomatis dibuat.

↓

Payment dapat dicatat.

↓

Status booking berubah.

---

# 17. Business Rules

BR-001

Maintenance tidak dapat dibooking.

---

BR-002

Overlap ditolak.

---

BR-003

Deposit bukan revenue.

---

BR-004

Customer blacklist tidak dapat booking.

---

BR-005

Booking Completed apabila:

- Payment Paid

- Barang Return

- Inspection Selesai

---

# 18. MVP Scope

Authentication

Inventory

Booking

Payment

Customer

Dashboard

Report

Invoice

QR

---

# 19. Out of Scope

Marketplace

AI

Dynamic Pricing

IoT

Accounting

CRM

POS

ERP

---

# 20. Product Roadmap

## Phase 1

Core Rental

Inventory

Booking

Payment

Dashboard

---

## Phase 2

Website Booking

Payment Gateway

Notification

Maintenance

---

## Phase 3

Marketplace

Mobile Customer

Analytics

White Label

AI Recommendation

IoT
