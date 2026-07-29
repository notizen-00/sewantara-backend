# PRODUCT REQUIREMENTS DOCUMENT

## Aplikasi Rental SaaS Multi-Bisnis

**Nama sementara produk:** RentFlow
**Versi dokumen:** 1.0
**Status:** Draft MVP
**Platform:** Web Admin, Web Booking Customer, dan Mobile Staff pada tahap lanjutan
**Model bisnis:** SaaS Multi-Tenant

---

## 1. Ringkasan Produk

RentFlow adalah aplikasi SaaS untuk membantu bisnis rental mengelola barang, unit, pelanggan, booking, pembayaran, pengambilan, pengembalian, denda, dan laporan dalam satu sistem.

Aplikasi dirancang agar dapat digunakan oleh berbagai jenis usaha rental, antara lain:

- Rental mobil
- Rental motor
- Rental tenda
- Rental kursi dan perlengkapan acara
- Rental kamera
- Rental drone
- Rental PlayStation
- Rental laptop
- Rental alat berat
- Rental alat camping
- Rental pakaian
- Rental alat kesehatan
- Rental perlengkapan bayi

Setiap bisnis rental terdaftar sebagai tenant dan memiliki data, pengguna, cabang, barang, transaksi, serta pengaturan yang terpisah.

---

## 2. Latar Belakang

Banyak usaha rental masih mengelola operasional menggunakan WhatsApp, buku catatan, spreadsheet, atau aplikasi sederhana yang tidak terintegrasi.

Masalah yang sering terjadi:

- Double booking
- Jadwal rental tidak terpantau
- Stok barang tidak akurat
- Sulit mengetahui barang sedang disewa
- Riwayat barang tidak tercatat
- Pembayaran dan deposit sulit dipantau
- Pengembalian terlambat tidak terdeteksi
- Kerusakan barang tidak terdokumentasi
- Data pelanggan tersebar
- Laporan pendapatan dibuat secara manual
- Sulit mengelola lebih dari satu cabang

RentFlow hadir sebagai sistem rental universal yang dapat dikonfigurasi sesuai jenis usaha tenant.

---

## 3. Tujuan Produk

Tujuan utama RentFlow adalah:

1. Menyediakan satu aplikasi yang dapat digunakan oleh berbagai jenis usaha rental.
2. Mengurangi risiko double booking.
3. Menyederhanakan pengelolaan stok dan unit barang.
4. Mempermudah proses booking hingga pengembalian.
5. Mencatat pembayaran, deposit, denda, dan refund.
6. Memberikan informasi ketersediaan barang secara real-time.
7. Menyediakan website booking untuk setiap tenant.
8. Membantu owner memantau operasional dan pendapatan.
9. Mendukung usaha rental dengan satu atau banyak cabang.
10. Menyediakan sistem yang dapat dikembangkan menjadi marketplace rental.

---

## 4. Target Pengguna

### 4.1 Super Admin SaaS

Super Admin adalah pengelola platform RentFlow.

Tanggung jawab:

- Mengelola tenant
- Mengelola paket berlangganan
- Mengaktifkan atau menonaktifkan tenant
- Memantau penggunaan sistem
- Mengelola pembayaran langganan
- Mengelola fitur global
- Melihat log dan aktivitas tenant
- Menangani dukungan pelanggan

### 4.2 Owner Rental

Owner adalah pemilik bisnis rental.

Tanggung jawab:

- Mengatur profil bisnis
- Mengelola cabang
- Mengelola staf
- Mengelola barang dan unit
- Melihat dashboard
- Melihat laporan
- Mengatur harga
- Mengatur deposit dan denda
- Menyetujui booking
- Memantau pembayaran

### 4.3 Admin

Admin menangani operasional harian.

Tanggung jawab:

- Mengelola pelanggan
- Membuat booking
- Mengonfirmasi booking
- Mengelola pembayaran
- Mengatur pengambilan
- Mengatur pengembalian
- Membuat invoice
- Mencatat denda dan kerusakan

### 4.4 Staff Gudang

Staff gudang bertanggung jawab terhadap barang.

Tanggung jawab:

- Memeriksa barang
- Menyiapkan barang
- Scan QR atau barcode
- Menyerahkan barang
- Menerima pengembalian
- Mengubah kondisi barang
- Mencatat barang rusak atau hilang

### 4.5 Kasir

Kasir bertanggung jawab terhadap transaksi pembayaran.

Tanggung jawab:

