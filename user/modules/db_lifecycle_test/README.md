# Database Lifecycle Test — ChAoS MVC 1.2.2

Disposable, database-backed fixture. No Core table is modified.

## Baseline 1.0.0

1. Install the module as `user/modules/db_lifecycle_test/`.
2. Visit `/admin/db_lifecycle_test` as a Level 9 administrator.
3. Observe `missing`; click **Install Disposable SQL**.
4. Confirm `current`, record count `1`, reference row `YES`, and migration column `NO`.

## Migration to 1.0.1

The repository fixture is now **1.0.1**. Its `module.json` declares
`schema_version: 1.0.1` and the exact migration
`sql/patches/1.0.0-to-1.0.1.sql`, which adds the `qualified_at` column.

1. **Leave the VPS-installed 1.0.0 files intact**; the baseline schema and its reference row are already verified.
2. Package the repository's 1.0.1 fixture with DCT. The updater supports `db_lifecycle_test/` and `db_lifecycle_test-1.0.1/` ZIP roots.
3. Publish the ZIP and HTTPS update JSON with its actual SHA-256 checksum. No RSA keys or signatures.
4. Ensure the installed 1.0.0 module's `update_url` points to the published update manifest.
5. Trigger the standard Admin module Update action, not a direct edit of installed version metadata.

The Core updater must apply the declared migration and record it in
`module_migrations` before activating the 1.0.1 files. After updating,
`/admin/db_lifecycle_test` should show `current`, record count `1`,
reference row `YES`, and migration column `YES`.

This migration is a one-time qualification transition; it must not be rerun
manually after the updater reports success.

## Nuke

Use the existing Level 9 Module Nuke operation and confirm
`db_lifecycle_test_records` is absent afterward, while unrelated Core tables remain.
Do not Nuke an established module to perform this test.

## Caveats

The **installed** 1.0.0 baseline must remain unchanged until the updater
applies the repository's 1.0.1 release. Do not edit the installed module's
version fields manually: doing so bypasses the migration qualification.

No production PHP, routing, or Core changes are necessary. Test only on the VPS.
