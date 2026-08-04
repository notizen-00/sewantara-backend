# Product Requirements Document

# Sewantara Admin Dashboard

**Project:** Sewantara Backend API
**Module:** Tenant Admin Dashboard (Web)
**Repository:** `sewantara-admin` (Nuxt, terpisah dari `sewantara-api`)
**Version:** 1.0
**Status:** Proposed
**Backend:** Laravel 13 (`sewantara-api`), konsumsi `routes/tenant-api.php`
**Frontend stack:** Nuxt 4, Vue 3, TailwindCSS, Pinia — sesuai `docs/03_TECH_STACK.md`
**Tenancy:** `stancl/tenancy`
**Subscription:** `laravelcm/laravel-subscriptions`
**Domain:** `https://app.sewantara.id`
**Primary consumer of:** `https://api.sewantara.id/api/tenant/{tenant}/...`

---

## 1. Product overview

Sewantara Admin Dashboard adalah aplikasi web operasional untuk pemilik dan
staf tenant mengelola bisnis rental/booking/membership/penjualan mereka
sehari-hari: onboarding awal, produk dan inventaris, booking, pelanggan,
pembayaran, maintenance, langganan SaaS beserta Product Engine, dan
pengaturan tenant.

Dokumen ini adalah kelanjutan dari dokumentasi yang sudah ada:

- `docs/PRD_TENANT_BFF.md` — PRD untuk **website publik tenant** (customer-facing,
  `{tenant}.sewantara.id`). Admin Dashboard bukan aplikasi itu.
- `docs/20_GOOGLE_AUTH_NUXT.md` — integrasi login Google untuk `app.sewantara.id`,
  yaitu domain aplikasi ini. Dokumen tersebut tetap menjadi rujukan utama untuk
  alur Google OAuth; PRD ini tidak mengulanginya.
- `docs/12_UI_UX_GUIDE.md` — brief desain **aplikasi mobile** (Flutter, per
  `docs/03_TECH_STACK.md`) untuk actor tenant yang sama. Admin Dashboard web
  mengadaptasi information architecture yang sama (dashboard, booking,
  inventaris, pelanggan, dst.) ke layout desktop/tablet, bukan bottom
  navigation mobile. Perbedaan disebutkan eksplisit di §7.
- `docs/05_MODULE_GUIDE.md` — sumber kebenaran untuk entity, business rule,
  permission, dan event tiap modul backend. PRD ini tidak mengulang detail
  tersebut, hanya merujuknya per bagian.
- `docs/PRD_Product_Engine_Module_Sewantara.md` — modul Product Engine
  (Rental/Booking/Membership/Sales) yang baru diimplementasikan di backend.
  Dashboard ini adalah permukaan UI pertama yang mengekspos pengaturan engine
  ke tenant, sehingga dibahas mendalam di §9.8.

Tiga aplikasi frontend Sewantara melayani audiens berbeda dan **tidak saling
menggantikan**:

| Aplikasi | Domain | Audiens | Backend consumer |
| --- | --- | --- | --- |
| Tenant Public Website | `{tenant}.sewantara.id` | Customer/guest | `/v1/public/*` |
| Admin Dashboard (dokumen ini) | `app.sewantara.id` | Owner, admin, kasir, staf gudang tenant | `/api/tenant/{tenant}/*` |
| Mobile App | Flutter (Android/iOS) | Staf tenant operasional (kasir, gudang, driver) | `/api/tenant/{tenant}/*` (subset) |

---

## 2. Background

Backend `sewantara-api` sudah menyediakan API tenant-admin yang cukup lengkap
di `routes/tenant-api.php`: onboarding, settings, branch, kategori, produk
(+gambar/harga/unit), inventory (stok/pergerakan/transfer), customer, booking
(+transisi status), payment, maintenance, availability, dashboard report,
subscription payment, dan — baru ditambahkan — engine catalog, membership,
serta sales order. Belum ada aplikasi web yang mengonsumsinya; staf tenant
saat ini hanya punya opsi mobile app (Flutter, sesuai `03_TECH_STACK.md`).

Admin Dashboard mengisi kebutuhan operasional yang lebih nyaman dikerjakan di
layar besar: input katalog massal, review laporan, konfigurasi onboarding dan
langganan, serta pengaturan tenant — sambil tetap memakai backend dan model
data yang sama persis dengan mobile app (tidak ada logic bisnis baru yang
khusus dashboard).

---

## 3. Goals

### 3.1 Primary goals

