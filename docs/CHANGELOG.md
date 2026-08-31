# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.9

## v1.1.10 Development — Unreleased

> Pre-release maintenance only. The current release remains v1.1.9 until the
> changes are deployed and tested on chaos-mvc.org.

## Security
- Added an optional bootstrap perimeter hook that invokes an installed Sentinel module's callable `inspect()` entry point before normal MVC initialization while allowing installations without Sentinel to continue unchanged
- Restricted Admin navigation and Admin → Modules listings to user modules whose own controller declares a public `admin()` method, using token inspection without executing user-land PHP
- Separated module update discovery from installation verification: status checks compare local and remotely announced versions, while installation continues enforcing package host, SHA-256, signing, archive, and migration requirements

## Reliability
- Added explicit service-module parameter routing through `module.json`'s `index_parameters` declaration, allowing URLs such as `/s/{code}` to reach the module's declared `index()` action without treating the value as a method name
- Aligned controller ownership validation with the router so one-character user-module slugs such as `s` can establish their confined module context
- Aligned Admin dispatch, public `admin()` discovery, update checks, update execution, and Nuke with one-character module slugs, allowing service modules such as `s` to use their complete authorized administration lifecycle
- Made module uninstall filesystem cleanup fail explicitly when a file, link, or directory cannot be removed, and added administrator success confirmation after completed removal
- Removed the obsolete `app/controllers/admin_old.php` maintenance copy

---

## v1.1.9 Release — August 29, 2026

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
- Added exact-file module class ownership checks, pinned RSA-SHA256 developer signatures, link-aware destination confinement, portable ZIP path collision rejection, atomic whole-directory updates, transactional uninstall quarantine, and separation of signed release metadata from installation-local trust (`CMSEC-2026-4828-L` through `CMSEC-2026-4828-R`)
- Migrated module discovery and administration navigation to verified `/user/modules/{slug}` metadata without executing user PHP during listing (`CMSEC-2026-4830-A`, `CMSEC-2026-4830-B`)
- Added exact controller-file ownership and explicit `module.json` route declarations for user-module HTTP dispatch (`CMSEC-2026-4830-C`, `CMSEC-2026-4830-D`)
- Preserved `/admin/{module}` dispatch for router-prevalidated user controllers while continuing to reject classes owned by any other file (`CMSEC-2026-4830-C1`)
- Required administrator authentication before `/admin/{module}` can load user-module PHP, and reserved module `admin()` methods from direct public routing
- Confined user-module controllers, models, and views to real, unlinked files beneath the selected `/user/modules/{slug}` ownership boundary
- Serialized update and uninstall transactions with per-module locks (`CMSEC-2026-4830-E`)
- Removed the internal traffic collector from HTTP routing, bounded request-derived traffic fields, and added retention pruning (`CMSEC-2026-4830-F`, `CMSEC-2026-4830-G`)
- Escaped administrator health diagnostics contextually (`CMSEC-2026-4830-H`)
- Prevented `/install` from reopening after a completed installation when the runtime lock file is missing by verifying the configured database contains an administrator account (`CMSEC-2026-4831-A`)
- Connected PHPMailer to the installation-local SMTP configuration with strict validation and no source-embedded credentials (`CMSEC-2026-4829`)
- Escaped and validated addon metadata, module links, and developer-domain links in the module administration interface
- Removed installation database configuration and runtime authentication state from Git tracking
- Updated the bundled PHPMailer dependency to v7.1.1
- Added signed-package, exact-version module database migrations with module-owned table confinement, durable replay prevention, and explicit partial-DDL failure reporting (`CMSEC-2026-4832-A` through `CMSEC-2026-4832-C`)
- Added authenticated asynchronous module update discovery so Admin → Modules compares installed versions with each configured remote announcement and enables installation when a newer release is available (`CMSEC-2026-4833-A` through `CMSEC-2026-4833-C`)