- Mencatat pembayaran
- Menerima pembayaran tunai
- Memverifikasi transfer
- Mencatat deposit
- Memproses refund
- Mencetak bukti pembayaran

### 4.6 Driver

Driver bertanggung jawab terhadap pengiriman dan pengambilan barang.

Tanggung jawab:

- Melihat jadwal pengiriman
- Melihat alamat pelanggan
- Mengubah status pengiriman
- Mengunggah bukti serah terima
- Mencatat barang yang dikirim

### 4.7 Customer

Customer adalah penyewa barang.

Customer dapat:

- Melihat katalog
- Memilih jadwal
- Mengecek ketersediaan
- Membuat booking
- Mengunggah dokumen identitas
- Melakukan pembayaran
- Melihat status booking
- Mengunduh invoice
- Melihat riwayat rental

---

## 5. Ruang Lingkup Produk

RentFlow terdiri dari beberapa bagian utama:

### 5.1 SaaS Management

Digunakan oleh Super Admin untuk mengelola platform dan tenant.

### 5.2 Tenant Admin Panel

Digunakan oleh owner dan staf untuk mengelola bisnis rental.

### 5.3 Customer Booking Website

Website publik tenant untuk melihat dan menyewa barang.

### 5.4 Staff Mobile App

Aplikasi mobile untuk scan barang, serah terima, dan pengembalian.

Staff Mobile App tidak termasuk prioritas MVP pertama.

---

## 6. Konsep Multi-Tenant

Setiap bisnis rental merupakan satu tenant.

Contoh:

```text
mobiljember.rentflow.id
tendamakmur.rentflow.id
kamerapro.rentflow.id
```

Tenant juga dapat menggunakan domain sendiri.

```text
booking.rentalmobiljember.com
```

Setiap tenant memiliki data terpisah, meliputi:

- User
- Cabang
- Pelanggan
- Kategori
- Produk
- Unit barang
- Booking
- Pembayaran
- Deposit
- Laporan
- Pengaturan

Tenant tidak boleh dapat mengakses data tenant lain.

---

## 7. Jenis Inventaris

Sistem mendukung dua jenis inventaris.

### 7.1 Serialized Inventory

Setiap unit barang dicatat secara individual.

Contoh:

```text
Produk: Toyota Avanza 2024

Unit:
AVZ-001 — P 1234 AB
AVZ-002 — P 5678 CD
AVZ-003 — P 9012 EF
```

Cocok untuk:

- Mobil
- Motor
- Kamera
- Drone
- Laptop
- PlayStation
- Proyektor
- Alat berat

Setiap unit memiliki:

- Kode unit
- Serial number
- Nomor polisi
- Barcode
- QR Code
- Cabang
- Kondisi
- Status
- Harga pembelian
- Catatan

### 7.2 Quantity Inventory

Barang dikelola berdasarkan jumlah stok.

Contoh:

```text
Produk: Kursi Futura
Total stok: 500
Tersedia: 350
Disewa: 150
```

Cocok untuk:

- Kursi
- Meja
- Tenda
- Kabel
- Peralatan catering
- Peralatan dekorasi
- Peralatan camping

---

## 8. Status Barang

Status unit barang:

- Available
- Reserved
- Rented
- Maintenance
- Cleaning
- Damaged
- Lost
- Inactive

### Available

Barang tersedia untuk disewa.

### Reserved

Barang sudah dialokasikan untuk booking mendatang.

### Rented

Barang sedang disewa pelanggan.

### Maintenance

Barang sedang diperbaiki atau diservis.

### Cleaning

Barang sedang dibersihkan.

### Damaged

Barang mengalami kerusakan dan tidak dapat disewa.

### Lost

Barang dinyatakan hilang.

### Inactive

Barang tidak lagi digunakan dalam operasional.

---

## 9. Modul Produk

### 9.1 Kategori

Tenant dapat membuat kategori dan subkategori.

Contoh:

```text
Kendaraan
├── Mobil
├── Motor
└── Sepeda

Elektronik
├── Kamera
├── Drone
└── PlayStation

Perlengkapan Acara
├── Tenda
├── Kursi
└── Sound System
```

Data kategori:

- Nama
- Slug
- Parent category
- Deskripsi
- Gambar
- Status aktif
- Urutan tampil

### 9.2 Produk

Produk adalah jenis atau model barang.

Contoh:

- Toyota Avanza 2024
- Honda Vario 160
- Canon EOS R6
- PlayStation 5 Slim
- Tenda Kerucut 5 × 5 Meter
- Kursi Futura