- Menyediakan satu aplikasi web untuk seluruh siklus operasional tenant:
  onboarding → katalog → booking → pembayaran → laporan.
- Mengekspos Product Engine (§9.8) sehingga tenant dapat melihat, mengaktifkan,
  dan menonaktifkan engine (Rental/Booking/Membership/Sales) sendiri, termasuk
  memahami dampaknya ke tagihan langganan.
- Mematuhi RBAC (role dan permission) yang sudah didefinisikan di backend —
  dashboard tidak boleh punya aturan otorisasi sendiri yang berbeda dari
  Laravel.
- Konsisten dengan konvensi response, error code, dan pagination yang sudah
  dipakai `sewantara-api` (lihat §10) — tidak menciptakan kontrak baru.
- Mendukung multi-branch dan (untuk peran tertentu) multi-tenant per user.

### 3.2 Secondary goals

- Dark mode.
- Command palette / pencarian cepat lintas modul.
- Export laporan (CSV/PDF) memakai endpoint backend yang sama dengan mobile.
- Notifikasi in-app real-time via Laravel Reverb (fase lanjutan, lihat §19).

---

## 4. Non-goals

Versi pertama dashboard tidak mencakup:

- Central super-admin console (mengelola seluruh tenant, plan, billing
  platform) — itu aplikasi terpisah dengan audiens berbeda (super admin
  Sewantara, bukan pemilik tenant), didokumentasikan kemudian jika dibutuhkan.
- Fitur customer-facing apa pun (itu domain `PRD_TENANT_BFF.md`).
- Business logic baru di Nuxt — semua pricing, availability, validasi, dan
  status transition tetap dihitung dan divalidasi Laravel, sama seperti
  prinsip di `PRD_TENANT_BFF.md` §1.
- Native mobile experience — dashboard adalah aplikasi web responsif, bukan
  pengganti aplikasi Flutter untuk staf lapangan (driver, gudang).
- Realtime collaborative editing.
- Lifecycle otomatis Membership (freeze/renew) atau Sales (checkout/stok) —
  backend-nya sendiri baru skeleton (lihat `PRD_Product_Engine_Module_Sewantara.md`),
  dashboard hanya menyediakan CRUD dasar yang sesuai dengan itu.

---

## 5. Actors

Mengikuti actor Auth Module (`05_MODULE_GUIDE.md` §5.3), yang menentukan menu
dan aksi yang terlihat (lihat §11 Permission-based UI):

- **Owner** — akses penuh: dashboard, laporan, pengguna & role, langganan,
  engine, pengaturan.
- **Admin** — produk, pelanggan, booking, pembayaran, dashboard operasional.
- **Kasir** — pembayaran, invoice, deposit, saldo outstanding.
- **Staf Gudang** — inventaris, persiapan, check-out, check-in, maintenance.
- **Driver** — tidak menjadi target utama dashboard web; tugas pengiriman
  tetap di mobile app (lihat `12_UI_UX_GUIDE.md` Screen 29). Dashboard boleh
  menampilkan status pengiriman read-only jika waktu memungkinkan, tapi ini
  bukan prioritas.

Super admin central **tidak** menjadi actor dashboard ini (lihat §4).

---

## 6. Architecture

```text
Browser (owner/admin/kasir/staf gudang)
        │
        ▼
app.sewantara.id
        │
        ▼
Nuxt 4 (SSR untuk halaman publik-terbatas seperti login;
        SPA/CSR untuk area setelah login)
        │
        │ HTTPS
        │ Authorization: Bearer {sanctum_token}
        │ X-Branch-Id (setelah branch dipilih)
        ▼
api.sewantara.id/api/tenant/{tenant}/...
        │
        ▼
Laravel Tenant Admin API
        │
        ├── auth:sanctum
        ├── tenant.user (user memang milik tenant ini)
        ├── tenant.branch (resolve X-Branch-Id)
        ├── tenant.accessible
        ├── tenant.subscription
        ├── tenant.engine:{code} (khusus rute membership/sales-orders)
        └── Controller → Application service → Eloquent (tenant schema)
```

Berbeda dengan Tenant Public Website (`PRD_TENANT_BFF.md`), dashboard **boleh**
memanggil Laravel langsung dari browser (tidak wajib lewat BFF server-to-server)
karena:

- request sudah terautentikasi per user (bukan guest anonim);
- tidak ada kebutuhan menyembunyikan identitas tenant dari klien — user
  memang staf tenant tersebut;
