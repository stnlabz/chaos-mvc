# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.8

<!-- [AI:GPT-5.6 Sol | 2026-08-25 UTC] -->
## v1.1.9 Maintenance (Unreleased)

### Security

- Added CSRF protection and POST-only state changes.
- Added isolated, signed Core update discovery and Core-only filesystem locks.
- Added signed-manifest Core package staging and protected-path validation.
- Added verified Core backups, hash-chained operation journals, and one-version rollback retention.
- Added atomic Core installation, ordered migrations, mandatory health verification, maintenance mode, and administrator/CLI recovery.
- Enforced administrator authorization on directly routed controllers.
- Restricted media uploads by MIME type, size, and generated filename.
- Added signed module metadata, package checksums, and safe ZIP extraction.
- Hardened session cookies, password resets, generated URLs, and Markdown links.
- Escaped stored content in public and administrative views.

### Fixed

- Preserved password-reset route tokens.
- Added missing password-reset and traffic schema tables.
- Fixed media lookup and deletion signatures.
- Added the missing account password action.
- Restored developer and capabilities routes that referenced missing dependencies.
- Repaired the administrative discovery-resource refresh action.

### Maintenance

- Defined the separate v1.1.9 Core Updater release-gate contract.
- Added Core package checksum, archive-shape, staging, and preflight checks.
- Added deterministic installed-Core ownership metadata; `chaos-mvc.org`-only release construction remains outside this distribution.
- Validated SQL identifiers and simple helper conditions.
- Added the existing-installation `1.1.9` migration.
- Added dependency, signature, renderer, schema, and syntax regression checks.
<!-- [End AI:GPT-5.6 Sol] -->

## v1.1.8	Release	August 24, 2026

## Added
- Registration route `/register → auth → register`
- Full registration flow integrated with `accounts_model::create($data)`
- Email address update functionality in admin accounts

## Fixed
- Admin account creation not submitting (routing + POST handling)
- Inconsistent controller-to-model data handling
- Broken signup method (now model-compliant)
- Session messaging not displaying reliably in account actions
- Admin account deletion incorrectly reporting "You cannot delete your own account" when deleting other accounts
- Account deletion routing corrected so Admin → Accounts uses the intended account deletion handler (closes #f145ac)

## Changed
- Standardized all account operations to array-based model input
- Enforced role assignment via `user_level` (1 = user, 9 = admin)
- Updated all registration forms to match new routing
- Refactored authentication controller for consistent MVC flow

## Cleaned
- Enforced PSR-12 formatting across accounts and auth systems
- Added DocBlock coverage to controllers and models
- Standardized database queries to positional bindings (`?`)
- Removed inconsistent AI-generated logic and patchwork code

## Notes
- v1.1.7 focused on modules
- v1.1.8 focuses on security

## Status
- Released

## v1.1.7 Release March 23, 2026

## Added
 - Module updating

## Version
- Bumped version to 1.1.7

## v1.1.7 Development March 19, 2026

# This version
is all about the **Chaos MVC** getting smarter about itself.

## Added
 - Traffic Monitoring System - Added internal request tracking to observe real-world usage, crawl behavior, and system interaction across routes. Observable in `admin`
 - Module update system (manual trigger, no auto updates)
- External update source support (`update_url`)

## Improved
 - Admin module interface (real-time update feedback)
 - Core version handling (centralized system version)

## Notes
 - Updates require user interaction (no automatic execution)
 - Core modules remain locked to system version
 - Addon modules are independently versioned and updated

## v1.1.6 Security Fix March 17, 2026

# Fixed
 - Horizontal Rule `<hr`> was missing, it has been added, e.g. `---` or `***` in `/app/lib/render_md.php`

## v1.1.6 Release March 16, 2026

# Added
 - Install check and steps for proper installation.
 - Example Module in the Developers Portal.

---

# Fixed
## Rendering
 - adjusted precedence in `/app/lib/render.php` to fix underscores in functions inline code being munged.

## Posts
 - added the missing `comments` table to allow commenting for logged-in users.
 - added a feed controller to produce an RSS feed of website Posts

---

# Version
 - Bumped version to 1.1.6

~~This is a working document for Monday, March 16, 2026~~
