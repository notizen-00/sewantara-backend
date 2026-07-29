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