- tenant diidentifikasi lewat path `{tenant}` di URL, bukan header yang perlu
  dipercayai lintas trust-boundary seperti pada Public API.

Namun panggilan tetap harus melalui satu API client Nuxt terpusat (§12) agar
konsisten menyisipkan header, menangani refresh/expired token, dan menangani
format error.

---

## 7. Navigasi dan tata letak

### 7.1 Perbedaan dari mobile (`12_UI_UX_GUIDE.md`)

Mobile app memakai bottom navigation 5 item (Beranda, Booking, Inventaris,
Pelanggan, Lainnya) karena ruang layar terbatas. Dashboard web memakai:

- **Sidebar kiri** (collapsible) berisi seluruh modul yang boleh diakses role
  user, dikelompokkan sesuai grup di `12_UI_UX_GUIDE.md` Screen 33 (Business,
  Financial, System) ditambah grup **Engine** (baru).
- **Topbar**: nama/logo tenant aktif, branch selector, badge plan langganan,
  notifikasi, avatar profil — setara Screen 5 mobile tapi selalu terlihat,
  bukan hanya di home.
- **Konten utama**: tabel data (bukan card list seperti mobile) untuk layar
  lebar, dengan fallback card list di breakpoint tablet/mobile browser.

### 7.2 Struktur sidebar

```text
Dashboard
Booking
  ├── Daftar Booking
  └── Kalender Booking
Inventaris
  ├── Produk
  ├── Unit & Stok
  ├── Pergerakan
  └── Maintenance
Pelanggan
Membership          (hanya tampil jika engine "membership" aktif)
Penjualan            (hanya tampil jika engine "sales" aktif)
Keuangan
  ├── Pembayaran
  ├── Invoice
  └── Deposit
Laporan
Langganan
  ├── Paket & Tagihan
  └── Engine            (baru — lihat §9.8)
Pengguna & Peran
Pengaturan
```

Item yang tersembunyi karena permission atau engine harus **disembunyikan**,
bukan sekadar dinonaktifkan — mengikuti prinsip `12_UI_UX_GUIDE.md`: "Hide
unavailable actions instead of only disabling them."

### 7.3 Onboarding gate

Selama `tenant_onboarding.status != 'completed'`, dashboard mengarahkan user
ke wizard onboarding (§9.1) dan mengunci akses ke modul lain, konsisten dengan
urutan step backend (`business_setup → business_template → rental_configuration
→ inventory_setup → pricing → booking_configuration → payment_configuration →
go_live`, lihat `EloquentTenantOnboardingWorkspace`). Wizard menampilkan
progress checklist sesuai response `GET /onboarding`.

---

## 8. Autentikasi dan konteks

### 8.1 Login

Dua jalur sudah tersedia di backend:

- **Email/password**: `POST /api/tenant/{tenant}/auth/login` (rate-limited
  `throttle:5,1`) — form login standar, mengikuti pola `12_UI_UX_GUIDE.md`
  Screen 2 (tanpa nama field/label mobile-spesifik).
- **Google OAuth**: alur lengkap sudah didokumentasikan di
  `20_GOOGLE_AUTH_NUXT.md` — dashboard mengimplementasikan `pages/auth/google/callback.vue`
  persis seperti contoh di dokumen tersebut.

Token Sanctum disimpan sebagai HttpOnly, Secure, `SameSite=Lax` cookie melalui
`useCookie()` (bukan localStorage) untuk mengurangi risiko XSS, sejalan dengan
rekomendasi `PRD_TENANT_BFF.md` §17.2 meskipun konteksnya first-party, bukan
BFF pihak ketiga.

### 8.2 Pemilihan tenant dan branch

- Jika user hanya terhubung ke satu tenant, langsung masuk ke tenant tersebut
  (tidak ada layar pemilihan).
- Jika user terhubung ke lebih dari satu tenant (kasus multi-bisnis), tampilkan
  layar pemilihan tenant setara `12_UI_UX_GUIDE.md` Screen 3, lalu redirect ke
  `app.sewantara.id` dengan tenant terpilih tersimpan di sesi (subdomain per
  tenant untuk dashboard di luar cakupan v1 — semua tenant memakai domain
  `app.sewantara.id` yang sama, dibedakan lewat state aplikasi + path
  `{tenant}` API, bukan hostname).
- Setelah tenant aktif, tampilkan pemilihan branch (setara Screen 4) jika user
  punya akses ke >1 branch aktif; jika hanya satu, langsung dipakai. Branch
  terpilih dikirim sebagai header `X-Branch-Id` pada semua request tenant-admin,
  konsisten dengan middleware `tenant.branch` backend.
