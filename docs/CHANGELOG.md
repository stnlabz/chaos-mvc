# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.8

## v1.1.9 Development — Unreleased

> Pre-release maintenance only. The current release remains v1.1.8 until the
> changes are deployed and tested on chaos-mvc.org.

## Security
- Added CSRF verification to login, registration, password recovery, and password reset operations (`CMSEC-2026-4827-A`)
- Restricted logout and protected account deletion to verified POST requests (`CMSEC-2026-4827-B`)
- Replaced plaintext password-reset token storage with SHA-256 digests and single-use token consumption (`CMSEC-2026-4827-C`)
- Enforced a consistent 12-character minimum for registration, password reset, and administrative password management (`CMSEC-2026-4827-D`)
- Added bounded login and password-recovery throttling with atomic state updates, per-account and per-source limits, restricted state-file permissions, and bounded state growth (`CMSEC-2026-4827-E`)
- Invalidated the previous session identifier after successful authentication (`CMSEC-2026-4827-G`)
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
- Hardened controller dispatch with an explicit public-action boundary while preserving aliases, administrative module delegation, clean post slugs, DB-driven module routing, and existing 404 behavior (`CMSEC-2026-4827-F`)
- Changed addon module updates to administrator-only POST requests with CSRF verification (`CMSEC-2026-4828-A`)
- Added HTTPS manifest validation, required SHA-256 package verification, bounded downloads, and authorized package-host enforcement for addon updates (`CMSEC-2026-4828-B`, `CMSEC-2026-4828-G`)
- Added module-scoped ZIP validation that rejects traversal, absolute paths, symbolic links, oversized archives, and files outside the requested module boundary (`CMSEC-2026-4828-C`)
- Added staged module installation with file backup, rollback, metadata preservation, and isolated temporary-workspace cleanup (`CMSEC-2026-4828-D`)
- Added level-9 authorization, CSRF verification, strict module identifiers, trusted ownership metadata, and confined deletion targets to addon uninstall operations (`CMSEC-2026-4828-E`, `CMSEC-2026-4828-F`)
- Prevented module uninstall cleanup from traversing top-level or nested symbolic links; links are removed without following their targets (`CMSEC-2026-4828-I`)
- Blocked module update connections to loopback, private, link-local, reserved, multicast, carrier-grade NAT, and mapped IPv6 destinations (`CMSEC-2026-4828-G`)
- Added cumulative count and byte limits for module-server HTTP headers and chunked-response trailers (`CMSEC-2026-4828-J`)
- Migrated module update and uninstall ownership from distributed `/app` paths to the self-contained `/user/modules/{slug}` boundary, including canonical slug validation and package identity checks (`CMSEC-2026-4828-K`)
- Connected PHPMailer to the installation-local SMTP configuration with strict validation and no source-embedded credentials (`CMSEC-2026-4829`)
- Escaped and validated addon metadata, module links, and developer-domain links in the module administration interface
- Removed installation database configuration and runtime authentication state from Git tracking
- Updated the bundled PHPMailer dependency to v7.1.1

## Reliability
- Updated authentication and logout forms to submit the required CSRF tokens and POST requests
- Added matching client-side 12-character password requirements to registration and password-reset forms
- Prevented recursive document-root rewrites of requests already routed under `public/`
- Closed the installer database test connection after successful validation
- Preserved password-reset tokens when translating clean authentication aliases
- Removed synchronous third-party update checks from module administration page rendering; update discovery now occurs only after an authenticated administrator action (`CMSEC-2026-4828-H`)
- Added explicit failures for missing, unreadable, or invalid installation SMTP configuration

## Module Update Contract
- Defined remote addon manifests as release metadata containing `version`, `download`, and the downloadable ZIP package’s `sha256`
- Kept installation-local module records responsible for the installed `version`, trusted `update_url`, descriptive metadata, owned `database_tables`, and any explicitly authorized alternate `package_hosts`
- Clarified that the local installed version changes only after a verified update succeeds
- Deferred signed-manifest enforcement until the Core and independent-developer key model is finalized and provisioned

## Qualification Pending
- Run live authentication coverage for login, logout, registration, password recovery, password reset, throttle limits, token replay rejection, and administrative account operations
- Run live regression coverage for router aliases, administrative module delegation, clean post slugs, rejected controller helpers, and 404 behavior
- Validate addon update staging, rollback, manifest SHA-256 enforcement, destination blocking, and authorized alternate package hosts with controlled test packages
- Validate addon uninstall behavior with module-owned files and metadata-declared database tables
- Migrate remaining addon tracking records and remote manifests to the finalized local/remote contract
- Save production SMTP settings and verify password-recovery and post-notification delivery

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