Data produk:

- Nama
- SKU
- Kategori
- Merek
- Model
- Deskripsi
- Foto
- Jenis inventaris
- Jenis harga
- Harga rental
- Deposit
- Denda keterlambatan
- Minimum durasi rental
- Status aktif

### 9.3 Unit Produk

Digunakan untuk produk serialized.

Data unit:

- Kode unit
- Barcode
- QR Code
- Serial number
- Nomor polisi
- Cabang
- Kondisi
- Status
- Harga pembelian
- Tanggal pembelian
- Kilometer atau hour meter
- Catatan

---

## 10. Sistem Harga

Sistem mendukung beberapa metode harga:

- Per jam
- Per hari
- Per minggu
- Per bulan
- Per event
- Harga custom

Contoh:

```text
Canon EOS R6
Harga per hari: Rp350.000
Harga per minggu: Rp1.800.000
Deposit: Rp1.000.000
```

### 10.1 Aturan Harga Lanjutan

Pada tahap berikutnya sistem dapat mendukung:

- Harga akhir pekan
- Harga hari libur
- Harga musim ramai
- Harga berdasarkan cabang
- Harga berdasarkan durasi
- Harga khusus customer
- Paket produk
- Voucher
- Promo
- Diskon manual

---

## 11. Modul Customer

Data customer:

- Nama
- Nomor telepon
- Email
- Alamat
- Tanggal lahir
- Jenis identitas
- Nomor identitas
- Dokumen identitas
- Catatan
- Status
- Riwayat rental

Status customer:

- Active
- Inactive
- Blacklisted

Customer yang masuk blacklist tidak dapat membuat booking baru, kecuali mendapat persetujuan owner.

---

## 12. Modul Booking

Booking merupakan transaksi utama dalam sistem.

### 12.1 Alur Booking

```text
Customer memilih barang
↓
Customer memilih tanggal dan waktu
↓
Sistem mengecek ketersediaan
↓
Customer mengisi data
↓
Sistem menghitung harga
↓
Customer membuat booking
↓
Customer melakukan pembayaran atau DP
↓
Admin mengonfirmasi booking
↓
Barang dialokasikan
↓
Barang disiapkan
↓
Barang diambil atau dikirim
↓
Rental berjalan
↓
Barang dikembalikan
↓
Barang diperiksa
↓
Deposit dikembalikan atau dipotong
↓
Booking selesai
```

### 12.2 Status Booking

- Draft
- Pending
- Confirmed
- Preparing
- Ready
- Ongoing
- Returned
- Completed
- Cancelled
- Rejected

### 12.3 Draft

Booking belum dikirim.

### 12.4 Pending

Booking sudah dibuat tetapi belum dikonfirmasi.

### 12.5 Confirmed

Booking telah disetujui dan barang telah dialokasikan.

### 12.6 Preparing

Barang sedang dipersiapkan.

### 12.7 Ready

Barang siap diambil atau dikirim.

### 12.8 Ongoing

Barang sudah diserahkan dan rental sedang berjalan.

### 12.9 Returned

Barang telah dikembalikan dan menunggu pemeriksaan.

### 12.10 Completed

Semua proses telah selesai.

### 12.11 Cancelled

Booking dibatalkan.

### 12.12 Rejected

Booking ditolak oleh tenant.

---

## 13. Booking Item

Satu booking dapat memiliki beberapa produk.

Contoh:

```text
Booking BKG-20260728-0001

2 Tenda Kerucut
100 Kursi Futura
4 Meja Panjang
1 Sound System
```

Data booking item:

- Produk
- Unit yang dialokasikan
- Jumlah
- Harga
- Durasi
- Deposit
- Diskon
- Subtotal
- Total
- Catatan

Harga disimpan sebagai snapshot agar transaksi lama tidak berubah ketika harga produk diperbarui.

---

## 14. Pencegahan Double Booking

Sistem wajib mengecek bentrok berdasarkan:

- Tenant
- Produk
- Unit barang
- Tanggal mulai
- Tanggal selesai
- Status booking

Rumus bentrok:

```text
booking_lama.mulai < booking_baru.selesai

dan

booking_lama.selesai > booking_baru.mulai
```

Booking dianggap bentrok apabila kedua kondisi tersebut terpenuhi.

Booking yang berstatus cancelled atau rejected tidak dihitung sebagai bentrok.

Untuk inventaris quantity, sistem menghitung total kuantitas yang sedang digunakan pada periode tersebut.