- Tenant dan branch aktif disimpan di Pinia store (`useTenantContext`,
  `useBranchContext`) dan dipulihkan dari sesi saat reload.

### 8.3 Sesi habis dan akses ditolak

Tangani seragam di API client (§12):

| Kondisi backend | Aksi dashboard |
| --- | --- |
| `401 UNAUTHENTICATED` | Hapus sesi, redirect ke halaman login, simpan intended URL |
| `403 SUBSCRIPTION_REQUIRED` / `SUBSCRIPTION_EXPIRED` | Redirect ke halaman Langganan dengan banner status |
| `403 ENGINE_NOT_ENABLED` | Tampilkan state "fitur belum aktif" dengan CTA ke Langganan → Engine (bukan halaman blank/404) |
| `404 TENANT_NOT_FOUND` | Halaman "akun usaha tidak ditemukan", tombol kembali ke login |

---

## 9. Modul dan kebutuhan data

Setiap modul di bawah merujuk endpoint `routes/tenant-api.php` dan entity
lengkap di `05_MODULE_GUIDE.md`. Bagian ini hanya menegaskan kebutuhan
tampilan/interaksi dashboard, bukan mengulang skema data.

### 9.1 Onboarding wizard

Endpoint: `GET/PATCH /onboarding/*`, `POST /onboarding/go-live`.

- Step mengikuti urutan backend (§7.3), progress bar dan checklist memakai
  response `checklist` apa adanya (jangan hardcode step di frontend).
  Sejak Product Engine berjalan, step `rental_configuration` dan
  `booking_configuration` sekarang membawa field `engine_code` (lihat
  `PRD_Product_Engine_Module_Sewantara.md` §4) — form step 3/6 wizard wajib
  mengirim `engine_code` yang relevan, bukan hanya field lama.
- Tombol "Selesaikan Penyiapan" mengirim `POST /onboarding/go-live` dan
  memblokir jika ada item checklist `false`, menampilkan pesan
  `checklist` dari response validasi.

### 9.2 Dashboard (beranda)

Endpoint: `GET /reports/dashboard`.

Adaptasi Screen 5 (`12_UI_UX_GUIDE.md`) ke layout grid desktop: kartu ringkasan
(pendapatan hari ini, booking hari ini, sedang disewa, belum lunas), alert
operasional, quick actions, grafik tren 7 hari, dan daftar booking mendatang.

### 9.3 Booking

Endpoint: `GET/POST /bookings`, `GET /bookings/{id}`, `POST /bookings/{id}/{check-out|return|cancel}`,
`POST /bookings/{id}/payments[/checkout]`, `POST /availability/check`,
`GET /reports/dashboard` (kalender terpisah bila backend menambah endpoint
kalender; jika belum ada, susun dari `GET /bookings` dengan filter tanggal).

- Daftar booking: filter status/tanggal/cabang, kolom sesuai Screen 7.
- Detail booking: timeline status, tombol aksi mengikuti status saat ini
  (Confirm/Reject/Prepare/Ready/Check-Out/Return/Complete/Cancel) — aksi yang
  tidak valid untuk status saat ini **disembunyikan**, bukan disabled, karena
  backend sendiri menolaknya via `BKG-017` (state transition map).
- Form buat booking multi-step mengikuti Screen 9, dengan validasi akhir tetap
  di backend (`ManageBookings::create`) — frontend hanya validasi UX (required
  field, tipe data), bukan business rule (overlap, blacklist, dsb).
- **Penting (Product Engine):** field `product_id` yang dipilih di step 3
  menentukan `engine_code` booking (lihat `ManageBookings::resolveEngineCode`).
  Jika user memilih produk dari engine berbeda dalam satu booking, backend
  menolak dengan error `items` — dashboard harus mem-filter pemilihan produk
  step 3 agar hanya menampilkan produk dari satu engine per booking, atau
  menampilkan error tersebut dengan jelas jika validasi lolos ke server.

### 9.4 Inventaris (Produk, Unit, Stok, Pergerakan, Maintenance)

Endpoint: `GET/POST/PATCH/DELETE /products`, `/products/{id}/images`,
`/product-prices`, `/product-units`, `/inventory/stocks*`,
`/inventory/movements/*`, `/maintenance*`.

