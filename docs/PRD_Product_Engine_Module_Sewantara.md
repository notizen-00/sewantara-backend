# PRD --- Product Engine Module

## Overview

Product Engine merupakan modul inti Sewantara yang menentukan bagaimana
setiap produk diproses oleh sistem.

## Supported Engines

### Rental Engine

-   Mobil
-   Motor
-   Kamera
-   Kos
-   Villa
-   Apartemen

Lifecycle:

Draft -\> Reserved -\> Confirmed -\> Checked Out -\> In Use -\> Returned
-\> Inspection -\> Completed

### Booking Engine

-   PS per jam
-   Studio
-   Lapangan
-   Karaoke

Lifecycle:

Draft -\> Reserved -\> Confirmed -\> Checked In -\> Completed

### Membership Engine

-   Gym
-   Coworking
-   Paket Bulanan

Lifecycle:

Pending -\> Active -\> Frozen -\> Expired -\> Renewed

### Sales Engine

-   Snack
-   Merchandise

## Product Model

  -----------------------------------------------------------------------
  Field                               Description
  ----------------------------------- -----------------------------------
  product_type                        Vehicle, Equipment, Accommodation,
                                      Space, Service, Membership, Goods,
                                      Package

  engine_type                         Rental, Booking, Membership, Sales

  inventory_type                      Serialized, Quantity, Unlimited,
                                      Virtual

  pricing_type                        Hourly, Daily, Weekly, Monthly,
                                      Session, Fixed
  -----------------------------------------------------------------------

## API

-   /api/products
-   /api/rentals
-   /api/bookings
-   /api/memberships
-   /api/orders

## Recommended Architecture

``` text
Modules/
├── ProductEngine/
├── Rental/
├── Booking/
├── Membership/
├── Sales/
├── Inventory/
├── Availability/
├── Payments/
└── TenantSubscription/
```

## Acceptance Criteria

-   Multi-engine per tenant.
-   Workflow berdasarkan engine_type.
-   Engine dapat ditambah tanpa mengubah engine lain.
