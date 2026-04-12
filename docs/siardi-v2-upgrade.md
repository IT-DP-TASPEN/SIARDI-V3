# SIARDI V2 Backward-Compatible DWH Coverage Upgrade

## Audit Summary

Audit date: 2026-04-11

- Legacy SIARDI in `../SIARDI` is a Laravel 12 / Filament 3 application. The archive flow is centered around `ArchiveResource`, with separate recap logic in `RekapanArsip` and `RekapArsipController`.
- The production SIARDI schema in `siardiv3` already contains the legacy tables expected by that codebase, including `archives`, `categories`, `branch_offices`, `category_user`, `roles`, and `users`.
- The live production schema has drift relative to the old migrations. Examples found during the audit:
  - `users.email_verified_at` still exists, even though a legacy migration attempted to remove it.
  - category IDs are not sequential and include large IDs, so category logic must not hardcode IDs.
  - archive data quality is mixed, including blank `archive_code` values and malformed `archive_date` values.
- The legacy recap feature is file-count based and hardcodes category IDs. That is not sufficient for DWH reconciliation because file count is not the same as covered business entities.
- DWH inspection confirmed category-specific keys and branch columns:
  - `raw_savings`: business key `norekening`, CIF `nocif`, branch `locationid`
  - `raw_loans`: business key `id`, CIF `nocif`, branch `locationid`
  - `raw_time_deposits`: business key `nobilyet`, CIF `nocif`, branch `locationid`
- The DWH mirror tables expose an `as_of_date` column, but the application now treats `raw_savings`, `raw_loans`, and `raw_time_deposits` as current-state daily upsert sources rather than user-selectable snapshot-history sources.

## Extension Design

- Keep `archives` as the primary document entity and preserve legacy archive metadata fields unchanged.
- Add business-reference support through additive tables:
  - `category_reference_fields`
  - `archive_business_references`
  - `dwh_branch_mappings`
- Drive the upload form from `category_reference_fields` so category-aware inputs are configurable instead of being hardcoded into the UI.
- Match DWH coverage on distinct normalized business keys, not file count.
- Map SIARDI branch codes to DWH branch codes through `dwh_branch_mappings` because the codes differ in format and should not be inferred ad hoc in query logic.

## Implemented Changes

### Legacy module port

- Ported the archive resource, recap page, recap print route, branch office resource, category resource, and login flow into the Laravel 13 / Filament 5 application.
- Preserved legacy archive list, detail, edit, and download behavior for records with no business references.
- Replaced the old PDF preview package dependency with a first-party iframe/download rendering path.

### Additive schema

- Added `category_reference_fields` for per-category field configuration.
- Added `archive_business_references` for one-to-many archive-to-business-reference linkage.
- Added `dwh_branch_mappings` for SIARDI-to-DWH branch reconciliation.
- Brought the legacy production migration history into this repo so fresh environments can be built safely while production skips already-run migrations.

### Category-aware upload flow

- Extended the archive create/edit form so the legacy fields remain present.
- Added dynamic business-reference inputs based on the selected category.
- Enforced required business-reference validation only for categories configured in `category_reference_fields`.
- Added archive list filtering by business reference and archive detail rendering for linked references.

### Reconciliation and legacy linking

- Added `DwhCoverageRepository` to centralize reconciliation queries and matching rules.
- Added `DwhCoverageDashboard` for target, realized, missing, and coverage by branch/category.
- Added missing-record drilldown sourced from DWH target sets minus covered SIARDI business keys.
- Added `LegacyArchiveLinker` so legacy archives can be manually linked without automatic backfill writes.

## Matching Rules

- Matching is category-specific:
  - `TABUNGAN` -> `raw_savings`
  - `KREDIT` -> `raw_loans`
  - `BILYET DEPOSITO` -> `raw_time_deposits`
- The primary realized-count key is:
  - `savings_account_no` for tabungan
  - `loan_account_no` for kredit
  - `deposito_bilyet_no` for deposito
- `cif` is stored and validated but does not drive realized counts.
- Normalization uses trim + whitespace removal + uppercase normalization.
- Leading zeros are preserved because identifiers are treated as strings and never cast to numeric values.
- Multiple archives can point to the same business entity, but realized coverage counts distinct normalized primary keys.

## Backward Compatibility Notes

- Old archive records remain readable even when they have no business references.
- Legacy recap remains available as a file-count summary.
- Legacy categories outside the initial reconciliation scope still use the standard archive flow and are not forced into DWH matching.
- The design is additive. Existing archive columns were not repurposed.

## Rollout Safety

- Feature flags:
  - `SIARDI_ENABLE_BUSINESS_REFERENCES`
  - `SIARDI_ENABLE_DWH_RECONCILIATION`
  - `SIARDI_ENABLE_LEGACY_REFERENCE_LINKING`
- Recommended rollout:
  1. deploy code with all three flags off
  2. run additive migrations
  3. seed `category_reference_fields` and `dwh_branch_mappings`
  4. enable business references
  5. enable DWH dashboard
  6. enable legacy linking after operator validation

## Tests Added

- Legacy compatibility tests for archive view behavior without business references.
- Dynamic category-field tests driven by configuration.
- Matching normalization tests for whitespace and duplicate coverage handling.
- Reconciliation tests for target/realized/missing logic.
- Legacy-linking tests to ensure manual linking changes realized coverage.

## Known Limitations

- Initial reconciliation scope is limited to `TABUNGAN`, `KREDIT`, and `BILYET DEPOSITO`.
- Realized coverage is current distinct linkage against the current mirrored DWH state. If historical snapshot behavior is required, it must be handled in the ETL layer instead of inferred inside the application.
- Legacy suggestion parsing is heuristic and intentionally non-persistent until a user saves the linkage.
