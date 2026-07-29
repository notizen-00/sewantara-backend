# API Specification

Dokumentasikan seluruh endpoint REST API beserta request, response, validation, dan permission.

## Tenant Registration

```http
POST /api/central/auth/register
```

```json
{
  "business_name": "Rental Kamera Jember",
  "business_type": "camera_rental",
  "subdomain": "rentalkamerajember",
  "owner": {
    "name": "Owner Rental",
    "email": "owner@example.test",
    "phone": "081234567890",
    "password": "<TENANT_PASSWORD>",
    "password_confirmation": "<TENANT_PASSWORD>"
  },
  "plan_id": 1,
  "billing_interval": "month",
  "terms_accepted": true
}
```

## Tenant Authentication

```http
POST /api/tenant/{tenant}/auth/login
```

```json
{
  "email": "owner@example.test",
  "password": "<TENANT_PASSWORD>",
  "device_name": "web"
}
```

Response menghasilkan `access_token` Sanctum. Kirim token pada endpoint tenant
yang dilindungi:

```http
Authorization: Bearer {access_token}
Accept: application/json
```

Logout dan revoke token aktif:

```http
POST /api/tenant/{tenant}/auth/logout
```

## Product Master

```text
GET    /api/tenant/{tenant}/products
POST   /api/tenant/{tenant}/products
GET    /api/tenant/{tenant}/products/{product}
PATCH  /api/tenant/{tenant}/products/{product}
DELETE /api/tenant/{tenant}/products/{product}
```

Contoh request create:

```json
{
  "name": "Sony Alpha A7 IV",
  "sku": "CAM-SONY-A7IV",
  "brand": "Sony",
  "model": "A7 IV",
  "inventory_type": "serialized",
  "default_pricing_type": "daily",
  "minimum_rental_duration": 1,
  "deposit_amount": 1000000,
  "late_fee_amount": 250000,
  "is_featured": true,
  "is_active": true
}
```
