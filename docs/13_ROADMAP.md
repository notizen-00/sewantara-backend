# Product Roadmap

## Sewantara — Universal Rental Management SaaS

**Versi:** 1.0
**Status:** Draft
**Arsitektur:** Modular Monolith
**Tenant Management:** `stancl/tenancy`
**Subscription Management:** `laravelcm/laravel-subscriptions`

---

# 1. Tujuan Roadmap

Roadmap ini menjelaskan arah pengembangan Sewantara dari aplikasi rental inti hingga menjadi ekosistem rental digital.

Roadmap digunakan untuk:

- Menentukan prioritas pengembangan.
- Menjaga fokus MVP.
- Menghindari penambahan fitur terlalu dini.
- Menyelaraskan tim product, design, engineering, QA, dan DevOps.
- Menentukan dependency antarfitur.
- Menjadi dasar perencanaan sprint dan release.
- Menjadi acuan evaluasi keberhasilan setiap versi.

Roadmap bersifat fleksibel dan dapat berubah berdasarkan:

- Feedback tenant.
- Hasil validasi pasar.
- Kapasitas tim.
- Perubahan regulasi.
- Performa produk.
- Kebutuhan bisnis.
- Stabilitas versi sebelumnya.

---

# 2. Visi Pengembangan

Sewantara dikembangkan dalam tiga tahap utama:

```text
v1
Core Rental SaaS
    ↓
v2
Digital Booking Ecosystem
    ↓
v3
Marketplace, Automation, dan AI
```

Fokus setiap versi:

| Versi | Fokus                                         |
| ----- | --------------------------------------------- |
| v1    | Menyelesaikan masalah operasional rental      |
| v2    | Membuka booking online dan otomatisasi bisnis |
| v3    | Membangun marketplace dan fitur intelligence  |

---

# 3. Prinsip Prioritas

Setiap fitur diprioritaskan berdasarkan:

1. Dampak terhadap operasional tenant.
2. Kebutuhan pengguna terbanyak.
3. Risiko teknis.
4. Dependency dengan fitur lain.
5. Nilai bisnis.
6. Potensi monetisasi.
7. Kompleksitas implementasi.
8. Kesiapan infrastruktur.
9. Kebutuhan keamanan dan compliance.

Metode prioritas:

```text
Must Have
Should Have
Could Have
Won't Have Yet
```

---

# 4. Version 1 — Core Rental SaaS

## 4.1 Tujuan

Versi 1 membangun fondasi utama aplikasi rental yang dapat digunakan berbagai jenis bisnis.

Target utama:

- Rental dapat mengelola produk dan unit.
- Rental dapat membuat booking.
- Sistem dapat mencegah double booking.
- Pembayaran dapat dicatat.
- Barang dapat keluar dan kembali.
- Owner dapat melihat laporan dasar.
- Tenant dan subscription dapat dikelola.

---

## 4.2 Target Pengguna

- Owner rental.
- Admin.
- Kasir.
- Staff gudang.
- Super Admin SaaS.

---

## 4.3 Scope Utama

```text
Tenant
Auth
Subscription
Branch
Customer
Category
Product
Inventory
Booking
Payment
Invoice
Deposit
Check-out
Check-in
Dashboard
Basic Report
Audit Log
```

---

## 4.4 Foundation

### Tenant Management

Menggunakan:

```text
stancl/tenancy
```

Fitur:

- Registrasi tenant.
- Tenant provisioning.
- Tenant activation.
- Tenant suspension.
- Tenant settings.
- Tenant timezone.
- Tenant currency.
- Tenant branding dasar.
- Tenant isolation.
- Central domain.
- Tenant domain data.

Metode identifikasi:

```text
Landing page tenant:
InitializeTenancyByDomainOrSubdomain

Mobile API:
InitializeTenancyByPath

Internal service:
InitializeTenancyByRequestData

Queue:
Manual tenant initialization
```

---

### Subscription Management

Menggunakan:

```text
laravelcm/laravel-subscriptions
```

