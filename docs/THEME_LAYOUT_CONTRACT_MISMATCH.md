# Theme layout contract mismatch

Tracking: `CMSEC-2026-4830-J`

Theme Builder and existing installation themes place `head.php`, `nav.php`,
and `foot.php` beneath `/user/themes/{slug}/inc`. The initial Core resolver
looked only in the theme root, causing otherwise complete generated themes to
be omitted from administration and preventing activation.

Core now prefers the established `inc/` structure and retains root-level
lookup for themes produced during the initial implementation window. All
resolved files remain confined beneath the selected, unlinked theme root.
