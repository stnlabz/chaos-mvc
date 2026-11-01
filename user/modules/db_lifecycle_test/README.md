# Database Lifecycle Test — ChAoS MVC 1.2.2

Disposable database lifecycle fixture. Only the module-owned table
`db_lifecycle_test_records` is modified by this module's SQL actions.
Module Nuke remains a Core administration operation.

## Module-owned database lifecycle

Use `/admin/db_lifecycle_test` as a Level 9 administrator.
The module handles schema and data lifecycle independently:

| Current database state | Admin action | Expected result |
| --- | --- | --- |
| `missing` | **Install SQL** | Create the current schema and one reference row |
| `update` | **Update SQL** | Apply packaged `sql/patches/1.0.0-to-1.0.1.sql` |
| `current` | **Delete Data** | Remove records but retain the schema |
| `current` | **Data Reset** | Restore the canonical reference row |
| `invalid` | None | Reject mutations until the state is understood |

All mutations require POST, CSRF protection, and Level 9 administrator authority.
The controller accepts only the explicit lifecycle actions listed above, and
the model reads SQL from known module-owned files, never from a web request.

## Qualification of an existing 1.0.0 database

The observed VPS baseline was:

- schema target `1.0.0`, database state `current`
- reference record count `1`, reference row `YES`
- migration column `qualified_at`: `NO`

If the installed **module files** now declare schema target `1.0.1` while
the existing table still lacks `qualified_at`, the module reports `update`
and displays **Update SQL**. Click it to execute the module-owned migration.
Expected results: state `current`, reference count `1`, reference row
`YES`, and migration column `YES`.

This module-owned SQL workflow does **not** create a Core
`module_migrations` journal entry. It is distinct from the Core package
updater's migration handling, and is intended to qualify the module's own
schema lifecycle.

## Fresh installation

The packaged `sql/schema.sql` now creates the current 1.0.1 table,
including `qualified_at`. On a fresh installation, **Install SQL** should
immediately produce state `current` with one canonical reference row.

## Data lifecycle and Nuke

While state is `current`, **Delete Data** should change the count to 0
without dropping the table. **Data Reset** should restore one reference row.
Finally use Core's Level 9 **Module Nuke** action and verify
`db_lifecycle_test_records` is absent. Do not Nuke established modules.

The table and its records are disposable test data. This fixture does not
contain a general-purpose migration system.
