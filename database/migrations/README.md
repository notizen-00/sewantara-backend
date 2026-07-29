# Migration layout

`php artisan migrate` only discovers `central/`.

- `central/` contains framework, Stancl tenancy, subscription package, SaaS
  billing, and global authorization tables.
- `tenant/` contains operational tables for each tenant database. It is not
  registered with Laravel's central migrator.

Run central migrations using:

```bash
php artisan migrate
```

Tenant migrations run automatically after a verified subscription payment, or
manually using `php artisan tenants:migrate`.
