# Chaos MVC
**CHANGELOG**

**Current Version:** 1.1.9

## v1.1.10 Development — Unreleased

> Pre-release maintenance only. The current release remains v1.1.9 until the
> changes are deployed and tested on chaos-mvc.org.

## Remaining Issues
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
  
### Updating

- Update Chaos MVC from within the site admin
- Backup and rollback capable
- Only the **Core** gets updated (`/app`)
- User content (`modules`, `themes`, and libraries) remains untouched
- Only allow updates from the two previous versions; installations older than current release -2 require a manual update
- Manual download and update remains available