Fitur:

- Plan.
- Trial.
- Subscription.
- Feature entitlement.
- Usage limit.
- Upgrade.
- Downgrade.
- Expiration.
- Grace period.
- Suspend.
- Read-only mode.

Contoh limit:

- Cabang.
- User.
- Produk.
- Product unit.
- Booking bulanan.

---

### Authentication dan Authorization

Fitur:

- Login.
- Logout.
- Lupa password.
- Reset password.
- Sanctum Bearer Token.
- Role.
- Permission.
- Branch scope.
- User activation.
- Session management dasar.

Role awal:

```text
Super Admin
Owner
Admin
Cashier
Warehouse
Driver
Viewer
```

---

## 4.5 Organization

Fitur:

- CRUD cabang.
- Penempatan user ke cabang.
- Scope data berdasarkan cabang.
- Alamat cabang.
- Jam operasional.
- Status aktif cabang.

---

## 4.6 Customer Management

Fitur:

- CRUD customer.
- Nomor telepon.
- Email.
- Alamat.
- Catatan.
- Dokumen identitas.
- Verifikasi dokumen.
- Status aktif.
- Blacklist.
- Riwayat booking.
- Riwayat pembayaran.

---

## 4.7 Inventory Management

Fitur:

- Kategori.
- Subkategori.
- Produk.
- Foto produk.
- Harga.
- Deposit.
- Denda keterlambatan.
- Serialized inventory.
- Quantity inventory.
- Product unit.
- Barcode.
- QR Code.
- Status unit.
- Kondisi unit.
- Inventory movement.
- Stock adjustment.
- Transfer cabang.
- Availability check.

Status unit:

```text
available
reserved
rented
maintenance
cleaning
damaged
lost
inactive
```

---

## 4.8 Booking Management

Fitur:

- Booking manual.
- Booking item.
- Multi-item booking.
- Rental period.
- Pricing snapshot.
- Availability check.
- Unit allocation.
- Quantity reservation.
- Booking calendar.
- Booking status history.
- Cancellation.
- Expiration.
- Reschedule dasar.
- Extension dasar.
- Internal notes.
- Customer notes.

Status:

```text
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
```

---

## 4.9 Pencegahan Double Booking

Fitur wajib:

- Period overlap validation.
- Database transaction.
- Row-level locking.
- Recheck sebelum commit.
- PostgreSQL exclusion constraint untuk serialized unit.
- Quantity availability calculation.
- Concurrency test.

Acceptance criteria:

```text
Satu unit tidak dapat dialokasikan
ke dua booking aktif
pada periode yang sama.
```

---

## 4.10 Payment Management

Fitur:

- Pembayaran tunai.
- Transfer manual.
- DP.
- Pelunasan.
- Partial payment.
- Status pembayaran.
- Bukti pembayaran.
- Payment history.
- Verification.
- Cancellation.
- Refund manual dasar.

Status:

```text
pending
paid
failed
expired
cancelled
refunded
```

Payment gateway belum menjadi requirement utama v1.

---

## 4.11 Invoice

Fitur:

- Nomor invoice.
- Invoice snapshot.
- Daftar item.
- Customer information.
- Total.
- Deposit.
- Denda.
- Payment summary.
- Download PDF.
- Print.
- Status invoice.

---

## 4.12 Deposit dan Charge

Fitur:

- Hold deposit.
- Deposit status.
- Deposit deduction.
- Deposit refund.
- Late fee.
- Damage fee.
- Lost item fee.
- Missing accessory fee.
- Custom charge.

Business rule:

```text
Deposit bukan revenue.
```

---

## 4.13 Fulfillment

Fitur:

- Persiapan barang.
- Checklist check-out.
- Check-out.
- Bukti kondisi awal.
- Check-in.
- Pemeriksaan kondisi.
- Update status unit.
- Penghitungan keterlambatan.
- Penghitungan charge.
- Deposit settlement.

---

## 4.14 Dashboard dan Laporan Dasar

Dashboard:

- Pendapatan hari ini.
- Pendapatan bulan ini.
- Booking hari ini.
- Booking aktif.
- Barang disewa.
- Barang tersedia.
- Barang terlambat.
- Pembayaran belum lunas.
- Deposit belum selesai.

Laporan:

- Booking.
- Revenue.
- Payment.
- Inventory.
- Customer.
- Deposit.
- Charge.

Export:

- CSV.
- Excel.
- PDF dasar.

---

## 4.15 Notification Dasar

Channel:

- In-app.
- Email.

Notifikasi:

- Booking dibuat.
- Booking dikonfirmasi.
- Payment diterima.
- Booking siap.
- Pengingat pengembalian.
- Booking terlambat.
- Deposit dikembalikan.
- Subscription hampir berakhir.

WhatsApp dan push notification dapat masuk v2.

---

## 4.16 Audit dan Security

Fitur:

- Audit log.
- Login history.
- Tenant isolation test.
- Permission test.
- Rate limiting.
- Webhook security foundation.
- Sensitive data masking.
- File validation.
- Security headers.
- Backup.
- Error monitoring.

---

## 4.17 Non-Functional Target v1

Target:

```text
API p95 < 2 detik
Dashboard p95 < 3 detik
Error rate < 1%
Tidak ada double booking
Tenant isolation 100% pada test kritikal
```

---

## 4.18 Out of Scope v1

Belum termasuk:

- Marketplace.
- Public booking website lengkap.
- Payment gateway penuh.
- WhatsApp automation.
- AI.
- Dynamic pricing.
- Loyalty point.
- Customer mobile app.
- GPS tracking.
- IoT.
- Accounting penuh.
- White label penuh.

---

## 4.19 Definition of Done v1

v1 dianggap selesai jika:

- Tenant dapat dibuat.
- Tenant dapat berlangganan.
- Owner dapat login.
- Owner dapat membuat cabang.
- Produk dan unit dapat dikelola.
- Customer dapat dikelola.
- Booking dapat dibuat.
- Double booking dicegah.
- Payment dapat dicatat.
- Check-out dan return berjalan.
- Invoice tersedia.
- Dashboard dasar tersedia.
- Report dasar tersedia.
- Audit log tersedia.
- Tenant isolation test lulus.
- Booking concurrency test lulus.
- Subscription limit test lulus.

---

# 5. Version 2 — Website Booking dan Business Automation

## 5.1 Tujuan

Versi 2 membuka kanal digital agar customer dapat melakukan booking secara online dan tenant dapat mengotomatisasi operasional.

Fokus:

- Website publik tenant.
- Domain dan subdomain.
- Custom domain.
- Online booking.
- Payment gateway.
- Delivery.
- Notification multi-channel.
- Maintenance.
- Advanced reporting.

---

## 5.2 Landing Page Tenant

Menggunakan:

```text
InitializeTenancyByDomainOrSubdomain
```

Fitur:

- Landing page tenant.
- Subdomain tenant.
- Custom domain.
- Logo.
- Warna brand.
- Banner.
- Profil bisnis.
- Katalog publik.
- Halaman detail produk.
- Syarat rental.
- FAQ.
- Kontak.
- SEO metadata.

Contoh:

```text
rentalkamera.sewantara.id
booking.rentaljember.com
```

---

## 5.3 Public Booking Website

Fitur:

- Cek ketersediaan.
- Filter tanggal.
- Pilih produk.
- Multi-item cart.
- Harga otomatis.
- Customer form.
- Upload identitas.
- Booking online.
- Status booking.
- Invoice publik.
- Customer booking lookup.
- Cancellation request.
- Booking confirmation.

---

## 5.4 Payment Gateway

Integrasi:

- Midtrans.
- Xendit.
- Provider lain melalui adapter.

Fitur:

- QRIS.
- Virtual account.
- E-wallet.
- Card.
- Payment link.
- Expiration.
- Webhook.
- Refund.
- Reconciliation.
- Idempotency.
- Signature verification.