- Form produk (Screen 16) **wajib** menambahkan dua field baru yang tidak ada
  di brief mobile lama: **Engine** (`engine_code`: Rental/Booking/Membership/Sales)
  dan **Tipe Produk** (`product_type`), dengan opsi tipe produk difilter sesuai
  engine yang dipilih (lihat mapping di
  `PRD_Product_Engine_Module_Sewantara.md` §1 — mis. engine Rental hanya
  menampilkan Vehicle/Equipment/Accommodation). Dropdown engine hanya
  menampilkan engine yang sudah diaktifkan tenant (`GET /engines`); jika
  tenant mencoba memilih engine yang belum aktif, backend menolak dengan
  pesan `Engine {code} belum diaktifkan untuk akun usaha ini.` — dashboard
  sebaiknya mencegah ini di UI dengan hanya menampilkan opsi yang valid,
  ditambah tautan cepat ke halaman Langganan → Engine untuk mengaktifkan.
- Sisanya (galeri, kategori, harga per tipe, unit serialized vs quantity,
  stok per cabang, pergerakan, maintenance) mengikuti Screen 13–20 apa adanya,
  cukup diadaptasi ke tabel/data grid desktop.

### 9.5 Pelanggan

Endpoint: `GET/POST/PATCH /customers`.

Mengikuti Screen 21–23. Verifikasi dokumen dan blacklist memerlukan permission
khusus (`customer.verify_document`, `customer.blacklist`) — sembunyikan aksi
bila user tidak punya permission tersebut.

### 9.6 Keuangan (Pembayaran, Invoice, Deposit)

Endpoint: `POST /bookings/{id}/payments[/checkout]`, `POST /payments/webhooks/{gateway}`
(server-to-server, tidak dipanggil dari dashboard).

Mengikuti Screen 24–27. Aksi finansial (refund, forfeit deposit) wajib dialog
konfirmasi eksplisit sebelum submit, sesuai `12_UI_UX_GUIDE.md`: "Require
confirmation before financial actions."

### 9.7 Laporan

Endpoint: `GET /reports/dashboard` (perluasan endpoint laporan lain mengikuti
Report Module di `05_MODULE_GUIDE.md` §11 begitu tersedia di backend — jangan
membuat endpoint laporan baru di sisi Nuxt).

Tampilkan banner upgrade ketika laporan lanjutan tidak tersedia di plan
saat ini (Screen 30), memakai data `plan.features` dari `GET /me`.

### 9.8 Langganan dan Product Engine (baru)

Endpoint: `GET /me` (berisi blok `subscription` termasuk array `engines`,
lihat `GetCurrentTenantSubscription`), `POST /subscription/payments/checkout`,
`GET /subscription/payments/{payment}`, `GET/POST /engines[/enable|disable]`.

Ini adalah permukaan UI pertama untuk fitur Product Engine yang baru
diimplementasikan backend-nya. Halaman **Langganan → Paket & Tagihan**
mengikuti Screen 31 apa adanya (plan, status, siklus tagihan, usage limit).

Halaman **Langganan → Engine** (baru, tidak ada di brief mobile lama) berisi:

- Kartu per engine (`GET /engines` → `rental`, `booking`, `membership`, `sales`),
  masing-masing menampilkan nama, deskripsi, harga bulanan, status aktif,
  dan badge "Termasuk Paket Dasar" untuk engine `is_core: true`
  (Rental & Booking, harga Rp0 — tidak bisa dinonaktifkan, tombol toggle
  disabled dengan tooltip penjelasan).
