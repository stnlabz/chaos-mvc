# Chaos MVC Module Creation Guide

**Audience:** Human Developers and AI Development Agents  
**Applies To:** Chaos MVC User Modules  
**Status:** Development Standard  
**Scope:** Module construction, database lifecycle, CSRF responsibilities, and module removal

---

## 1. Purpose

This document defines the standard development model for Chaos MVC user modules.

Chaos MVC follows a simple architectural principle:

> **Secure the Core. Grow outwards.**

Application-specific functionality belongs in modules.

A module must use the interfaces and lifecycle provided by Chaos MVC rather than modifying Core to satisfy module-specific requirements.

If a module cannot be implemented without changing Core behavior, the developer or AI agent must stop and identify the required Core change.

The module must not silently modify, bypass, duplicate, or replace Core behavior.

---

## 2. Core Boundary

User modules are developed beneath:

    user/modules/{module}/

A module owns its own:

- controller
- model
- views
- libraries
- SQL definitions
- SQL migration patches
- documentation
- module manifest
- module-specific data and behavior

A module does not own Chaos MVC Core.

Module development must not modify Core components merely to make an individual module work.

This includes, but is not limited to:

- Core routing
- Core Admin dispatch
- Core authentication
- Core database/model infrastructure
- Core updater behavior
- Core module lifecycle behavior
- Core Nuke/uninstall behavior

If a legitimate framework capability is missing, report the requirement for Core development rather than implementing a module-specific workaround inside Core.

---

## 3. Standard Module Structure

A database-backed module normally follows this structure:

    user/modules/{module}/
    ├── controllers/
    │   └── {module}.php
    ├── models/
    │   └── {module}_model.php
    ├── views/
    │   ├── index.php
    │   └── admin/
    │       └── index.php
    ├── lib/
    ├── sql/
    │   ├── schema.sql
    │   └── patches/
    ├── docs/
    │   └── CHANGELOG.md
    └── module.json

Not every module requires every optional directory.

For example, a module without database storage does not require SQL files.

The module must not add unused architecture merely because the directory is shown in this standard.

---

## 4. Responsibility Separation

Module responsibilities remain clearly separated.

### 4.1 Controller

The controller owns request-level behavior.

Examples include:

- choosing the requested action
- requiring Admin authorization
- requiring CSRF validation
- validating request flow
- calling the model
- selecting a view
- redirecting after successful state changes

The controller must not become the module's SQL layer.

### 4.2 Model

The model owns module data operations.

Examples include:

- querying module records
- inserting records
- updating records
- deleting records
- checking schema state where appropriate
- validating data required by persistence operations

SQL values must be passed through prepared parameters.

Request values must never be directly interpolated into SQL.

### 4.3 View

Views render information.

Views must not contain:

- database operations
- business logic
- router logic
- lifecycle mutations
- installation logic
- migration logic
- destructive module operations

Simple presentation conditionals are acceptable.

---

## 5. Admin Entry Point

A module Admin interface uses:

    public function admin($params = []): void

Chaos MVC Core is responsible for the outer Admin environment, including authentication and module dispatch.

The module remains responsible for authorization appropriate to its own Admin operations.

The module must explicitly control which Admin actions are accepted.

Example:

    private const ADMIN_ACTIONS = [
        'create',
        'update',
        'delete',
        'delete_data',
        'install_schema',
        'update_schema',
    ];

Request input must never be used to dynamically invoke arbitrary controller methods.

An action must be explicitly recognized before it can execute.

---

## 6. Admin Model Loading

When a module is entered through:

    /admin/{module}

Chaos MVC establishes module context during Admin dispatch.

Module-local models required by the Admin interface must therefore be loaded after the module context has been established.

They should normally be loaded from:

    admin()

or from a method called after `admin()` has begun execution.

Do not assume that loading a module-local model from the controller constructor will work correctly during Admin dispatch.

---

## 7. Database Ownership

A database-backed module owns only its declared module tables.

Owned tables are declared in:

    module.json

using the module's database table declaration.

Example:

    {
        "module": "shop",
        "database_tables": [
            "shop_products",
            "shop_orders",
            "shop_order_items",
            "shop_gateways"
        ]
    }

Table ownership follows the module namespace.

For module:

    shop

