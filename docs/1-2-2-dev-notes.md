# v1.2.2 Development Notes
Various things to be addressed, added, removed or updated.

## PHP 8.5 Compatibility — cURL lifecycle
- Remove the deprecated `curl_close()` call from `app/controllers/site_json.php`. Review confirmed no other ChAoS MVC Core use of `curl_close()`.

## Web Server Compatibility
See PREFLIGHT.md

## ChAoS MVC Logging
Core Logging Reliability — Investigation Required
Major errors are not consistently reaching the ChAoS log configured in app/bootstrap.php. Determine whether the failure originates from PHP 8.5 behavior/configuration, PHP SAPI/web-server environment, filesystem capability, bootstrap initialization order, or ChAoS error handling. Do not prescribe a Core correction until the failure path is established.

## Concerns

- What relevant security and compatibility intelligence has emerged since the previous review, and does any of it apply to ChAoS MVC?
  - MariaDB Security Announcements
  - PHP
  - cURL
  - Web Servers
    - LiteSpeed / OpenLiteSpeed
    - Apache
    - IIS
    - Other relevant platforms
  - OpenSSL
  - CMS Security Concerns
    - WordPress
    - Joomla
    - Drupal

### Review Rule

External security or compatibility intelligence does not establish a ChAoS MVC issue by itself.

For each concern:

1. Identify the reported vulnerability, compatibility issue, or behavioral change.
2. Identify the underlying mechanism or affected technology.
3. Determine whether ChAoS MVC uses or exposes an equivalent mechanism.
4. Determine whether the concern is applicable to ChAoS MVC.
5. Record the evidence supporting the determination.
6. Do not modify ChAoS MVC solely because another project or platform is affected.

A concern may be classified as:

- **Applicable** — evidence establishes that ChAoS MVC is affected.
- **Potentially Applicable** — a relevant mechanism exists, but impact has not been established.
- **Not Applicable** — the affected mechanism does not apply to ChAoS MVC.
- **Unknown** — available evidence is insufficient to make a determination.

A finding does not authorize a patch.

Patch authorization remains governed by the established ChAoS MVC Patch Requirements.