Contoh:

```text
Total kursi: 500
Sudah dibooking: 400
Permintaan baru: 150

Hasil: Tidak tersedia
```

---

## 15. Kalender Rental

Sistem menyediakan kalender untuk melihat jadwal barang.

Kalender dapat difilter berdasarkan:

- Cabang
- Kategori
- Produk
- Unit
- Status booking
- Tanggal

Tampilan kalender:

- Harian
- Mingguan
- Bulanan
- Timeline per unit

Informasi yang ditampilkan:

- Nomor booking
- Customer
- Barang
- Waktu mulai
- Waktu selesai
- Status
- Cabang

---

## 16. Pengambilan dan Pengiriman

Booking mendukung dua metode fulfillment:

### 16.1 Pickup

Customer mengambil barang di cabang.

### 16.2 Delivery

Tenant mengirimkan barang ke alamat customer.

Data delivery:

- Alamat pengiriman
- Koordinat
- Biaya kirim
- Driver
- Jadwal pengiriman
- Status pengiriman
- Foto bukti
- Nama penerima
- Tanda tangan penerima

---

## 17. Checklist Serah Terima

Setiap kategori dapat memiliki checklist berbeda.

### Contoh Checklist Mobil

- Kondisi body
- Kondisi ban
- Jumlah bahan bakar
- Kilometer
- STNK
- Ban cadangan
- Dongkrak
- Kunci roda
- Foto bagian depan
- Foto bagian belakang
- Foto sisi kanan
- Foto sisi kiri

### Contoh Checklist Kamera

- Body kamera
- Lensa
- Baterai
- Charger
- Memory card
- Tas
- Strap
- Tutup lensa

### Contoh Checklist Tenda

- Rangka
- Penutup tenda
- Tali
- Pasak
- Kondisi kain
- Jumlah komponen

Checklist dapat digunakan saat:

- Barang keluar
- Barang kembali
- Pemeriksaan maintenance

---

## 18. Pengembalian Barang

Ketika barang dikembalikan, staff melakukan:

1. Scan barang.
2. Membuka detail booking.
3. Mengisi checklist pengembalian.
4. Mengunggah foto.
5. Mengisi kondisi barang.
6. Mencatat kekurangan.
7. Mencatat kerusakan.
8. Menghitung keterlambatan.
9. Menghitung denda.
10. Menentukan potongan deposit.
11. Mengubah status booking.

Status unit setelah pengembalian dapat menjadi:

- Available
- Cleaning
- Maintenance
- Damaged
- Lost

---

## 19. Denda

Sistem mendukung jenis denda:

- Keterlambatan
- Kerusakan
- Kehilangan
- Kekurangan aksesoris
- Over kilometer
- Over jam
- Biaya pembersihan
- Biaya bahan bakar
- Denda custom

Perhitungan denda dapat dilakukan otomatis atau manual.

Contoh:

```text
Keterlambatan: 2 hari
Denda per hari: Rp100.000
Total denda: Rp200.000
```

---

## 20. Deposit

Deposit memiliki status:

- Pending
- Held
- Partially Deducted
- Refunded
- Forfeited

Contoh:

```text
Deposit diterima: Rp1.000.000
Potongan kerusakan: Rp250.000
Refund deposit: Rp750.000
```

Deposit harus dipisahkan dari pendapatan rental agar laporan keuangan tidak salah menghitung deposit sebagai omzet.

---

## 21. Pembayaran

Metode pembayaran:

- Tunai
- Transfer bank
- QRIS
- Virtual account
- Kartu kredit
- Payment gateway
- Metode lainnya

Jenis pembayaran:

- Uang muka
- Pelunasan
- Deposit
- Denda keterlambatan
- Denda kerusakan
- Refund
- Pembayaran lain

Status pembayaran:

- Pending
- Paid
- Failed
- Expired
- Refunded
- Cancelled

### 21.1 Status Pembayaran Booking

- Unpaid
- Partial
- Paid
- Refunded

### 21.2 Payment Gateway

Integrasi yang direncanakan:

- Midtrans
- Xendit
- Duitku

Sistem harus dapat menerima webhook payment gateway dan memperbarui status pembayaran secara otomatis.

---

## 22. Invoice

Setiap booking memiliki invoice.

Invoice berisi:

- Logo tenant
- Nama bisnis
- Alamat
- Nomor invoice
- Nomor booking
- Data customer
- Daftar barang
- Tanggal rental
- Harga
- Diskon
- Pajak
- Deposit
- Denda
- Total
- Pembayaran
- Sisa tagihan
- Status pembayaran
- QR verifikasi

