# ChAoS MVC Example User Module

## Purpose

Example is the canonical working reference for the current ChAoS MVC user-Module architecture. It is intentionally small enough to inspect while demonstrating the major facilities a normal Module may require.

## Structure

```text
example/
├── controllers/
│   └── example.php
├── models/
│   └── example_model.php
├── views/
│   ├── index.php
│   └── admin/
│       └── index.php
├── sql/
│   ├── schema.sql
│   ├── references.sql
│   └── patches/
│       └── 1.9.0-to-2.0.0.sql
├── docs/
│   ├── README.md
│   └── CHANGELOG.md
└── module.json
```

## Public View

`/example` explains what the Example Module is. It deliberately does not act as a public CRUD application.

## Administration

`/admin/example` explains and exercises the implementation.

Every state-changing operation uses POST, the ChAoS MVC CSRF field, an explicit action value, and a fixed controller dispatch path.

## Module Lifecycle

The model reports one deterministic database state:

- `missing` — one or more required tables are absent;
- `update` — the installed schema version differs from the target and an exact migration exists;
- `current` — required tables exist and schema version equals the manifest target;
- `invalid` — state cannot be established or no exact migration exists.

`installSchema()` applies `sql/schema.sql` and then restores canonical reference data.

`updateSchema()` accepts only an exact `{current}-to-{target}.sql` migration and updates the schema state record after the migration succeeds.

## Data Lifecycle

The data lifecycle is separate from schema installation and update.

`deleteData()` deletes Example-owned records while preserving the installed schema.

`restoreReferenceData()` applies `sql/references.sql` without deleting existing mutable records.

`resetData()` is the explicit Data Reset operation:

```text
deleteData()
    ↓
restoreReferenceData()
```

The Module remains installed and its schema remains intact.

## CRUD

The Admin implementation demonstrates all four CRUD operations:

- Create — `createRecord()`
- Read — `getRecords()` and `getRecord()`
- Update — `updateRecord()`
- Delete — `deleteRecord()`

The model validates record identifiers, title length, body length, and active state normalization. SQL values are passed through the model/database parameter interfaces rather than interpolated from request input.

## Canonical Reference Data

`sql/references.sql` supplies known Example records. These records make Data Reset observable and deterministic.

Developers may modify Example while learning, then use Data Reset to return its data to the package-defined reference state.

## Boundary

Example demonstrates Module-owned lifecycle and data operations without modifying ChAoS MVC Core. It is a reference implementation, not a requirement that every Module use SQL or implement every lifecycle operation.
