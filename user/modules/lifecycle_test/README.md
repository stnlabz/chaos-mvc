# Lifecycle Test Module

Disposable ChAoS MVC module for testing module discovery, install/reinstall,
routing, administrative authorization, and uninstall on a test installation.

## Version

1.0.0

## Scope

- Public route: `/lifecycle_test`.
- Admin route: `/admin/lifecycle_test`, subject to Level 9 authority or
  explicitly granted Level 7 module administration permission.
- No model, database tables, schema, migration files, reference data, or
  persistent module data.
- No forms or data mutation actions.

## Test sequence

1. Copy the entire `lifecycle_test/` directory into the target
   `user/modules/` directory. Do not modify protected Core files.
2. Verify the module appears in Core module administration.
3. Open `/lifecycle_test` and `/admin/lifecycle_test`; confirm the
   **1.0.0** fixture marker.
4. Verify a Level 7 account without an explicit grant cannot access the
   module's administration route.
5. As a Level 9 administrator, uninstall through the native Core Modules UI.
6. Confirm the module directory is removed and the public route is unavailable.
7. Reinstall the same directory and verify both routes return.
8. Check that no module-owned database tables were added or removed.

## Updating and rollback

This initial fixture intentionally has no `update_url` or signing key.
Its presence in GitHub does **not** constitute a signed, remotely installable
update. ChAoS MVC Core requires a valid HTTPS update manifest, a verified
package hash, and a valid cryptographic signature before applying a module
update. Do not bypass or weaken that verification for the test.

To qualify updates, prepare a **separately signed** 1.1.0 package and trusted
HTTPS manifest with the Foundation's authorized release workflow. Keep the
same module slug and release signing identity. Confirm the 1.1.0 marker after
the update and verify filesystem-only rollback restores 1.0.0. Rollback
does not reverse database migrations; this fixture has none.
