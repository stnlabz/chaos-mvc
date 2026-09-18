# Changelog

All notable changes to the ChAoS MVC Example user Module are recorded here.

## 2.0.0 — 2026-09-17

- Rebuilt Example against the current ChAoS MVC Module architecture.
- Added deterministic database lifecycle states: `missing`, `update`, `current`, and `invalid`.
- Added Module-owned SQL installation.
- Added exact-version schema migration from 1.9.0 to 2.0.0.
- Added the `example_schema` lifecycle state table.
- Added canonical `sql/references.sql` data.
- Added Delete Data while preserving installed schema.
- Added explicit Data Reset that deletes mutable Example data and restores canonical reference data.
- Retained and completed Create, Read, Update, and Delete demonstrations.
- Added record existence validation before update and delete.
- Added POST + CSRF protection to every mutation.
- Added visible success and failure status to Admin operations.
- Simplified the public index to explain what Example is.
- Rebuilt the Admin index to explain what the implementation demonstrates and provide the functional lifecycle/CRUD laboratory.
- Removed the obsolete decorative `views/admin/example.php`.
- Updated module metadata and documentation.

## 1.9.0 — 2026-09-01

- Modernized the module as a user-module example.
- Added public Controller → Model → View operation.
- Added authenticated Admin record CRUD.
- Added module-owned `example_records` SQL schema.

## 1.8.9 — Initial Instructional Design

- Provided a minimal public Example controller and view.
- Provided basic module metadata and an Admin entry point.
