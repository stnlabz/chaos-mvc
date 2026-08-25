# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.8

## v1.1.9 Development — Unreleased

> Pre-release maintenance only. The current release remains v1.1.8. No v1.1.9
> release announcement, package, manifest, or supported Core update is available
> until deployment and testing on chaos-mvc.org are complete.

## Security
- Added CSRF verification to authentication, account, post, media, and installer state changes
- Restricted destructive actions to authenticated administrators and POST requests
- Regenerated session identifiers after successful authentication and hardened session cookies
- Stored password-reset tokens as SHA-256 hashes and enforced stronger password requirements
- Validated uploaded image size and MIME type, generated safe filenames, restricted deletion paths, and blocked executable files in the uploads directory
- Restored SQL identifier and simple `WHERE` clause validation in the shared model helpers
- Blocked unsafe URL schemes in both Markdown renderers
- Escaped untrusted content across authentication, error, traffic, account, media, post, and shared views
- Limited public post and comment queries to published and approved records
- Added route-segment and callable-method validation
- Added browser security headers and disabled public directory listings

## Installer
- Added the missing `password_resets` table to the fresh-install schema
- Added uniqueness constraints for account usernames, account email addresses, module slugs, post slugs, and password-reset tokens
- Standardized newly installed account and comment tables on `utf8mb4`

## Core Updater Status
- Core Updater development remains separate from the independently versioned module updater
- Core Updater files are not yet deployed or operational on chaos-mvc.org
- Core Updater verification is deferred until a test release is deliberately published

---

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
