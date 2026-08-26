# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.8

## v1.1.9 Development — Unreleased

> Pre-release maintenance only. The current release remains v1.1.8 until the
> changes are deployed and tested on chaos-mvc.org.

## Security
- Added administrator authorization and CSRF verification to account, media, post, installer, and site-configuration operations
- Restricted media and post deletion to verified POST requests
- Added image MIME, size, generated-filename, and upload-path protections
- Blocked executable files in the public uploads directory
- Added secure session-cookie defaults and disabled production error display by default
- Validated generated site URLs rather than trusting the HTTP Host header
- Restored SQL identifier and parameterized `WHERE` validation in shared model helpers
- Blocked unsafe URL schemes in Markdown and content renderers
- Escaped untrusted database and request content across public and administrative views
- Limited public post and comment retrieval to published and approved records
- Replaced referer-based comment redirects with a validated internal post route
- Restricted generated site and mail configuration files to owner-only permissions where supported

## Reliability
- Prevented recursive document-root rewrites of requests already routed under `public/`
- Closed the installer database test connection after successful validation

## Installer
- Added the missing `password_resets` table to the fresh-install schema
- Added uniqueness constraints for account usernames, account emails, module slugs, post slugs, and reset tokens
- Standardized newly installed account and comment tables on `utf8mb4`

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