Invoice dapat:

- Dilihat di web
- Diunduh sebagai PDF
- Dicetak
- Dikirim melalui email
- Dikirim melalui WhatsApp

---

## 23. QR Code dan Barcode

Setiap unit barang dapat memiliki QR Code dan barcode.

Ketika QR Code dipindai, sistem menampilkan:

- Nama produk
- Kode unit
- Status
- Kondisi
- Cabang
- Booking aktif
- Riwayat rental
- Riwayat maintenance
- Riwayat kerusakan

QR Code digunakan untuk:

- Stock opname
- Persiapan barang
- Check-out
- Check-in
- Transfer cabang
- Maintenance

---

## 24. Maintenance

Maintenance digunakan untuk mencatat servis dan perawatan barang.

Jenis maintenance:

- Service
- Repair
- Cleaning
- Inspection
- Calibration
- Lainnya

Data maintenance:

- Unit
- Jenis
- Judul
- Deskripsi
- Jadwal
- Tanggal mulai
- Tanggal selesai
- Vendor
- Biaya
- Status
- Catatan

Status maintenance:

- Scheduled
- In Progress
- Completed
- Cancelled

Unit yang sedang maintenance tidak dapat dialokasikan untuk booking.

---

## 25. Pergerakan Barang

Setiap perubahan unit dicatat sebagai movement.

Jenis movement:

- Stock in
- Reserved
- Check-out
- Check-in
- Transfer cabang
- Maintenance
- Damaged
- Lost
- Status change

Data movement:

- Unit
- Booking
- User
- Status sebelumnya
- Status baru
- Cabang asal
- Cabang tujuan
- Waktu kejadian
- Catatan

Riwayat ini digunakan untuk audit dan pelacakan barang.

---

## 26. Manajemen Cabang

Tenant dapat memiliki satu atau lebih cabang.

Data cabang:

- Nama
- Kode
- Alamat
- Nomor telepon
- Koordinat
- Status

Setiap cabang memiliki:

- Stok
- Unit
- User
- Booking
- Pengambilan
- Pengiriman

Unit dapat dipindahkan dari satu cabang ke cabang lain.

---

## 27. Dashboard

Dashboard owner menampilkan:

- Pendapatan hari ini
- Pendapatan bulan ini
- Booking hari ini
- Booking mendatang
- Booking aktif
- Barang sedang disewa
- Barang tersedia
- Barang terlambat
- Barang maintenance
- Barang rusak
- Pembayaran belum lunas
- Deposit belum dikembalikan
- Produk paling sering disewa
- Customer paling aktif
- Grafik pendapatan
- Grafik booking

Dashboard dapat difilter berdasarkan:

- Periode
- Cabang
- Kategori
- Produk

---

## 28. Laporan

Laporan MVP:

- Laporan booking
- Laporan pendapatan
- Laporan pembayaran
- Laporan piutang
- Laporan barang disewa
- Laporan keterlambatan
- Laporan customer
- Laporan produk terlaris
- Laporan stok
- Laporan unit
- Laporan deposit
- Laporan denda

Laporan lanjutan:

- Laba dan rugi
- Biaya maintenance
- Utilisasi barang
- ROI per barang
- Riwayat kerusakan
- Pendapatan per cabang
- Pendapatan per kategori
- Performa staff
- Cashflow

Laporan dapat diekspor ke:

- PDF
- Excel
- CSV

---

## 29. Notifikasi

Notifikasi dikirim melalui:

- In-app notification
- Email
- WhatsApp
- Push notification

Jenis notifikasi:

- Booking baru
- Booking dikonfirmasi
- Pembayaran diterima
- Pembayaran gagal
- Pengingat pelunasan
- Pengingat pengambilan
- Pengingat pengembalian
- Rental terlambat
- Deposit telah dikembalikan
- Maintenance jatuh tempo
- Stok tidak tersedia
- Dokumen customer belum lengkap

---

## 30. Website Booking Tenant

Setiap tenant mendapatkan website booking.

Halaman website:

- Beranda
- Katalog
- Detail produk
- Cek ketersediaan
- Form booking
- Pembayaran
- Status booking
- Riwayat booking
- Kontak
- Syarat dan ketentuan

Tenant dapat mengatur:

- Logo
- Warna
- Banner
- Nama bisnis
- Deskripsi
- Nomor WhatsApp
- Alamat
- Jam operasional
- Kebijakan deposit
- Kebijakan pembatalan
- Metode pembayaran
- Domain

