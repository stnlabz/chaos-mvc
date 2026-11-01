# Database Lifecycle Test — ChAoS MVC 1.2.2

Disposable, database-backed fixture. No Core table is modified.

## Baseline 1.0.0

1. Install the module as `user/modules/db_lifecycle_test/`.
2. Visit `/admin/db_lifecycle_test` as a Level 9 administrator.
3. Observe `missing`; click **Install Disposable SQL**.
4. Confirm `current`, record count `1`, reference row `YES`, and migration column `NO`.

## Migration to 1.0.1

Publish a version 1.0.1 ZIP and HTTPS manifest using the existing DCT/ChAoS module updater:
- update `module.json` version and schema_version to `1.0.1`;
- add `"migrations": {"1.0.0-to-1.0.1": "sql/patches/1.0.0-to-1.0.1.sql"}`;
- retain the same declared database table and supplied SQL patch;
- set the installed 1.0.0 module's HTTPS `update_url` to the released 1.0.1 manifest.

The Core updater must apply the exact declared migration, record it in
`module_migrations`, and activate the 1.0.1 files. Admin should then show
`current`, record count `1`, reference row `YES`, migration column `YES`.

## Nuke

Use the existing Level 9 Module Nuke operation and confirm
`db_lifecycle_test_records` is absent afterward, while unrelated Core tables remain.
Do not Nuke an established module to perform this test.

## Caveats

This fixture is intentionally versioned at **1.0.0** so the update can be
measured. Do not edit the installed 1.0.0 `module.json` to pretend the module
has updated: that would bypass the Core migration journal test.

No production PHP, routing, or Core changes are necessary. Test only on the VPS.