valid owned table names include:

    shop
    shop_products
    shop_orders
    shop_gateways

A table with an unrelated name is not made module-owned merely by placing it in the manifest.

The manifest declaration is both documentation and part of the module lifecycle contract.

This becomes especially important during Nuke.

---

## 8. Database Lifecycle

Database-backed modules use an explicit lifecycle.

The module must never silently install or migrate its database merely because a visitor opened a page.

The standard lifecycle is:

    Missing SQL
        ↓
    Public: Module unavailable
    Admin:  Install SQL available
        ↓
    Installed
        ↓
    Normal module operation
        ↓
    Pending schema update
        ↓
    Admin: Update SQL available
        ↓
    Current schema

Lifecycle state must be visible and deterministic.

### 8.1 Missing Database Schema

When required module tables do not exist, the public module must not:

- silently create tables
- attempt automatic installation
- expose partially functional behavior
- fail with an avoidable database exception

Instead, the public module reports that it is unavailable.

Example:

    Shop is currently unavailable.

The public request does not install anything.

### 8.2 Install SQL

When required schema is absent, the module Admin interface presents an explicit:

    Install SQL

action.

Installation is an intentional Admin operation.

It requires:

- authenticated Admin access
- POST
- valid CSRF protection

After successful installation, the module enters its normal operational state.

Opening `/admin/{module}` by itself must not constitute authorization to install SQL.

### 8.3 Schema Updates

Schema changes after initial release belong in:

    sql/patches/

A module with a pending supported schema migration presents:

    Update SQL

in Admin.

Updating SQL is also an explicit state-changing operation and requires:

- authenticated Admin access
- POST
- valid CSRF protection

The module must not silently modify its schema merely because Admin or a public page was loaded.

---

## 9. Data Lifecycle

Schema lifecycle and data lifecycle are separate concepts.

A module may need to clear its operational records without uninstalling the module.

For this purpose, database-backed modules provide:

    Delete Data

where appropriate.

Delete Data is a **module-owned operation**.

Its purpose is:

    Delete Data
        ↓
    Remove module records
        ↓
    Preserve module schema
        ↓
    Preserve installed module

After Delete Data completes:

- the module remains installed
- its required tables remain installed
- its Admin interface remains available
- the module can immediately accept new data

Delete Data is not Nuke.

### 9.1 Delete Data Requirements

Delete Data is destructive.

It therefore requires:

- authenticated Admin access
- POST
- valid CSRF protection
- explicit Admin action selection

GET requests must never delete module data.

A module must delete only records it owns.

Delete Data must not be used to delete unrelated application data.

Where multiple module-owned tables have dependencies, records must be removed in an order that preserves database integrity.

---

## 10. Module Nuke

Nuke is the complete removal lifecycle.

Nuke means:

    Module
    +
    Module-owned database tables
    =
    REMOVED

Unlike Delete Data, **Nuke is Core-owned**.

A module must not implement an independent module-local Nuke engine.

### 10.1 Nuke Control

A module Admin interface may expose a clearly identified destructive control:

    Nuke

The control initiates the established Chaos MVC Core uninstall/Nuke flow.

Conceptually:

    Module Admin
        ↓
    Nuke
        ↓
    POST /admin/uninstall
        ↓
    Chaos MVC Core

The module requests Nuke.

Core performs Nuke.

### 10.2 Core Nuke Responsibilities

During Nuke, Core is responsible for validating and removing the module.

The established lifecycle includes:

1. Authenticate the Admin request.
2. Validate CSRF.
3. Validate the requested module slug.
4. Reject attempts to Nuke protected Core modules.
5. Establish the module lifecycle operation.
6. Locate the exact installed module.
7. Read the installed module's `module.json`.
8. Read its declared database tables.
9. Validate ownership of those tables.
10. Remove the verified module-owned tables.
11. Remove the installed module.
12. Return control to the Admin environment.

The module must not bypass these checks.

### 10.3 Database Table Declaration

Core determines module-owned database resources from the installed module manifest.

For example:

    {
        "module": "shop",
        "database_tables": [
            "shop_products",
            "shop_orders",
            "shop_order_items",
            "shop_gateways"
        ]
    }

A module must accurately declare every table that belongs to its lifecycle.

Failure to declare a module-owned table can leave orphaned database resources after Nuke.

