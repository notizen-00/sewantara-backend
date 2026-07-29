Design a complete high-fidelity mobile application for **Sewantara**, a multi-tenant SaaS rental management platform used by rental business owners and staff.

Sewantara can support many rental industries, including car rental, motorcycle rental, camera rental, PlayStation rental, tent and event equipment rental, heavy equipment rental, camping equipment rental, fashion rental, and medical equipment rental.

This mobile application is specifically for tenant users:

* Business Owner
* Administrator
* Cashier
* Warehouse Staff
* Driver

Do not design a customer marketplace application. This is an operational business management application for rental tenants.

# Product Identity

App name: Sewantara

Tagline: Kelola Rental, Lebih Mudah

Primary language: Indonesian

Currency: Indonesian Rupiah (IDR)

Default timezone: Asia/Jakarta

Platform: Android and iOS mobile application

Design style:

* Modern Indonesian SaaS
* Clean and professional
* Premium but approachable
* Simple enough for small rental businesses
* Optimized for daily operational use
* High information clarity
* Fast scanning and easy one-handed interaction
* Material 3 inspired, without looking like a generic Google template

# Visual Direction

Use a clean light theme with:

* Primary color: deep emerald green
* Secondary color: warm golden yellow
* Neutral background: soft gray-white
* White cards
* Dark charcoal text
* Green for success and available status
* Blue for confirmed or processing status
* Orange for pending or warning status
* Red for overdue, damaged, failed, or cancelled status
* Gray for inactive status

Use:

* Rounded cards with 14–18 px corner radius
* Soft shadows
* Clear typography hierarchy
* Generous spacing
* Compact but readable data tables and lists
* Modern outlined icons
* Bottom navigation
* Sticky primary actions
* Skeleton loading states
* Empty states with simple illustrations
* Success, warning, confirmation, and error dialogs
* Accessible color contrast
* Minimum 44 px touch targets

Avoid:

* Excessive gradients
* Decorative glassmorphism
* Overly colorful cards
* Tiny text
* Dense desktop-style tables
* Complicated navigation
* Generic finance-app appearance

# Multi-Tenant Context

The authenticated user belongs to one rental tenant.

Display the active tenant clearly in the top area using:

* Tenant logo
* Tenant business name
* Active branch
* Subscription plan badge

Example:

Rental Kamera Jember
Cabang Utama
Business Plan

Provide a tenant and branch switcher for users who have access to multiple tenants or branches.

The mobile API architecture uses tenant identification through the URL path:

`/api/v1/{tenant}/...`

The UI does not need to expose this technical URL, but the active tenant context must always be visually clear.

# Main Navigation

Create a bottom navigation bar with five items:

1. Beranda
2. Booking
3. Inventaris
4. Pelanggan
5. Lainnya

Place a floating QR/barcode scan button above the center of the bottom navigation when relevant.

# User Roles and Permissions

The interface must adapt to user roles.

Owner:

* Full dashboard
* Revenue
* Reports
* Users
* Subscription
* Settings

Administrator:

* Products
* Customers
* Bookings
* Payments
* Operational dashboard

Cashier:

* Payments
* Invoice
* Deposit
* Outstanding balance

Warehouse Staff:

* Inventory
* Preparation
* Check-out
* Check-in
* QR scanning
* Maintenance

Driver:

* Delivery tasks
* Pickup tasks
* Navigation
* Proof of delivery

Hide unavailable actions instead of only disabling them.

# Screen 1 — Splash Screen

Create a minimal splash screen containing:

* Sewantara logo
* App name
* Tagline “Kelola Rental, Lebih Mudah”
* Emerald green background
* Subtle rental inventory illustration

# Screen 2 — Login

Create a professional login screen containing:

* Sewantara logo
* Welcome message
* Email input
* Password input
* Show password button
* “Lupa kata sandi?”
* Device name handled silently
* Primary button: “Masuk”
* Link to contact administrator
* Secure login visual indicator

Include loading, invalid credential, inactive user, suspended tenant, and expired subscription states.

# Screen 3 — Tenant Selection

Show this screen only when the user belongs to multiple tenants.

Elements:

* Title: “Pilih Bisnis”
* Search tenant
* Tenant cards
* Logo
* Business name
* Subscription status
* Last accessed information
* Selected state
* Continue button

# Screen 4 — Branch Selection

Show:

* Active tenant
* List of accessible branches
* Branch name
* Branch code
* Address
* Active/inactive indicator
* Current selected branch
* Search field

# Screen 5 — Home Dashboard

Design a modern operational dashboard.

Header:

* Greeting using the user’s name
* Tenant logo and name
* Active branch selector
* Notification icon
* Profile avatar

Summary cards:

* Pendapatan Hari Ini
* Booking Hari Ini
* Sedang Disewa
* Belum Lunas

Use a horizontally scrollable card layout on small screens.

Operational alerts:

* Booking perlu dikonfirmasi
* Barang perlu disiapkan
* Pengembalian hari ini
* Booking terlambat
* Deposit belum dikembalikan
* Barang dalam maintenance

Quick actions:

* Buat Booking
* Tambah Pelanggan
* Tambah Produk
* Catat Pembayaran
* Scan Barang
* Pengembalian

Dashboard chart:

* Revenue for the last seven days
* Booking trend
* Toggle between revenue and booking

Upcoming bookings:

* Booking number
* Customer
* Rental period
* Product summary
* Status badge
* Time until pickup

# Screen 6 — Notification Center

Create notification groups:

* Hari Ini
* Kemarin
* Sebelumnya

Notification types:

* Booking created
* Payment received
* Booking ready
* Return reminder
* Overdue booking
* Deposit refund
* Subscription warning
* Maintenance reminder

Actions:

* Mark as read
* Mark all as read
* Open related transaction

# Screen 7 — Booking List

Create a mobile-friendly booking management screen.

Components:

* Search booking number or customer
* Date range filter
* Branch filter
* Status filter chips
* Payment status filter
* Sort button

Status chips:

* Semua
* Pending
* Dikonfirmasi
* Disiapkan
* Siap
* Berjalan
* Dikembalikan
* Selesai
* Terlambat
* Dibatalkan

Booking cards contain:

* Booking number
* Customer name
* Product thumbnail stack
* Product count
* Start and end date
* Total amount
* Payment status
* Booking status
* Fulfillment type
* Branch

Use clear status colors.

# Screen 8 — Booking Detail

Create a detailed booking page with sections:

Header:

* Booking number
* Booking status
* Payment status
* Overflow action menu

Customer section:

* Customer name
* Phone number
* Identity verification badge
* Address
* Call and WhatsApp actions

Rental period:

* Start date and time
* End date and time
* Actual checkout and return times
* Duration
* Overdue indicator

Products:

* Product image
* Product name
* Allocated unit code
* Quantity
* Pricing type
* Unit price
* Subtotal

Payment summary:

* Subtotal
* Discount
* Delivery fee
* Tax
* Charges
* Total
* Paid amount
* Remaining amount
* Deposit

Timeline:

* Draft
* Pending
* Confirmed
* Preparing
* Ready
* Ongoing
* Returned
* Completed

Actions must adapt to status:

* Confirm
* Reject
* Prepare
* Mark Ready
* Check-Out
* Return
* Complete
* Reschedule
* Extend
* Cancel
* Record Payment
* Download Invoice

Use a sticky bottom action area.

# Screen 9 — Create Booking Stepper

Create a multi-step booking flow.

Step 1: Select Customer

* Search existing customer
* Recent customers
* Add new customer
* Blacklist warning
* Identity verification status

Step 2: Rental Period

* Start date and time
* End date and time
* Duration preview
* Branch
* Pickup or delivery

Step 3: Select Products

* Search
* Category chips
* Product cards
* Available quantity
* Rental price
* Deposit
* Quantity stepper
* Availability indicator

Step 4: Allocation

For serialized inventory:

* Select available physical units
* Unit code
* Serial number
* Plate number
* Condition
* Branch
* Availability status

For quantity inventory:

* Requested quantity
* Available quantity
* Stock warning

Step 5: Pricing Summary

* Duration
* Price per item
* Discount
* Delivery fee
* Deposit
* Total amount
* Required down payment
* Notes