- Toggle aktif/nonaktif untuk engine berbayar (Membership, Sales) memanggil
  `POST /engines/enable` atau `/disable`. Sebelum enable, tampilkan dialog
  konfirmasi yang **menyatakan kenaikan tagihan bulanan** secara eksplisit
  (mis. "Mengaktifkan Membership Engine akan menambah Rp50.000/bulan ke
  tagihan langganan Anda mulai periode berikutnya.") — nilai harga diambil
  dari `monthly_price` engine tersebut, jangan di-hardcode di frontend karena
  ini masih placeholder yang bisa berubah (lihat
  `PRD_Product_Engine_Module_Sewantara.md` §8).
- Setelah enable/disable berhasil, refresh blok `subscription.engines` dari
  `GET /me` agar total tagihan yang ditampilkan selalu sinkron dengan
  perhitungan `CreateSubscriptionCheckout` (base plan + total harga engine
  aktif) — dashboard **tidak** menghitung ulang total secara mandiri.
- Menonaktifkan engine tidak menghapus data (Membership/Sales Order/Produk)
  yang sudah dibuat di engine tersebut — hanya memblokir pembuatan baru
  (`ENGINE_NOT_ENABLED`). Tampilkan catatan ini di dialog konfirmasi disable.

### 9.9 Membership (baru, skeleton)

Endpoint: `GET/POST/PATCH /memberships` (butuh engine `membership` aktif,
dijaga backend dengan middleware `tenant.engine:membership`).

Karena backend baru menyediakan CRUD dasar tanpa lifecycle otomatis (lihat
`PRD_Product_Engine_Module_Sewantara.md` §7), dashboard v1 cukup:

- Daftar membership (nomor, produk, pelanggan, status, periode).
- Form buat membership manual (pilih produk ber-engine `membership`, pelanggan,
  cabang, tanggal mulai/selesai, harga).
- Form update **status manual** (`pending|active|frozen|expired|renewed|cancelled`)
  — tidak ada automasi freeze/renew, jadi UI harus jelas bahwa perubahan
  status ini murni tindakan manual staf, bukan hasil sistem.
- Jika menu ini tidak muncul di sidebar tenant tertentu, itu berarti engine
  `membership` belum diaktifkan — arahkan ke §9.8, jangan tampilkan halaman
  kosong yang membingungkan.

### 9.10 Penjualan / Sales Order (baru, skeleton)

Endpoint: `GET/POST/PATCH /sales-orders` (butuh engine `sales` aktif).

- Daftar order (nomor, pelanggan, status, total).
- Form buat order: pilih pelanggan (opsional), tambah item (produk ber-engine
  `sales`, kuantitas, harga satuan) — subtotal/total dihitung backend saat
  submit, dashboard hanya menampilkan preview kalkulasi sebagai bantuan UX,
  bukan sumber kebenaran.
- Form update **status manual** (`draft|pending|completed|cancelled`) — sama
  seperti Membership, tidak ada integrasi pembayaran/stok otomatis di v1.

### 9.11 Pengguna dan Peran

Endpoint: sesuai Auth Module (`05_MODULE_GUIDE.md` §5.8) — `GET/POST /users`,
`GET/PATCH/DELETE /users/{id}`, `GET /roles`, `POST /users/{id}/roles`.

Mengikuti Screen 32. Hanya Owner (permission `role.*`, `user.assign_role`)
yang melihat menu ini.

### 9.12 Pengaturan tenant

Endpoint: `GET/PATCH /settings`, `POST /settings/images`,
`DELETE /settings/images/{image}`.

Mengikuti Screen 34. Bagian "Konfigurasi Rental Engine" pada payload
`rental_engine` sekarang bersifat **per-engine utama** (`primary_engine_code`
tenant), bukan satu-satunya konfigurasi tenant lagi — jika tenant mengaktifkan
Booking engine di §9.8, pengaturan detail engine keduanya (slot durasi,
strategi alokasi, dst.) untuk saat ini hanya dapat diubah lewat ulang wizard
onboarding step rental/booking (§9.1) dengan `engine_code` eksplisit; halaman
Pengaturan v1 tetap menampilkan/mengubah satu engine utama saja. Menyediakan
UI pengaturan per-engine penuh adalah kandidat fase berikutnya, bukan v1.

---

## 10. Konvensi API

Dashboard **mewarisi** konvensi yang sudah dipakai `sewantara-api`, sama
seperti `PRD_TENANT_BFF.md` §12, dengan penyesuaian karena ini API
tenant-admin (bukan public):

- Response sukses: `{"success": true, "data": ..., "message"?: "..."}`.
- Response gagal: `{"success": false, "error": {"code", "message", "details"}}`
  untuk endpoint yang memakai format ini (mis. `EnsureTenantSubscriptionActive`,
  `EnsureTenantEngineEnabled`), atau `{"message", "errors"}` standar Laravel
  validation untuk endpoint yang memakai `FormRequest`/`$request->validate()`
  bawaan. **Dashboard harus menangani kedua bentuk ini** di satu error
  handler terpusat (§12) karena backend belum 100% seragam di semua modul.
- Pagination: parameter `page`/`per_page`, response memakai struktur Laravel
  paginator standar (`data`, `current_page`, `last_page`, `total`, dst.) —
  **berbeda** dari `meta.page/per_page/total` custom yang dipakai Public API
  (`PRD_TENANT_BFF.md` §12.5). Jangan menyamakan kedua kontrak ini.
- Uang: backend tenant-admin menyimpan/mengirim desimal string (mis.
  `"1250000.00"`), bukan integer minor unit seperti Public API. Format tampilan
  di dashboard mengikuti ini, jangan mengasumsikan integer sen.
- Tanggal/waktu: ISO 8601, dikonversi ke timezone tenant untuk tampilan.

---

## 11. Permission-based UI

Backend adalah satu-satunya sumber kebenaran otorisasi. Dashboard **tidak**
menyimpan daftar permission-per-role secara statis di frontend; permission
efektif user diambil dari `GET /me` (`user.roles[].permissions`) setelah
login, disimpan di Pinia (`useAuthStore`), dan dipakai untuk:

- Menyembunyikan item sidebar/menu yang tidak relevan.
- Menyembunyikan tombol aksi (create/update/delete/verify/blacklist/dll.)
  yang permission-nya tidak dimiliki.

Endpoint tetap memvalidasi permission secara independen di server — UI hanya
mencegah kebingungan (menampilkan tombol yang nanti ditolak 403), bukan
mekanisme keamanan itu sendiri. Prinsip yang sama seperti
`05_MODULE_GUIDE.md` §12.9: "Endpoint tetap wajib memeriksa entitlement
meskipun menu disembunyikan di frontend."

---

## 12. API client dan state management

- Satu composable/plugin Nuxt (`useApi()` atau setara `$fetch` instance
  terkonfigurasi) menjadi satu-satunya jalur pemanggilan backend. Semua
  request lain (komponen, store) wajib lewat sini — tidak boleh ada `$fetch`
  ad-hoc tersebar di halaman, agar penanganan header, refresh token, dan error
  tetap konsisten.
- Interceptor menyisipkan otomatis: `Authorization`, `X-Branch-Id` (jika ada),
  `Accept: application/json`.
- Interceptor menangani `401`/`403` sesuai tabel §8.3 secara terpusat, bukan
  per-halaman.
- State management: Pinia store per domain (`useAuthStore`, `useTenantContext`,
  `useBranchContext`, dan store data per modul secukupnya — hindari satu store
  raksasa untuk seluruh aplikasi).
- Form mutation memakai pola optimistic-safe: submit → tampilkan loading →
  refresh data dari response backend (bukan menyimpan echo dari payload
  request sebagai state akhir), karena backend dapat menormalisasi/menghitung
  ulang nilai (contoh nyata: `ManageProducts::create()` mengisi default
  `slug`/`deposit_amount` yang mungkin berbeda dari input mentah).

---

## 13. Global states

Mengikuti daftar `12_UI_UX_GUIDE.md` "Global States" apa adanya (Loading,
Skeleton, Empty data, No search results, Offline, Server error, Permission
denied, Tenant access denied, Tenant not found, Subscription expired, Feature
unavailable, Usage limit reached, Maintenance mode, Session expired), ditambah
satu state baru khusus dashboard web:

- **Engine belum aktif** — dipicu oleh `403 ENGINE_NOT_ENABLED` (§8.3), beda
  dari "Feature unavailable" karena punya CTA spesifik (aktifkan engine),
  bukan upgrade plan.

---

## 14. Security requirements

- Token Sanctum disimpan HttpOnly cookie (§8.1), tidak pernah di localStorage
  atau exposed ke `window`.
- CSRF: karena dashboard adalah first-party SPA yang memanggil API langsung
  dengan bearer token (bukan cookie-based session terhadap Laravel), risiko
  CSRF klasik rendah — tetap terapkan `SameSite=Lax` pada cookie penyimpan
  token sebagai defense-in-depth.
- Jangan mengirim kredensial payment gateway tenant (server key, client key)
  ke browser dalam bentuk apa pun; field tersebut sudah tidak dikembalikan
  backend pada `GET /settings`, dashboard tidak boleh menambahkannya di form
  tanpa memverifikasi ulang perilaku backend.
- Validasi upload gambar (tipe MIME, ukuran maksimum) di sisi klien sebagai
  UX, backend tetap menjadi validator final (`TenantPrivateMedia`).
- Rate limit login (`throttle:5,1`) — tampilkan pesan yang jelas saat `429`,
  jangan retry otomatis tanpa jeda.

---

## 15. Non-functional requirements

- Responsif: breakpoint minimal desktop (≥1280px, layout utama), tablet
  (768–1279px, sidebar collapsible), dan mobile browser (<768px, fallback
  navigasi drawer) — dashboard web tetap harus dapat dipakai di tablet
  lapangan meskipun target utamanya desktop.
- Aksesibilitas: kontras warna memadai, target sentuh minimum sesuai
  `12_UI_UX_GUIDE.md` (44px) untuk breakpoint mobile/tablet.
- Performa: navigasi antar halaman utama (dashboard, daftar booking, daftar
  produk) idealnya < 1 detik dengan data ter-cache/prefetch wajar; daftar
  dengan volume besar wajib pagination server-side, tidak memuat seluruh data
  sekaligus ke klien.
- Dark mode (goal sekunder, §3.2) tidak boleh mengubah makna warna status
  (hijau=available/success, biru=confirmed/processing, oranye=pending/warning,
  merah=overdue/damaged/failed/cancelled, abu=inactive) dari palet
  `12_UI_UX_GUIDE.md`.

---

## 16. Environment variables

```env
NUXT_PUBLIC_API_BASE=https://api.sewantara.id
NUXT_PUBLIC_APP_NAME=Sewantara
NUXT_PUBLIC_APP_DOMAIN=app.sewantara.id
NUXT_PUBLIC_GOOGLE_LOGIN_ENABLED=true
```

Konfigurasi Google OAuth backend (`GOOGLE_REDIRECT_URI`,
`GOOGLE_AUTH_FRONTEND_CALLBACK_URL`, dst.) sudah dicakup penuh di
`20_GOOGLE_AUTH_NUXT.md` dan tidak diulang di sini.

---

## 17. Rollout plan

### Phase 1 — Foundation

- Login (email/password + Google), tenant/branch selection, layout
  sidebar+topbar, permission-based navigation, API client terpusat.
- Onboarding wizard lengkap (termasuk field `engine_code` baru).

### Phase 2 — Operasional inti

- Dashboard, Booking (list/detail/create/status transitions), Inventaris
  (produk/unit/stok/pergerakan), Pelanggan.

### Phase 3 — Keuangan dan laporan

- Pembayaran, invoice, deposit, laporan dasar.

### Phase 4 — Langganan dan Product Engine

- Halaman Paket & Tagihan, halaman Engine (enable/disable + dialog dampak
  tagihan), integrasi checkout langganan.

### Phase 5 — Membership dan Sales (skeleton)

- CRUD dasar Membership dan Sales Order sesuai §9.9–9.10, digerbangi engine.

### Phase 6 — Pengguna, peran, pengaturan, hardening

- User & role management, tenant settings, audit log (jika endpoint tersedia),
  aksesibilitas, dark mode, performance pass.

---

## 18. Definition of done

Modul dashboard dianggap siap dipakai tenant apabila:

- Seluruh state di §13 punya desain dan penanganan nyata (bukan console
  error).
- Tidak ada permission/role yang dihardcode di frontend — semua bersumber
  dari `GET /me`.
- Tidak ada perhitungan bisnis (harga, availability, status transition, total
  tagihan langganan) yang dihitung ulang di frontend sebagai sumber
  kebenaran — semua nilai tampilan berasal dari response backend.
- Menu Membership/Penjualan/Engine hanya muncul sesuai entitlement backend
  yang sesungguhnya (`GET /engines`), diuji dengan tenant yang engine-nya
  belum diaktifkan.
- Error handler terpusat menangani kedua bentuk error backend (§10).
- Responsif teruji minimal di tiga breakpoint (§15).
- Environment variable production terisi lengkap (§16), tidak ada URL API
  yang di-hardcode di kode.

---

## 19. Open questions

Hal berikut belum diputuskan dan perlu dikonfirmasi sebelum atau selama
Phase 4–5 dikerjakan, bukan blocker untuk memulai Phase 1:

1. Apakah dashboard butuh notifikasi realtime (Laravel Reverb) di v1, atau
   polling `GET /me`/`GET /reports/dashboard` cukup untuk MVP?
2. Apakah central super-admin console (§4, non-goal v1) akan menjadi aplikasi
   Nuxt terpisah atau area ter-gate di dalam `app.sewantara.id`?
3. Kapan harga asli Membership/Sales engine (saat ini placeholder Rp50.000,
   lihat §9.8) akan difinalisasi — dashboard menampilkan nilai ini apa adanya
   dari backend, tidak perlu menunggu, tapi tim produk perlu tahu nilainya
   masih sementara.
4. Apakah export laporan (CSV/PDF, §3.2) memakai endpoint backend baru atau
   digenerate di sisi Nuxt dari data yang sudah diambil?