Declaring a table that the module does not own is invalid.

### 10.4 Why Core Owns Nuke

A broken module must still be removable.

If module-owned PHP were responsible for its own final destruction, a damaged or malicious module could:

- prevent its own removal
- execute arbitrary cleanup behavior
- target database objects it does not own
- leave an inconsistent installation behind

Core therefore determines the final removal operation from trusted lifecycle information and the installed module manifest.

A module declares what it owns.

Core verifies and removes it.

---

## 11. CSRF Responsibilities

Cross-Site Request Forgery protection is mandatory for state-changing module operations.

The responsibility is shared between Chaos MVC Core and the module depending on which component owns the action.

### 11.1 Module-Owned Actions

When the module owns a state-changing Admin action, the module is responsible for requiring valid CSRF protection.

Examples include:

- Create
- Update
- Delete
- Enable
- Disable
- Delete Data
- Install SQL
- Update SQL
- configuration changes
- other module-owned mutations

The normal request sequence is:

    POST
        ↓
    Admin authorization
        ↓
    CSRF validation
        ↓
    Explicit action validation
        ↓
    Input validation
        ↓
    Mutation
        ↓
    Redirect or render result

A state-changing operation must not execute before CSRF validation succeeds.

### 11.2 Forms

Admin forms that perform mutations must include the Chaos MVC CSRF field.

Where the framework helper is available, use the established helper rather than inventing a second CSRF mechanism.

For example:

    <?= $this->csrf_field(); ?>

The controller validates the submitted token using the established framework mechanism.

For example:

    $this->require_csrf();

Use the actual Chaos MVC helper contract available to the module.

Do not create a parallel module-specific token system when Core already provides CSRF infrastructure.

### 11.3 GET Requests

GET requests are for retrieval and navigation.

They must not perform destructive or state-changing module operations.

Do not implement behavior such as:

    /admin/module?action=delete&id=42

where merely requesting the URL deletes data.

Deletion requires POST and CSRF.

The same principle applies to:

- installation
- schema updates
- activation or deactivation
- configuration changes
- destructive cleanup
- other persistent mutations

### 11.4 Nuke CSRF

Because Nuke itself is Core-owned, the Nuke request must use the CSRF contract expected by the Core uninstall endpoint.

The module Admin interface may render the Nuke form/control, but it must not circumvent Core CSRF validation.

Conceptually:

    Module renders Nuke form
            ↓
        POST + CSRF
            ↓
    Core uninstall endpoint
            ↓
    Core validates request
            ↓
        Core performs Nuke

This preserves the authority boundary:

> **The module may request its removal. Core authorizes and performs it.**

---

## 12. Complete Data Lifecycle

Every database-backed Chaos MVC module follows a predictable lifecycle:

    ┌──────────────────┐
    │  Missing Schema  │
    └────────┬─────────┘
             │
             ├── Public → Unavailable
             │
             └── Admin → Install SQL
                         │
                         ▼
                ┌─────────────────┐
                │    Installed    │
                └────────┬────────┘
                         │
                  Normal Operation
                         │
              ┌──────────┴──────────┐
              │                     │
              ▼                     ▼
         Delete Data          Schema Update
              │                     │
       Records removed          Update SQL
       Schema preserved             │
       Module preserved             ▼
              │               Current Schema
              │                     │
              └──────────┬──────────┘
                         │
                         ▼
                       Nuke
                         │
                         ▼
               Core validates module
                         │
                         ▼
              Core validates ownership
                         │
                         ▼
                 Drop owned tables
                         │
                         ▼
                   Remove module

The lifecycle can be summarized as:

> **Unavailable → Install SQL → Operate → Update SQL when required → Delete Data when requested → Core Nuke when uninstalling.**

Each transition that changes persistent state is deliberate.

No lifecycle transition is silently triggered merely by viewing a page.

---

## 13. Lifecycle Authority

The ownership of each lifecycle operation must remain clear.

| Operation | Authority |
|---|---|
| Public unavailable state | Module |
| Detect required schema | Module |
| Present Install SQL | Module Admin |
| Install module schema | Established lifecycle mechanism |
| Present Update SQL | Module Admin |
| Apply supported schema update | Established lifecycle mechanism |
| CRUD | Module |
| Delete Data | Module |
| Present Nuke control | Module Admin |
| Authorize Nuke | Core |
| Validate table ownership | Core |
| Drop tables during Nuke | Core |
| Remove module | Core |