Step 6: Review and Submit

* Customer
* Period
* Products
* Price summary
* Terms confirmation
* Primary button: “Buat Booking”

Include states for:

* No inventory
* Date conflict
* Unit overlap
* Customer blacklisted
* Subscription limit reached
* Invalid rental period

# Screen 10 — Booking Calendar

Create a mobile rental calendar with:

* Monthly view
* Weekly view
* Agenda view
* Date selector
* Branch filter
* Product category filter
* Status legend

Clicking a date displays bookings for that day.

Booking timeline cards show:

* Product
* Unit
* Customer
* Start and end time
* Status

# Screen 11 — Check-Out Flow

Create a guided check-out workflow.

Steps:

1. Scan or confirm product unit
2. Verify allocated items
3. Complete checklist
4. Record condition
5. Record meter or mileage when applicable
6. Record fuel level when applicable
7. Upload photos
8. Customer confirmation
9. Digital signature
10. Complete check-out

Checklist example for camera:

* Camera body
* Lens
* Battery
* Charger
* Memory card
* Bag
* Strap
* Lens cap

Checklist example for vehicle:

* Body
* Tires
* Fuel
* Mileage
* Registration
* Spare tire
* Tools

Show progress and missing-item warnings.

# Screen 12 — Return Flow

Create a guided return flow.

Steps:

1. Scan returned units
2. Compare initial condition
3. Complete return checklist
4. Record current condition
5. Upload photos
6. Enter return meter
7. Calculate late duration
8. Add damage or missing-item charges
9. Determine next inventory status
10. Settle deposit
11. Complete return

Possible next statuses:

* Available
* Cleaning
* Maintenance
* Damaged
* Lost

Show a clear deposit settlement summary:

* Deposit held
* Late fee
* Damage fee
* Other deduction
* Refund amount

# Screen 13 — Inventory Overview

Create an inventory dashboard.

Summary:

* Total products
* Available units
* Reserved
* Rented
* Maintenance
* Damaged
* Low stock

Tabs:

* Produk
* Unit
* Stok
* Pergerakan

Filters:

* Branch
* Category
* Inventory type
* Status
* Condition

Quick actions:

* Add product
* Add unit
* Stock adjustment
* Transfer stock
* Scan QR
* Stock opname

# Screen 14 — Product List

Product card:

* Product image
* Name
* SKU
* Category
* Inventory type
* Rental price
* Available count
* Total count
* Status

Inventory type badges:

* Per Unit
* Berdasarkan Jumlah

Actions:

* View
* Edit
* Add unit
* Adjust stock
* Archive

# Screen 15 — Product Detail

Sections:

* Product gallery
* Product information
* Category
* Brand and model
* Pricing
* Deposit
* Late fee
* Inventory status
* Units by branch
* Booking history
* Movement history
* Maintenance history

Actions:

* Edit product
* Add unit
* Generate QR
* Adjust stock
* Deactivate

# Screen 16 — Product Form

Create a product form containing:

* Product image upload
* Category
* Name
* SKU
* Brand
* Model
* Description
* Inventory type
* Default pricing type
* Minimum rental duration
* Deposit
* Late fee
* Active status

Pricing section:

* Hourly
* Daily
* Weekly
* Monthly
* Event
* Custom

Show conditional fields based on inventory type.

# Screen 17 — Product Unit Detail

Display:

* Product image
* Product name
* Unit code
* QR code
* Barcode
* Serial number
* Plate number
* Branch
* Status
* Condition
* Purchase date
* Purchase price
* Mileage or meter
* Current booking
* Movement timeline
* Maintenance timeline

Quick actions:

* Change status
* Transfer branch
* Schedule maintenance
* Scan history
* Mark damaged
* Mark lost

# Screen 18 — QR and Barcode Scanner

Create a full-screen scanner.

Features:

* Camera viewfinder
* Flash toggle
* Manual code entry
* Scan history
* Haptic feedback
* Success result bottom sheet
* Invalid code state
* Wrong tenant code warning

After scanning, show:

* Product
* Unit code
* Current status
* Current branch
* Current booking
* Available actions

