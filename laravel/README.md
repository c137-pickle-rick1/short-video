# Short Video Laravel App

## Database Workflows

### Fresh Laravel-first database

Use the normal Laravel migration path:

```bash
php artisan migrate
```

That path is for a clean database owned by this app. It does not need any legacy preparation or backfill step.

### Existing legacy short-video database

If you are pointing Laravel at an older Node-compatible SQLite database, run the legacy onboarding flow explicitly:

```bash
php artisan shortvideo:prepare-legacy-db
php artisan migrate
php artisan shortvideo:upgrade-legacy-db
```

The responsibilities are intentionally split:

- `shortvideo:prepare-legacy-db` ensures the old database has the minimum compatible schema shape.
- `php artisan migrate` applies the Laravel-owned migrations.
- `shortvideo:upgrade-legacy-db` backfills legacy tweet/source records into Laravel-first tables such as `videos` and `user_external_accounts`.

Running `php artisan migrate` by itself no longer performs legacy data backfill.
