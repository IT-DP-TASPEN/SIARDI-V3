# Deployment Notes

## Required Configuration

Application database:

- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

DWH read-only connection:

- `DWH_DB_HOST`
- `DWH_DB_PORT`
- `DWH_DB_DATABASE`
- `DWH_DB_USERNAME`
- `DWH_DB_PASSWORD`

Feature flags:

- `SIARDI_ENABLE_BUSINESS_REFERENCES=false`
- `SIARDI_ENABLE_DWH_RECONCILIATION=false`
- `SIARDI_ENABLE_LEGACY_REFERENCE_LINKING=false`

## Production Rollout Order

1. Deploy the new application code with all SIARDI feature flags disabled.
2. Point the main application connection to the existing SIARDI database.
3. Point the `dwh` connection to a strictly read-only DWH user.
4. Run additive migrations:

```bash
php artisan migrate --force
```

5. Seed only the additive lookup data needed for the new features:

```bash
php artisan db:seed --class=BusinessReferenceConfigurationSeeder --force
```

6. Install and build frontend assets so the Filament panel theme manifest exists:

```bash
npm install
npm run build
```

7. Enable `SIARDI_ENABLE_BUSINESS_REFERENCES=true` and validate create/edit flows.
8. Enable `SIARDI_ENABLE_DWH_RECONCILIATION=true` and validate dashboard numbers against DWH.
9. Enable `SIARDI_ENABLE_LEGACY_REFERENCE_LINKING=true` when operators are ready to start manual legacy linking.

## Important Safety Notes

- Do not run `php artisan migrate:fresh` against any shared or production database.
- Do not run the broad local bootstrap `DatabaseSeeder` against production.
- Do not use a DWH account with write privileges.
- The new migrations are additive only. No production SIARDI tables should be dropped or overwritten.

## Local / Dev Setup

For a fresh local environment:

```bash
php artisan migrate:fresh
php artisan db:seed
npm install
npm run build
php artisan storage:link
php artisan db:seed --class=DevelopmentArchiveFixtureSeeder
```

`DatabaseSeeder` is intended for fresh local environments. It seeds master data and creates:

- `admin@example.com`
- username `admin`
- password `password`

## Dev Fixture Seeder

`DevelopmentArchiveFixtureSeeder` is intentionally not wired into `DatabaseSeeder`.

- It only runs in `local` and `testing` environments.
- It creates placeholder files in the local `public` disk.
- It seeds sample archives and sample business references for `TABUNGAN`, `KREDIT`, and `BILYET DEPOSITO`.

Run it manually only when you want demo data:

```bash
php artisan db:seed --class=DevelopmentArchiveFixtureSeeder
```

## Rollback Notes

If the rollout needs to be reversed:

1. Set all three SIARDI feature flags to `false`.
2. Redeploy the previous stable application version.
3. Leave additive tables in place unless a separate, approved cleanup plan is prepared.

This rollback avoids destructive schema churn and preserves any manually linked references already entered.

## Known Operational Limits

- Reconciliation coverage is only enabled for `TABUNGAN`, `KREDIT`, and `BILYET DEPOSITO`.
- Branch mappings for `00` and `09` are seeded as inactive by default.
- The application treats mirrored DWH tables as current-state daily upsert sources and does not apply snapshot-date filtering in the app layer.