---

## 31. Hak Akses

Sistem menggunakan role dan permission.

Contoh permission:

- View dashboard
- Manage users
- Manage branches
- Manage categories
- Manage products
- Manage product units
- Manage customers
- Create booking
- Confirm booking
- Cancel booking
- Manage payments
- Process refund
- Check-out item
- Check-in item
- Manage maintenance
- View reports
- Export reports
- Manage settings

Owner dapat membuat role custom pada paket tertentu.

---

## 32. Audit Log

Aktivitas penting harus dicatat.

Contoh:

- User login
- Produk dibuat
- Harga diubah
- Booking dibuat
- Booking dibatalkan
- Pembayaran diubah
- Deposit dipotong
- Unit dinyatakan rusak
- Customer dimasukkan ke blacklist
- Data dihapus
- Permission user diubah

Audit log berisi:

- Tenant
- User
- Aktivitas
- Data sebelum
- Data sesudah
- IP address
- Device
- Waktu

---

## 33. Paket Berlangganan

### Starter

Cocok untuk usaha rental kecil.

Fitur:

- 1 cabang
- Maksimal 3 user
- Maksimal 250 produk atau unit
- Booking
- Pembayaran
- Invoice
- Website booking
- Laporan dasar

### Business

Cocok untuk rental berkembang.

Fitur:

- Maksimal 5 cabang
- Maksimal 20 user
- Inventaris lebih besar
- QR Code
- Maintenance
- Deposit
- Delivery
- Laporan lengkap
- Custom domain

### Enterprise

Cocok untuk perusahaan besar.

Fitur:

- Cabang tidak terbatas
- User tidak terbatas
- White label
- API
- SSO
- Custom integration
- Dedicated support
- SLA
- Custom workflow

---

## 34. Subscription Management

Status tenant:

- Trial
- Active
- Suspended
- Expired

Fitur subscription:

- Paket langganan
- Masa trial
- Invoice langganan
- Pembayaran langganan
- Upgrade paket
- Downgrade paket
- Perpanjangan
- Grace period
- Pembatasan fitur berdasarkan paket

Tenant expired dapat masuk ke sistem dalam mode read-only selama periode tertentu.

---

## 35. Kebutuhan Non-Fungsional

### 35.1 Keamanan

- Semua endpoint tenant harus divalidasi berdasarkan tenant aktif.
- Password disimpan menggunakan hashing yang aman.
- API menggunakan token authentication.
- Upload file divalidasi.
- Data sensitif tidak ditampilkan di log.
- Rate limiting diterapkan.
- Role dan permission diterapkan.
- Webhook harus menggunakan signature verification.
- Aktivitas penting dicatat di audit log.

### 35.2 Performa

- Dashboard utama maksimal dimuat dalam 3 detik.
- Pencarian produk maksimal 2 detik.
- Cek ketersediaan maksimal 2 detik.
- Query selalu memiliki filter tenant.
- Kolom pencarian utama harus memiliki index.
- Proses berat dijalankan melalui queue.
- Data laporan dapat menggunakan caching.

### 35.3 Skalabilitas

Sistem harus mendukung:

- Banyak tenant
- Banyak cabang
- Banyak booking
- Banyak unit barang
- Penambahan payment gateway
- Penambahan aplikasi mobile
- Penambahan marketplace

### 35.4 Backup

- Backup database harian
- Backup file
- Retensi backup
- Prosedur restore
- Monitoring kegagalan backup

### 35.5 Availability

Target awal uptime:

```text
99,5%
```

Target dapat ditingkatkan pada paket enterprise.

---

## 36. Arsitektur Teknis

### Backend

- Laravel 12
- Laravel Sanctum
- Laravel Queue
- Laravel Horizon
- Laravel Reverb
- PostgreSQL
- Redis

### Frontend Admin

- Nuxt 4
- Vue 3
- Pinia
- Tailwind CSS
- TanStack Query

### Customer Web

- Nuxt 4
- Server-side rendering
- Responsive design

### Mobile Staff

- Flutter
- BLoC
- GoRouter
- Dio

### Storage

- Amazon S3
- Cloudflare R2
- MinIO

### Infrastruktur

- Docker
- Nginx
- GitHub Actions
- Prometheus
- Grafana
- Sentry

---

## 37. Struktur Database MVP

Tabel utama:

