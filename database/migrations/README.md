# Migration layout

`php artisan migrate` only discovers `central/`.

- `central/` contains framework, Stancl tenancy, subscription package, SaaS
  billing, business template presets, and global authorization tables.
- `tenant/` contains operational tables for each tenant schema. It is not
  registered with Laravel's central migrator. Business profile, rental engine
  configuration, onboarding progress, and payment methods also live here.

Run central migrations using:

```bash
php artisan migrate
```

Tenant migrations run automatically during trial registration, or manually
using `php artisan tenants:migrate`.
