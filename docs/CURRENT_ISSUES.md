# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.8

## v1.1.9 Development — Unreleased

> Pre-release maintenance only. The current release remains v1.1.8 until the
> changes are deployed and tested on chaos-mvc.org.

## Remaining Issues
 ***Modules***
  - Read Remote JSON
  - Verify with modules `module.json`
  - Update if needed
  - Migrate `SQL` if needed
  - Run Module test
  - If Pass, update
  - if fail, roll back
  - on success, `unlink()` files in `/tmp`
  - Delete (`nuke`) modules from `/admin/modules`
   
 ***Themes***
  - Read Remote JSON
  - Verify with themes `theme.json`
  - Update if needed
  - Run local test
  - If Pass, update
  - if fail, roll back
  - on success, `unlink()` files in `/tmp`
  - Delete (`nuke`) themes from `/admin/themes`
  - Update `/admin/themes` view to be more in line with Admin Flow.

