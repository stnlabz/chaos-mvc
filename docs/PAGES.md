# ChAoS MVC Pages
**CHANGELOG**

**Subsystem:** Core Pages  
**Status:** Development  
**Introduced:** ChAoS MVC v1.2.1 Development

## Initial Development — September 12, 2026

## Architecture
- Added the Core Pages subsystem as a filesystem-backed content system.
- Established `/user/pages/{slug}/` as the canonical ownership boundary for each page.
- Defined the page directory slug as the canonical page identifier.
- Defined `page.json` as the page metadata authority.
- Defined one `main.*` file as the page content source.
- Supported content files:
  - `main.md`
  - `main.html`
  - `main.txt`
  - `main.json`
- Kept Pages independent of the database and removed the previous database-driven page/module fallback from the public page-resolution path.

## Page Metadata
- Established the initial `page.json` contract:
  - `slug`
  - `title`
  - `description`
  - `status`
  - `content_file`
  - `created_at`
  - `updated_at`
- Supported page states:
  - `draft`
  - `published`
- Required the directory slug and metadata slug to match.
- Restricted public resolution to published pages.

## Routing
- Added filesystem Page resolution after existing Core-controller and user-module ownership checks.
- Established public routing precedence:
  1. Core controller
  2. User module
  3. Core Page
  4. 404
- Added clean root-level Page URLs such as `/test-page`.
- Added dedicated page-slug validation supporting lowercase letters, numbers, and hyphens.
- Preserved existing controller/module identifier validation rather than weakening it for Pages.
- Kept `/page/{slug}` and `/pages/{slug}` outside the public Pages contract.

## Administration
- Added Pages administration through the existing Core Admin delegation path at `/admin/page`.
- Added filesystem page discovery without a database registry.
- Added page creation.
- Added page editing.
- Added slug changes with directory rename.
- Added title and description editing.
- Added draft/published state management.
- Added content-file selection.
- Added content editing.
- Added deletion through a filesystem trash boundary.
- Page deletion moves the page directory into `/user/pages/.trash/` instead of immediately destroying it.

## Rendering
- Added the public Pages view at `/app/views/pages/dynamic.php`.
- Integrated Pages with the existing site header/footer rendering path.
- Changed Markdown Page rendering to use the established Markdown renderer against the actual content file:
  `$render_md->markdown_file($contentPath);`
 
- Added resolved `content_path` data from the Core Pages subsystem so Markdown rendering operates on the source file rather than reparsing content through `content_renderer`.
- Kept HTML, plain-text, and JSON page handling separate from Markdown rendering.
- Added passive JSON publication through `main.json`.
- Valid JSON documents are decoded, pretty-printed, escaped, and rendered inside a JSON code block.
- JSON Pages do not execute source content and do not use JSON as a Pages configuration or layout language.
- PHP page execution is not part of the current Pages content contract.

## Validation
- Verified filesystem creation of:
 
  `/user/pages/test-page/`
  ├── `page.json`
  └── `main.md`
  
- Verified draft pages remain inaccessible publicly.
- Verified published Pages resolve from their clean root-level route.
- Verified Markdown Pages reach the established Markdown rendering pipeline.
- Verified JSON Pages accept and render valid JSON documents through `main.json`.
- Verified JSON content remains passive data and is safely escaped for browser output.
- Verified the Pages subsystem was pushed to the project repository after live testing.

## Files Introduced or Updated
- `/app/core/pages.php`
- `/app/controllers/page.php`
- `/app/core/router.php`
- `/app/views/pages/dynamic.php`
- `/app/views/admin/pages.php`

## Notes
- Pages originated conceptually from the JSON-lite Pages subsystem used by the earlier ChAoS CMS.
- The current ChAoS MVC implementation is filesystem-authoritative and does not use the database as a Pages registry.
- Core Pages is considered operational after successful public routing, Markdown rendering, and JSON publication validation.