---

## 5.5 WhatsApp dan Push Notification

Channel:

- WhatsApp.
- Push notification.
- Email.
- In-app.

Fitur:

- Booking confirmation.
- Payment reminder.
- Pickup reminder.
- Return reminder.
- Overdue warning.
- Invoice.
- Deposit refund.
- Subscription reminder.
- Template per tenant.
- Notification preference.
- Delivery status.

---

## 5.6 Delivery Management

Fitur:

- Delivery atau pickup.
- Driver assignment.
- Jadwal pengiriman.
- Alamat customer.
- Map.
- Status pengiriman.
- Bukti serah terima.
- Foto.
- Nama penerima.
- Tanda tangan.
- Biaya delivery.

Status:

```text
scheduled
assigned
picked_up
on_delivery
delivered
failed
cancelled
```

---

## 5.7 Maintenance

Fitur:

- Maintenance schedule.
- Service.
- Repair.
- Cleaning.
- Calibration.
- Inspection.
- Vendor.
- Biaya.
- Reminder.
- Maintenance history.
- Auto blocking availability.
- Kilometer atau hour meter trigger.

---

## 5.8 Advanced Pricing

Fitur:

- Weekend pricing.
- Holiday pricing.
- Peak season.
- Duration discount.
- Branch pricing.
- Customer pricing.
- Custom pricing.
- Rental package.
- Bundle.
- Promo.
- Voucher.
- Manual discount approval.

---

## 5.9 Advanced Inventory

Fitur:

- Stock opname mobile.
- Bulk import.
- Bulk update.
- Accessory tracking.
- Package inventory.
- Bundle allocation.
- Low stock notification.
- Inventory utilization.
- Cost tracking.
- Purchase information.
- Depreciation data dasar.

---

## 5.10 Mobile Staff App

Fitur:

- Login.
- Tenant path API.
- Booking list.
- Product scan.
- QR scan.
- Barcode scan.
- Check-out.
- Check-in.
- Checklist.
- Foto.
- Delivery task.
- Maintenance task.
- Push notification.
- Offline queue dasar.

---

## 5.11 Customer Portal

Fitur:

- Customer login.
- Booking history.
- Payment history.
- Download invoice.
- Upload document.
- Booking status.
- Cancellation request.
- Extension request.
- Profile.
- Address book.

---

## 5.12 Advanced Report

Fitur:

- Revenue per branch.
- Revenue per category.
- Revenue per product.
- Product utilization.
- Maintenance cost.
- Customer value.
- Outstanding payment.
- Deposit liability.
- Booking conversion.
- Export async.
- Scheduled report.
- Materialized view.
- Dashboard caching.

Feature entitlement:

```text
advanced_reports
```

---

## 5.13 Custom Role dan White Label Dasar

Fitur:

- Custom role.
- Custom permission.
- Custom domain.
- Branding email.
- Branding invoice.
- Logo.
- Primary color.
- Tenant favicon.
- Hide Sewantara branding sesuai paket.

---

## 5.14 API Access

Fitur:

- API token tenant.
- API scope.
- Webhook tenant.
- Booking webhook.
- Payment webhook.
- Inventory webhook.
- Rate limit per plan.
- API documentation.
- Integration log.

Feature entitlement:

```text
api_access
```

---

## 5.15 Non-Functional Target v2

Target:

```text
Public website p95 < 2 detik
Availability p95 < 1.5 detik
Payment webhook processing < 5 detik
Notification delivery success > 95%
Uptime target 99.9%
```

---

## 5.16 Definition of Done v2

v2 dianggap selesai jika:

- Tenant memiliki landing page.
- Subdomain dan custom domain bekerja.
- Customer dapat booking online.
- Payment gateway aktif.
- Webhook idempotent.
- WhatsApp atau push notification tersedia.
- Delivery dapat dikelola.
- Maintenance tersedia.
- Mobile staff dapat scan barang.
- Advanced report tersedia.
- Custom domain dibatasi subscription.
- API access dapat digunakan sesuai plan.