```text
tenants
users
branches
categories
products
product_images
product_units
customers
bookings
booking_items
booking_unit_allocations
payments
deposits
maintenance_records
product_movements
```

### Relasi Utama

```text
Tenant
├── Users
├── Branches
├── Customers
├── Categories
│   └── Products
│       ├── Product Images
│       └── Product Units
├── Bookings
│   ├── Booking Items
│   ├── Unit Allocations
│   ├── Payments
│   └── Deposit
├── Maintenance Records
└── Product Movements
```

---

## 38. Batasan MVP

Fitur yang termasuk MVP:

- Registrasi tenant
- Login user
- Manajemen cabang
- Manajemen kategori
- Manajemen produk
- Manajemen unit
- Customer
- Booking
- Cek ketersediaan
- Pencegahan double booking
- Pembayaran manual
- Invoice
- Kalender booking
- Pengambilan
- Pengembalian
- Dashboard dasar
- Laporan dasar

Fitur yang belum termasuk MVP:

- Marketplace antar-tenant
- Aplikasi customer mobile
- Dynamic pricing
- AI prediction
- GPS tracking
- OCR KTP
- E-signature
- Akuntansi lengkap
- Franchise management
- Loyalty point
- Integrasi IoT

---

## 39. Tahapan Pengembangan

### Fase 1 — Foundation

- Setup project
- Authentication
- Multi-tenant
- Tenant context
- Role dan permission
- Branch
- User management
- Pengaturan tenant

### Fase 2 — Inventory

- Kategori
- Produk
- Foto produk
- Product unit
- Status unit
- QR Code
- Transfer cabang

### Fase 3 — Customer dan Booking

- Customer
- Booking
- Booking item
- Cek ketersediaan
- Unit allocation
- Kalender
- Pencegahan double booking

### Fase 4 — Payment dan Fulfillment

- Pembayaran
- Deposit
- Invoice
- Pickup
- Delivery
- Check-out
- Check-in
- Denda

### Fase 5 — Dashboard dan Report

- Dashboard
- Laporan booking
- Laporan pembayaran
- Laporan stok
- Laporan pendapatan
- Export

### Fase 6 — Customer Booking Website

- Katalog
- Detail produk
- Cek jadwal
- Booking online
- Pembayaran online
- Status booking

---

## 40. Prioritas Backlog MVP

### Must Have

- Multi-tenant
- Authentication
- Role dan permission
- Cabang
- Produk
- Unit
- Customer
- Booking
- Booking item
- Availability check
- Unit allocation
- Pembayaran
- Invoice
- Pengambilan
- Pengembalian
- Laporan dasar

### Should Have

- Deposit
- Denda
- QR Code
- Maintenance
- Kalender booking
- Notifikasi
- Delivery

### Could Have

- Voucher
- Promo
- Paket produk
- Custom domain
- WhatsApp integration
- Payment gateway
- Custom checklist

### Won’t Have in MVP

- Marketplace
- Dynamic pricing
- AI forecasting
- IoT tracking
- Loyalty point
- Full accounting system

---

## 41. User Story Utama

### Owner Mengelola Produk

Sebagai owner, saya ingin menambahkan produk dan unit barang agar barang dapat disewakan dan dilacak.

### Admin Membuat Booking

Sebagai admin, saya ingin membuat booking dengan memilih customer, barang, dan jadwal agar transaksi rental tercatat.

### Sistem Mengecek Ketersediaan

Sebagai admin, saya ingin sistem mendeteksi jadwal bentrok agar tidak terjadi double booking.

### Customer Booking Online

Sebagai customer, saya ingin melihat barang yang tersedia pada tanggal tertentu agar dapat melakukan booking tanpa bertanya melalui WhatsApp.

### Staff Menyerahkan Barang

Sebagai staff, saya ingin melakukan checklist dan scan barang saat pengambilan agar barang keluar tercatat dengan benar.

### Staff Menerima Pengembalian

Sebagai staff, saya ingin mencatat kondisi barang ketika dikembalikan agar kerusakan dan denda dapat dihitung.

### Owner Melihat Laporan

Sebagai owner, saya ingin melihat laporan pendapatan dan penggunaan barang agar dapat mengambil keputusan bisnis.

---

## 42. Acceptance Criteria Utama

### Membuat Produk

- User dapat memilih kategori.
- User dapat memilih inventory type.
- User dapat mengisi harga rental.
- User dapat mengunggah foto.
- Produk tersimpan pada tenant aktif.
- Produk tenant lain tidak dapat diakses.

### Membuat Booking