The most important distinction is:

> **Delete Data belongs to the module. Nuke belongs to Core.**

---

## 14. AI Development Agent Requirements

An AI agent developing a Chaos MVC module must:

1. Inspect the existing module before modifying it.
2. Respect the Core/module authority boundary.
3. Never modify Core without explicit authorization.
4. Preserve the standard database lifecycle.
5. Never silently install or migrate SQL.
6. Require POST and CSRF for module-owned mutations.
7. Use the existing Chaos MVC CSRF helpers.
8. Keep Nuke Core-owned.
9. Declare module-owned tables accurately in `module.json`.
10. Never use Nuke or Delete Data to target unrelated tables.
11. Keep database and business logic out of views.
12. Use prepared SQL parameters.
13. Use explicit action allowlists for Admin mutations.
14. Never dynamically execute a method supplied by request input.
15. Maintain `docs/CHANGELOG.md`.
16. Report a required Core capability rather than inventing a Core workaround.
17. Preserve existing working behavior unless modification is part of the authorized task.
18. Never claim a lifecycle or feature has been tested unless the test was actually performed.

Access to Core does not constitute authorization to modify Core.

The ability to make a change does not establish authority to make that change.

---

## 15. Human Developer Requirements

Human developers follow the same lifecycle and authority boundaries.

Before considering a database-backed module ready for release, verify:

- [ ] Public page handles missing schema without installing it.
- [ ] Public page does not fail merely because schema is absent.
- [ ] Admin detects missing schema.
- [ ] Admin presents Install SQL.
- [ ] Install SQL is explicit.
- [ ] Install SQL uses POST + CSRF.
- [ ] Normal Admin becomes available after installation.
- [ ] Pending schema updates can be detected.
- [ ] Update SQL is explicit.
- [ ] Update SQL uses POST + CSRF.
- [ ] CRUD mutations use POST + CSRF.
- [ ] Admin actions use an explicit allowlist.
- [ ] Request input cannot dynamically invoke arbitrary controller methods.
- [ ] Delete Data clears only module-owned records.
- [ ] Delete Data preserves schema.
- [ ] Delete Data preserves the installed module.
- [ ] Delete Data uses POST + CSRF.
- [ ] Nuke is exposed through the Core uninstall lifecycle.
- [ ] Nuke is not independently implemented by the module.
- [ ] `module.json` accurately declares owned database tables.
- [ ] All owned tables satisfy module ownership naming requirements.
- [ ] Core Nuke removes the declared tables.
- [ ] Core Nuke removes the module.
- [ ] Core Nuke returns cleanly to Admin.
- [ ] No Core files were modified merely to make the module work.
- [ ] `docs/CHANGELOG.md` reflects the module's current development state.

---

## 16. Qualification of Lifecycle Behavior

Implementation is not the same thing as demonstrated behavior.

A database-backed module should be tested through the complete lifecycle:

    Fresh module
        ↓
    Public unavailable
        ↓
    Admin Install SQL
        ↓
    Schema installed
        ↓
    Normal operation
        ↓
    CRUD operations
        ↓
    Delete Data
        ↓
    Schema remains
        ↓
    Module remains operational
        ↓
    Nuke
        ↓
    Core removes owned tables
        ↓
    Core removes module
        ↓
    Clean return to Admin

Where schema migration exists, qualification should additionally demonstrate:

    Old supported schema
        ↓
    Update SQL offered
        ↓
    Explicit POST + CSRF
        ↓
    Migration applied
        ↓
    Current schema
        ↓
    Normal operation

A developer or AI agent must distinguish between:

- designed behavior
- implemented behavior
- tested behavior
- confirmed behavior

Do not describe an implementation as confirmed merely because the code appears correct.

---

## 17. Development Principle

Chaos MVC modules grow functionality outward without compromising the framework that hosts them.

The module owns its application behavior.

Core owns framework authority.

Database state changes are explicit.

Destructive operations are protected.

Ownership is declared.

Nuke remains recoverable even when the module itself is broken.

The result should be predictable for the developer, predictable for the administrator, and predictable for the framework.

> **Secure the Core. Grow outwards.**