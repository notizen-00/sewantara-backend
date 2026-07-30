# Tenant-scoped migrations

These are operational tables isolated by `tenant_id`, such as users, branches,
customers, inventory, bookings, payments, fulfillment, maintenance, and logs.

This directory is not loaded by `php artisan migrate`.

Stancl runs these files against each tenant schema through
`php artisan tenants:migrate`. Foreign keys to the central `tenants` table are
intentionally forbidden to keep operational migrations isolated from the
central schema.

All primary keys and internal foreign keys use auto-incrementing `BIGINT`.
The `tenant_id` column remains a string reference to the central tenant
identifier and is not an internal tenant-schema foreign key.