# Screen 19 — Stock Adjustment

Form:

* Product
* Branch
* Current quantity
* Adjustment type
* Increase or decrease
* Quantity
* Reason
* Notes
* Confirmation

Show before and after quantities.

# Screen 20 — Stock Opname

Create a mobile stock opname experience:

* Select branch
* Scan units
* Enter physical quantity
* Compare system quantity
* Missing items
* Extra items
* Damaged items
* Progress summary
* Submit adjustment

# Screen 21 — Customer List

Create:

* Search by name or phone
* Status filter
* Verification filter
* Customer cards
* Name
* Phone
* Booking count
* Total spending
* Last booking
* Verification badge
* Blacklist badge

Floating button:

* Tambah Pelanggan

# Screen 22 — Customer Detail

Sections:

* Profile
* Contact
* Address
* Identity documents
* Verification status
* Booking history
* Payment history
* Charges
* Notes
* Customer statistics

Actions:

* Edit
* Call
* WhatsApp
* Create booking
* Upload document
* Verify document
* Add to blacklist

Use a prominent warning banner for blacklisted customers.

# Screen 23 — Customer Form

Fields:

* Name
* Phone
* Email
* Birth date
* Gender
* Address
* Notes
* Identity type
* Identity number
* Front image
* Back image
* Expiration date

# Screen 24 — Payment List

Tabs:

* Semua
* Pending
* Dibayar
* Gagal
* Dikembalikan

Payment cards:

* Payment number
* Booking number
* Customer
* Payment type
* Method
* Amount
* Status
* Payment date

Filters:

* Date
* Method
* Status
* Branch

# Screen 25 — Record Payment

Form:

* Booking
* Customer
* Outstanding amount
* Payment type
* Payment method
* Amount
* Payment date
* Upload proof
* Notes

Payment methods:

* Cash
* Bank transfer
* QRIS
* Virtual account
* Payment gateway
* Other

Show updated remaining balance before confirmation.

# Screen 26 — Invoice Detail

Design a clear mobile invoice:

* Tenant branding
* Invoice number
* Booking number
* Customer
* Items
* Price breakdown
* Payment summary
* Deposit
* Charges
* Status
* QR verification

Actions:

* Download PDF
* Share
* Print
* Record payment

# Screen 27 — Deposit Settlement

Display:

* Booking
* Customer
* Deposit held
* Deductions
* Remaining deposit
* Refund method
* Bank information
* Proof
* Notes

Actions:

* Add deduction
* Refund deposit
* Forfeit deposit

Require confirmation before financial actions.

# Screen 28 — Maintenance

Create maintenance tabs:

* Dijadwalkan
* Berjalan
* Selesai

Cards:

* Product
* Unit code
* Maintenance type
* Schedule
* Vendor
* Estimated cost
* Status

Actions:

* Schedule maintenance
* Start
* Complete
* Cancel

Maintenance types:

* Service
* Repair
* Cleaning
* Inspection
* Calibration

# Screen 29 — Delivery Tasks

Designed primarily for drivers.

Tabs:

* Hari Ini
* Mendatang
* Selesai

Delivery card:

* Booking number
* Customer
* Delivery type
* Address
* Scheduled time
* Product count
* Phone
* Status

Actions:

* Open navigation
* Call customer
* Start delivery
* Upload proof
* Mark delivered
* Report problem

# Screen 30 — Reports

Create report cards:

* Pendapatan
* Booking
* Pembayaran
* Inventaris
* Produk Terlaris
* Pelanggan
* Deposit
* Maintenance
* Performa Cabang

Filters:

* Date range
* Branch
* Category

Include:

* Charts
* KPI cards
* Trend comparison
* Export button

Show an upgrade banner when advanced reports are unavailable on the current subscription.

# Screen 31 — Subscription

Display:

* Current plan
* Subscription status
* Billing cycle
* Start date
* Renewal date
* Trial information
* Feature list
* Usage limits

Usage cards:

* Branches
* Users
* Products
* Product units
* Monthly bookings
* Storage

Actions:

* Upgrade plan
* Change billing cycle
* View invoice
* Cancel subscription

Status banners:

* Trial
* Active
* Past Due
* Grace Period
* Suspended
* Expired

# Screen 32 — User and Role Management

User list:

* Avatar
* Name
* Role
* Branch
* Active status

User form:

* Name
* Email
* Phone
* Role
* Branch access
* Active status

Role and permission screen:

* Role name
* Permission groups
* Select all per module
* Save changes

# Screen 33 — More Menu

Menu groups:

Business:

* Branches
* Users and Roles
* Maintenance
* Delivery
* Reports

Financial:

* Payments
* Invoices
* Deposits

System:

* Subscription
* Notifications
* Audit Log
* Settings
* Help
* Logout

# Screen 34 — Tenant Settings

Sections:

* Business profile
* Logo and branding
* Contact
* Timezone
* Currency
* Booking rules
* Minimum down payment
* Deposit policy
* Late fee policy
* Invoice numbering
* Notification preferences
* Operational hours

# Screen 35 — Profile and Security

Features:

* Personal profile
* Change password
* Active devices
* Revoke session
* Biometric login toggle
* Notification preference
* Logout

# Screen 36 — Audit Log

Display:

* User
* Action
* Module
* Entity
* Date and time
* IP address
* Before and after values

Filters:

* User
* Action
* Module
* Date range

Use readable diff presentation for changed fields.

# Global States

Create consistent designs for:

* Loading
* Skeleton loading
* Empty data
* No search results
* Offline
* Server error
* Permission denied
* Tenant access denied
* Tenant not found
* Subscription expired
* Feature unavailable
* Usage limit reached
* Maintenance mode
* Session expired

# Required User Flows

Create connected prototypes for these complete flows:

Flow 1 — Create Booking

Home
→ Booking List
→ Create Booking
→ Select Customer
→ Select Period
→ Select Products
→ Check Availability
→ Review Pricing
→ Submit Booking
→ Booking Detail

Flow 2 — Booking Fulfillment

Booking Detail
→ Confirm Booking
→ Prepare
→ Mark Ready
→ Check-Out Checklist
→ Ongoing Booking

Flow 3 — Return and Deposit

Ongoing Booking
→ Return
→ Inspection
→ Add Charge
→ Deposit Settlement
→ Complete Booking

Flow 4 — Inventory

Inventory
→ Product Detail
→ Product Unit
→ Scan QR
→ Change Status or Transfer

Flow 5 — Payment

Booking Detail
→ Record Payment
→ Payment Confirmation
→ Updated Invoice

Flow 6 — Subscription Limit

Create Product or Branch
→ Usage Limit Reached
→ Upgrade Plan
→ Select Plan
→ Payment or Upgrade Confirmation

# Design System Deliverables

Generate a reusable mobile design system containing:

* Color tokens
* Typography scale
* Spacing scale
* Radius scale
* Elevation
* Icon rules
* Buttons
* Text fields
* Select fields
* Date picker
* Time picker
* Search field
* Filter chips
* Status badges
* Cards
* List items
* Bottom sheets
* Dialogs
* Snackbars
* Tabs
* Bottom navigation
* App bar
* Empty states
* Skeletons
* Charts
* Amount display
* QR scanner result
* Upload component
* Timeline component

# Output Requirements

Generate:

* High-fidelity mobile screens
* Android-first layouts that also adapt well to iOS
* A complete connected user journey
* Reusable components
* Consistent design tokens
* Realistic Indonesian sample data
* Responsive layouts for compact and large phones
* Light theme as the primary theme
* A matching dark theme variant for the dashboard, booking detail, inventory, and profile
* Clear annotations for role-based visibility
* Clear annotations for subscription-based feature restrictions
* Editable design structure suitable for export to Figma or frontend implementation

Use realistic sample data such as:

Tenant: Rental Kamera Jember
Branch: Cabang Utama
Customer: Budi Santoso
Product: Canon EOS R6
Unit code: CAM-R6-001
Booking: BKG-20260729-0001
Price: Rp350.000 per day
Deposit: Rp1.000.000
Status: Dikonfirmasi

The final result should feel like a polished production-ready Indonesian rental management SaaS mobile application, not a generic admin dashboard.
