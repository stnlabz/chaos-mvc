# ChAoS MVC Admin Access

ChAoS MVC separates an account's classification from its authority over individual module administration interfaces.

## Account Classifications

| Classification | Authority level | Admin behavior |
| --- | ---: | --- |
| User | 1 | No Admin access |
| Developer | 7 | Access only to explicitly granted module Admin interfaces |
| AI | 7 | Access only to explicitly granted module Admin interfaces |
| Administrator | 9 | Unrestricted Admin access |

Authority levels are derived by Core from the selected classification. They are not accepted as arbitrary account form input.

## Module Grants

A level-9 administrator assigns Developer and AI module access through **Admin → Accounts**.

The grant identifies a module slug and applies only to that module's Admin interface. Developer and AI accounts receive no module access by default. Changing an account to User or Administrator removes its stored module grants because those classifications do not use the level-7 grant model.

The Admin dashboard displays only granted module interfaces for Developer and AI accounts. This filtering is presentation behavior, not the security boundary. Core also enforces the grant when processing:

- `/admin/{module}` delegation;
- direct Core controller `admin()` routes; and
- direct user-module controller `admin()` routes.

An authenticated level-7 account that requests an ungranted module Admin route receives an HTTP 403 response.

## Protected Core Components

The following components cannot be assigned to Developer or AI accounts:

- Accounts
- Admin
- Authentication
- Error Handler
- Installer
- Modules
- Core Updater

Core maintenance, account administration, module lifecycle authority, and other protected operations remain restricted to level-9 administrators.

Individual module actions must continue to use the established Core Admin authorization and CSRF controls. A module grant does not waive action-specific validation or grant ambient Core authority.

## Database Contract

Module grants are stored in the Core-owned `account_module_access` table:

| Column | Purpose |
| --- | --- |
| `account_id` | References the account receiving access |
| `module_slug` | Identifies the granted module Admin interface |

The composite primary key prevents duplicate grants. The account foreign key uses `ON DELETE CASCADE`, and successful module removal clears grants for the removed module.

Clean installations create the table from `app/install/schema.sql`. Existing installations initialize it through the Core account-access service when the feature is first used. If grant state cannot be established, restricted access fails closed.

## Module Discovery

Only modules that expose a public `admin()` method are eligible for assignment or display in the Admin dashboard. A public-facing module without an Admin interface is not listed as an assignable Admin module.

The module itself does not assign access. Classification, discovery, grant storage, dashboard filtering, and enforcement remain Core-owned responsibilities.