---

# 6. Version 3 — Marketplace dan AI

## 6.1 Tujuan

Versi 3 mengembangkan Sewantara dari aplikasi manajemen tenant menjadi ekosistem rental yang menghubungkan penyedia rental dan customer.

Fokus:

- Marketplace.
- Discovery.
- Customer mobile app.
- Unified payment.
- AI recommendation.
- Demand forecasting.
- Dynamic pricing.
- Fraud detection.
- IoT dan tracking.
- Enterprise capability.

---

## 6.2 Marketplace Rental

Fitur:

- Tenant listing.
- Product marketplace.
- Search lintas tenant.
- Filter lokasi.
- Filter harga.
- Filter tanggal.
- Filter kategori.
- Rating.
- Review.
- Tenant verification.
- Product verification.
- Marketplace booking.
- Marketplace commission.
- Marketplace cancellation.
- Dispute.
- Customer support.
- Marketplace promotion.

---

## 6.3 Marketplace Catalog

Kategori:

- Mobil.
- Motor.
- Kamera.
- Tenda.
- Event equipment.
- PlayStation.
- Alat berat.
- Camping.
- Fashion.
- Medical equipment.
- Baby equipment.
- Lainnya.

Fitur:

- Kategori global.
- Tenant category mapping.
- Product normalization.
- Location availability.
- Availability aggregation.
- Featured listing.
- Sponsored listing.

---

## 6.4 Customer Mobile App

Fitur:

- Register.
- Login.
- Location.
- Search.
- Product detail.
- Availability.
- Booking.
- Payment.
- Tracking.
- Chat.
- Review.
- Wishlist.
- Promo.
- Loyalty.
- Notification.
- Booking history.
- Identity verification.

---

## 6.5 Marketplace Payment

Fitur:

- Central payment.
- Commission.
- Platform fee.
- Tenant settlement.
- Refund.
- Escrow.
- Split payment.
- Payout.
- Settlement report.
- Dispute hold.
- Reconciliation.

Catatan:

Implementasi settlement wajib menyesuaikan regulasi dan capability payment provider.

---

## 6.6 Review dan Reputation

Fitur:

- Customer review.
- Product rating.
- Tenant rating.
- Response tenant.
- Moderation.
- Fraud review detection.
- Verified booking label.
- Reputation score.
- Service quality score.

---

## 6.7 AI Recommendation

Fitur:

- Product recommendation.
- Similar products.
- Popular nearby rentals.
- Personalized category.
- Bundle recommendation.
- Upselling.
- Cross-selling.
- Search ranking.
- Recommendation based on history.

---

## 6.8 Demand Forecasting

AI digunakan untuk memprediksi:

- Permintaan per produk.
- Permintaan per cabang.
- Peak season.
- Low season.
- Stok yang perlu ditambah.
- Maintenance timing.
- Potensi kehilangan pendapatan.
- Produk kurang produktif.

Output:

- Forecast harian.
- Forecast mingguan.
- Forecast bulanan.
- Confidence range.
- Suggested action.

---

## 6.9 Dynamic Pricing

Faktor:

- Demand.
- Supply.
- Hari.
- Musim.
- Lokasi.
- Event.
- Availability.
- Durasi.
- Customer segment.
- Competitor signal jika tersedia.

Guardrail:

- Minimum price.
- Maximum price.
- Tenant approval.
- Explainable adjustment.
- Manual override.
- Audit log.

Dynamic pricing tidak boleh mengubah harga booking yang sudah dibuat.

---

## 6.10 AI Assistant

Fitur:

- Ringkasan dashboard.
- Tanya laporan menggunakan bahasa natural.
- Saran inventory.
- Saran harga.
- Draft balasan customer.
- Ringkasan booking bermasalah.
- Deteksi pembayaran terlambat.
- Saran maintenance.
- Generate description produk.
- Generate notification template.

AI tidak boleh menjalankan tindakan finansial kritikal tanpa approval.

---

## 6.11 Fraud dan Risk Detection