- User dapat memilih customer.
- User dapat memilih periode rental.
- User dapat menambahkan beberapa produk.
- Sistem menghitung durasi.
- Sistem menghitung subtotal.
- Sistem mengecek ketersediaan.
- Sistem menolak booking yang bentrok.
- Sistem membuat nomor booking unik.
- Booking tersimpan pada tenant aktif.

### Mengalokasikan Unit

- Hanya unit available yang dapat dipilih.
- Unit maintenance tidak dapat dipilih.
- Unit dengan jadwal bentrok tidak dapat dipilih.
- Satu unit tidak dapat dialokasikan ke dua booking aktif pada periode yang sama.

### Pembayaran

- User dapat mencatat pembayaran parsial.
- Sistem memperbarui paid amount.
- Sistem menghitung remaining amount.
- Status berubah menjadi partial jika belum lunas.
- Status berubah menjadi paid jika lunas.

### Pengembalian

- Staff dapat mencatat tanggal pengembalian.
- Sistem menghitung keterlambatan.
- Staff dapat mengisi kerusakan.
- Staff dapat menambahkan denda.
- Staff dapat menentukan status unit.
- Deposit dapat dikembalikan atau dipotong.

---

## 43. Indikator Keberhasilan Produk

Indikator keberhasilan awal:

- Tenant dapat menyelesaikan setup awal kurang dari 30 menit.
- Booking dapat dibuat kurang dari 3 menit.
- Tidak terjadi double booking pada unit serialized.
- Owner dapat mengetahui barang tersedia secara real-time.
- Semua pembayaran dapat ditelusuri ke booking.
- Semua unit memiliki riwayat status.
- Laporan pendapatan dapat dibuat tanpa pengolahan manual.
- Minimal 80% transaksi tenant dilakukan melalui sistem.
- Penurunan kesalahan stok dan jadwal dibanding pencatatan manual.

---

## 44. Risiko Produk

### Fleksibilitas Terlalu Tinggi

Jenis rental memiliki kebutuhan berbeda.

Mitigasi:

- Gunakan field umum.
- Tambahkan konfigurasi kategori.
- Gunakan custom attributes pada versi berikutnya.
- Hindari kolom khusus yang terlalu banyak di tabel utama.

### Double Booking Akibat Race Condition

Dua booking dapat dibuat bersamaan.

Mitigasi:

- Gunakan database transaction.
- Lakukan pengecekan ulang sebelum commit.
- Gunakan locking saat alokasi unit.
- Tambahkan constraint dan index.

### Kebocoran Data Antar-Tenant

Kesalahan query dapat menampilkan data tenant lain.

Mitigasi:

- Gunakan TenantContext.
- Terapkan tenant scope.
- Gunakan policy.
- Tambahkan automated test isolasi tenant.

### Harga Lama Berubah

Perubahan harga produk dapat mengubah transaksi lama.

Mitigasi:

- Simpan snapshot nama dan harga pada booking item.

### Deposit Tercatat Sebagai Pendapatan

Deposit dapat menyebabkan laporan pendapatan salah.

Mitigasi:

- Pisahkan transaksi deposit dari pembayaran rental.

---

## 45. Pengembangan Setelah MVP

Setelah MVP stabil, pengembangan berikutnya meliputi:

- Mobile app staff
- Mobile app customer
- Payment gateway
- WhatsApp automation
- Dynamic pricing
- Promo dan voucher
- Paket rental
- Loyalty point
- Marketplace rental
- E-signature
- OCR KTP dan SIM
- GPS tracking kendaraan
- IoT asset tracking
- Akuntansi
- API publik
- White label
- Franchise management
- AI prediksi permintaan

---

## 46. Kesimpulan

RentFlow dirancang sebagai aplikasi rental SaaS yang fleksibel untuk berbagai jenis bisnis.

Fondasi utama produk adalah:

- Multi-tenant
- Produk dan unit
- Inventaris serialized dan quantity
- Booking berbasis waktu
- Pencegahan double booking
- Pembayaran
- Deposit
- Pengambilan dan pengembalian
- Riwayat barang
- Dashboard dan laporan

Pengembangan harus dimulai dari alur inti:

```text
Tenant
→ Produk
→ Unit
→ Customer
→ Booking
→ Availability
→ Payment
→ Check-out
→ Check-in
→ Completed
```

Setelah alur inti stabil, fitur seperti maintenance, delivery, payment gateway, mobile app, dan marketplace dapat ditambahkan secara bertahap.