## Reliability
- Reserved `/` and `/home` for the confined installation-owned Home module instead of mapping the protected Home route to `/app/controllers/home.php`
- Added explicit, ownership-checked cross-module model loading for user modules that intentionally collaborate
- Replaced site-specific shared includes with a neutral, self-contained Classic Core fallback that does not depend on `/public/assets/`
- Established user-module ownership context before invoking module constructors, so constructor-time model and view resolution stays confined to `/user/modules/{slug}` instead of incorrectly falling back to Core (`CMSEC-2026-4830-I`)
- Aligned the Core theme resolver with Theme Builder's `/user/themes/{slug}/inc` layout while retaining root-level theme-part compatibility (`CMSEC-2026-4830-J`)
- Added installation-owned PHP themes under `/user/themes/{slug}`, authenticated theme selection at `/admin/themes`, Core layout fallback, and confined active-theme asset delivery
- Exposed the established `$SITE`, page, Open Graph, renderer, URL, and session context to active theme `head.php`, `nav.php`, and `foot.php` layouts while preserving `active_theme` across site-identity edits
- Updated authentication and logout forms to submit the required CSRF tokens and POST requests
- Added matching client-side 12-character password requirements to registration and password-reset forms
- Prevented recursive document-root rewrites of requests already routed under `public/`
- Closed the installer database test connection after successful validation
- Preserved password-reset tokens when translating clean authentication aliases
- Removed synchronous third-party update checks from module administration page rendering; update discovery now occurs only after an authenticated administrator action (`CMSEC-2026-4828-H`)
- Kept module update discovery non-blocking by checking remote version announcements asynchronously after Admin → Modules renders; unavailable developer servers no longer delay the initial page response
- Added explicit failures for missing, unreadable, or invalid installation SMTP configuration
- Consolidated duplicate error controllers into one bootstrap-registered Core handler for intentional HTTP errors, uncaught exceptions, reportable PHP errors, and fatal shutdown failures
- Added safe site-styled error responses, dependency-free emergency rendering, private incident logging, reference identifiers, and removal of partial response output
- Restored bootstrap-provided `$SITE` identity inside normal and Core error view scopes so installation-specific titles and copyright values no longer fall back to `Chaos MVC`

## Module Update Contract
- Defined remote addon manifests as authenticated release metadata containing `module`, `version`, `download`, the downloadable ZIP package’s `sha256`, `key_id`, and an embedded Base64 `signature`
- Required update ZIP archives to contain one self-contained `{slug}/` directory with a matching `module.json` and controller
- Kept installation-local module records responsible for the installed `version`, trusted `update_url`, pinned developer `signing` configuration, owned `database_tables`, explicit HTTP `routes`, and any authorized alternate `package_hosts`
- Standardized developer release signing on RSA-3072 with SHA-256 using one developer keypair across that developer’s products; private keys remain outside the repository and web root
- Clarified that the local installed version changes only after a verified update succeeds
- Kept Core Updater signing separate from the developer-module signing contract; no Core public-key file or Core signature requirement is currently imposed
- Reserved `sql/schema.sql` for fresh installs and defined exact update patches as `sql/patches/{installed-version}-to-{target-version}.sql` in packaged `module.json` migrations metadata
- Required migration SQL to travel inside the signed and hash-verified module ZIP; migration SQL is never downloaded independently or inferred
- Limited migration statements to declared module-owned tables and records each completed transition with its signed package hash before activating updated source
- Made retries skip only an identically recorded transition, preventing old migrations from replaying on later update checks

## Qualification Pending
- Run live authentication coverage for login, logout, registration, password recovery, password reset, throttle limits, token replay rejection, and administrative account operations
- Run live regression coverage for router aliases, administrative module delegation, clean post slugs, rejected controller helpers, and 404 behavior
- Validate user-module discovery, declared public routes, `/admin/{module}` delegation, and class-collision rejection with controlled modules
- Validate addon signature enforcement, atomic directory replacement and rollback, stale-file removal, destination blocking, maintenance locking, and authorized alternate package hosts with controlled signed packages
- Validate addon uninstall behavior with module-owned files and metadata-declared database tables
- Migrate remaining addons and remote manifests to the self-contained `/user/modules/{slug}` and signed-manifest contract
- Save production SMTP settings and verify password-recovery and post-notification delivery
- Validate the Contact 1.0.0-to-1.1.0 signed migration preserves existing records, adds the required primary-key auto-increment behavior, and accepts a new submission

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