Fitur:

- Customer risk score.
- Dokumen mencurigakan.
- Booking abnormal.
- Pembayaran mencurigakan.
- Device anomaly.
- Repeated cancellation.
- Chargeback pattern.
- Blacklist intelligence.
- Manual review queue.

---

## 6.12 OCR dan Identity Verification

Fitur:

- OCR KTP.
- OCR SIM.
- Passport.
- Auto-fill customer data.
- Expiration detection.
- Face match jika tersedia.
- Document quality check.
- Manual verification fallback.

Data identitas wajib mengikuti security dan retention policy.

---

## 6.13 IoT dan Tracking

Fitur potensial:

- GPS kendaraan.
- Bluetooth tracker.
- Smart lock.
- Kilometer otomatis.
- Hour meter.
- Geofence.
- Tamper alert.
- Last location.
- Device health.
- Rental usage telemetry.

IoT diterapkan hanya jika kebutuhan tenant dan hardware partner sudah tervalidasi.

---

## 6.14 Enterprise Features

Fitur:

- SSO.
- SAML.
- SCIM.
- SLA.
- Dedicated environment.
- Advanced audit.
- Custom workflow.
- Approval flow.
- Organization hierarchy.
- Franchise management.
- Multi-brand.
- Multi-currency.
- Data warehouse integration.
- BI connector.
- Enterprise API.
- Custom integration.

---

## 6.15 Accounting Integration

Fitur:

- Chart of accounts mapping.
- Revenue journal.
- Refund journal.
- Deposit liability.
- Tax.
- Expense maintenance.
- Integration accounting.
- Export journal.
- Reconciliation.

Sewantara tidak harus menjadi aplikasi accounting penuh, tetapi dapat terintegrasi dengan sistem accounting.

---

## 6.16 Loyalty dan Membership

Fitur:

- Loyalty point.
- Customer tier.
- Referral.
- Membership.
- Coupon.
- Cashback.
- Reward.
- Repeat customer campaign.

---

## 6.17 Non-Functional Target v3

Target awal:

```text
Uptime 99.9% atau lebih
Marketplace search p95 < 2 detik
Payment processing idempotent
AI response memiliki audit dan guardrail
Tenant data tetap terisolasi
Marketplace projection terpisah dari operational write model
```

---

## 6.18 Definition of Done v3

v3 dianggap selesai jika:

- Customer dapat mencari produk lintas tenant.
- Marketplace booking berjalan.
- Commission tercatat.
- Settlement tenant berjalan.
- Review tersedia.
- Customer mobile app tersedia.
- Recommendation berjalan.
- Demand forecast tersedia.
- Dynamic pricing memiliki guardrail.
- Fraud detection menghasilkan review queue.
- Enterprise feature tersedia sesuai plan.
- Marketplace dan tenant operational system tetap terisolasi dengan baik.

---

# 7. Roadmap per Fase

## Phase 0 — Discovery dan Foundation

Fokus:

- Validasi masalah.
- Finalisasi PRD.
- Database design.
- Architecture.
- API specification.
- UI/UX flow.
- Setup repository.
- CI/CD.
- Environment.
- Security baseline.

Output:

```text
Documentation complete
Architecture approved
Development environment ready
```

---

## Phase 1 — SaaS Foundation

Modul:

- Tenant.
- Subscription.
- Auth.
- Role.
- Permission.
- Branch.
- Settings.
- Audit.

Milestone:

```text
Tenant dapat register, login, dan menggunakan plan.
```

---

## Phase 2 — Inventory dan Customer

Modul:

- Category.
- Product.
- Product unit.
- Quantity stock.
- Customer.
- Document.
- Blacklist.
- Movement.

Milestone:

```text
Tenant dapat mengelola barang dan customer.
```

---

## Phase 3 — Booking Core

Modul:

- Booking.
- Booking item.
- Availability.
- Allocation.
- Calendar.
- Status.
- Cancellation.
- Extension.
- Reschedule.

Milestone:

```text
Booking dapat dibuat tanpa double booking.
```

---

## Phase 4 — Payment dan Fulfillment

Modul:

- Payment.
- Invoice.
- Deposit.
- Charge.
- Check-out.
- Return.
- Inspection.
- Checklist.

Milestone:

```text
Siklus rental selesai dari booking sampai pengembalian.
```

---

## Phase 5 — Dashboard dan Release v1

Modul:

- Dashboard.
- Report.
- Notification.
- Export.
- Monitoring.
- Performance.
- Security hardening.

Milestone:

```text
v1 siap digunakan tenant awal.
```

---

## Phase 6 — Public Website dan Payment Gateway

Modul:

- Tenant landing page.
- Custom domain.
- Public catalog.
- Online booking.
- Payment gateway.
- Webhook.
- Customer portal.

Milestone:

```text
Customer dapat booking secara online.
```

---

## Phase 7 — Operational Automation

Modul:

- WhatsApp.
- Push.
- Delivery.
- Maintenance.
- Mobile staff.
- Advanced report.
- Custom role.

Milestone:

```text
v2 siap digunakan bisnis rental berkembang.
```

---

## Phase 8 — Marketplace Foundation

Modul:

- Global catalog.
- Marketplace search.
- Tenant verification.
- Review.
- Marketplace booking.
- Commission.
- Settlement.

Milestone:

```text
Customer dapat booking produk lintas tenant.
```

---

## Phase 9 — Intelligence dan Enterprise

Modul:

- AI recommendation.
- Forecast.
- Dynamic pricing.
- Fraud detection.
- OCR.
- IoT.
- Enterprise integration.

Milestone:

```text
v3 menjadi ekosistem rental digital.
```

---

# 8. Priority Matrix

## Must Have v1

- Multi-tenant.
- Subscription.
- Auth.
- Role dan permission.
- Branch.
- Customer.
- Inventory.
- Booking.
- Availability.
- Allocation.
- Payment manual.
- Invoice.
- Deposit.
- Check-out.
- Return.
- Dashboard.
- Basic report.
- Audit.

---

## Should Have v1

- QR Code.
- Barcode.
- Maintenance dasar.
- Email notification.
- Export.
- Booking calendar.
- Deposit deduction.
- Charge.

---

## Could Have v1

- Delivery dasar.
- Product bundle.
- Promo manual.
- Customer portal sederhana.
- Advanced report sederhana.

---

## Won't Have v1

- Marketplace.
- AI.
- Dynamic pricing.
- IoT.
- Customer mobile app.
- White label penuh.
- Accounting penuh.

---

# 9. Release Strategy

Setiap versi utama dapat dibagi menjadi release kecil.

Contoh:

```text
v1.0
Core tenant, auth, inventory, booking

v1.1
Payment, invoice, deposit

v1.2
Check-out, return, report

v2.0
Public booking website

v2.1
Payment gateway

v2.2
Mobile staff dan automation

v3.0
Marketplace

v3.1
AI recommendation

v3.2
Forecast, dynamic pricing, dan enterprise
```

---

# 10. Feature Flag Strategy

Fitur baru dapat dirilis bertahap menggunakan feature flag.

Contoh:

```text
public_booking
payment_gateway
advanced_reports
mobile_staff
marketplace
ai_recommendation
dynamic_pricing
```

Feature flag berbeda dari subscription feature.

Perbedaannya:

```text
Feature Flag:
Mengontrol rollout teknis.

Subscription Feature:
Mengontrol hak akses berdasarkan plan.
```

---

# 11. Beta Program

Sebelum general release:

## Internal Alpha

Digunakan oleh:

- Tim internal.
- Data dummy.
- Automated testing.

## Closed Beta

Digunakan oleh:

- Tenant terbatas.
- Beberapa jenis rental.
- Monitoring ketat.

## Open Beta

Digunakan oleh:

- Tenant lebih luas.
- Feature flag.
- Feedback aktif.

## General Availability

Dirilis setelah:

- Critical bug selesai.
- Performance memenuhi target.
- Security review selesai.
- Backup dan recovery diuji.
- Dokumentasi tersedia.

---

# 12. Success Metrics

## v1 Metrics

- Tenant berhasil onboarding.
- Setup awal kurang dari 30 menit.
- Booking dibuat kurang dari 3 menit.
- Tidak terjadi double booking.
- Minimal 80% transaksi tenant masuk sistem.
- Error rate kurang dari 1%.
- Tenant isolation test lulus.

## v2 Metrics

- Persentase booking online.
- Payment success rate.
- Notification delivery rate.
- Custom domain adoption.
- Customer conversion.
- Mobile staff adoption.

## v3 Metrics

- Marketplace GMV.
- Active marketplace tenant.
- Marketplace conversion.
- Repeat booking.
- Recommendation click-through.
- Forecast accuracy.
- Dynamic pricing uplift.
- Fraud loss reduction.

---

# 13. Risiko Roadmap

## Scope Terlalu Besar

Mitigasi:

- Kunci scope v1.
- Gunakan feature flag.
- Tunda fitur non-kritis.
- Gunakan milestone.

## Overengineering

Mitigasi:

- Modular monolith.
- DDD Lite.
- Pattern sesuai kebutuhan.
- Tidak langsung microservice.

## Marketplace Terlalu Dini

Mitigasi:

- Validasi core rental terlebih dahulu.
- Bangun supply tenant.
- Pastikan data inventory akurat.
- Pastikan payment dan support matang.

## AI Tanpa Data Cukup

Mitigasi:

- Kumpulkan data sejak v1.
- Buat event catalog.
- Pastikan kualitas data.
- Mulai dari recommendation sederhana.
- Gunakan rule-based fallback.

## Subscription Limit Tidak Akurat

Mitigasi:

- Backend validation.
- Transaction.
- Concurrency test.
- Usage reconciliation.

## Tenant Data Bocor

Mitigasi:

- `stancl/tenancy`.
- Tenant-aware route.
- Policy.
- Feature test.
- Audit.
- Security review.

---

# 14. Dependency Utama

```text
Tenant dan Auth
    ↓
Inventory dan Customer
    ↓
Booking
    ↓
Payment dan Fulfillment
    ↓
Dashboard dan Report
    ↓
Public Booking
    ↓
Marketplace
    ↓
AI dan Enterprise
```

Fitur tidak boleh dibangun sebelum dependency utamanya stabil.

Contoh:

```text
AI forecast
tidak dibangun sebelum
data booking dan inventory cukup.
```

---

# 15. Roadmap Governance

Roadmap direview:

- Setiap akhir sprint.
- Setiap akhir milestone.
- Setiap quarter.
- Setelah feedback tenant penting.
- Setelah incident besar.
- Sebelum memulai versi utama baru.

Perubahan roadmap harus mencatat:

- Alasan.
- Dampak.
- Dependency.
- Risiko.
- Target baru.
- Fitur yang ditunda.

---

# 16. Kesimpulan

Roadmap Sewantara dibagi menjadi:

```text
v1
Core Rental SaaS

v2
Website Booking dan Business Automation

v3
Marketplace, AI, dan Enterprise
```

Urutan pengembangan:

```text
Foundation
→ Inventory
→ Customer
→ Booking
→ Payment
→ Fulfillment
→ Report
→ Public Booking
→ Automation
→ Marketplace
→ AI
```

Prinsip utama:

1. Selesaikan core rental sebelum marketplace.
2. Pastikan tenant isolation sejak awal.
3. Subscription melekat pada tenant.
4. Double booking harus dicegah pada level aplikasi dan database.
5. Payment dan deposit harus akurat.
6. Public booking dibangun setelah operasional tenant stabil.
7. Marketplace dibangun setelah supply dan data cukup.
8. AI dibangun setelah kualitas data memadai.
9. Feature flag digunakan untuk rollout bertahap.
10. Setiap versi harus memiliki metrik keberhasilan.
