# Chaos MVC
**Core SEO and Discovery**

## Purpose

Chaos MVC generates public discovery artifacts from the framework's current ownership model rather than treating the legacy modules database table as an authoritative registry.

The Core SEO/discovery layer covers:

- `sitemap.xml`
- `ror.xml`
- `llms.txt`
- `rss.xml`

The sitemap, ROR, and LLMS generators describe routable public resources. RSS is content-oriented and publishes Posts through the existing Posts subsystem.

---

## Authority Model

SEO discovery follows the same ownership boundaries used by current Chaos MVC routing.

### Core Routes

Public Core routes are discovered from:

```text
/app/controllers/*.php
```

Controllers that are administrative, internal, infrastructure-only, or themselves discovery generators are excluded from public discovery output.

The site root is represented by the Home route.

### User Modules

User modules are discovered from:

```text
/user/modules/{slug}/
```

A user module is eligible for public discovery only when its filesystem identity is internally consistent.

The discovery generator verifies:

1. The module directory uses a valid module slug.
2. `module.json` exists.
3. `module.json` identifies the same module slug.
4. The matching module controller exists at:

   ```text
   /user/modules/{slug}/controllers/{slug}.php
   ```

5. The module declares `index` in its public `routes` array.

Discovery does not execute user-module PHP.

The legacy modules database table is not authoritative for sitemap, ROR, or LLMS generation.

### Core Pages

Core Pages are discovered from the filesystem-backed Pages subsystem:

```text
/user/pages/{slug}/
├── page.json
└── main.*
```

Only valid Pages with:

```json
"status": "published"
```

are included in public SEO/discovery artifacts.

Draft Pages are not published through sitemap, ROR, or LLMS output.

---

## Sitemap

Controller:

```text
/app/controllers/sitemap.php
```

Generated artifact:

```text
/public/sitemap.xml
```

The sitemap contains unique public URLs discovered from:

- public Core routes
- valid user-module root routes
- published Core Pages

URLs are de-duplicated and sorted deterministically before XML generation.

The sitemap does not use the legacy modules table.

---

## ROR

Controller:

```text
/app/controllers/ror.php
```

Generated artifact:

```text
/public/ror.xml
```

ROR uses the same public-resource discovery boundary as the sitemap:

- public Core routes
- valid user modules
- published Core Pages

Resource entries may include their title, URL, and available description metadata.

Site identity is taken from the installation's site configuration rather than being hardcoded to a particular Chaos MVC installation.

The ROR generator does not use the legacy modules table.

---

## LLMS

Controller:

```text
/app/controllers/llms.php
```

Generated artifact:

```text
/public/llms.txt
```

The LLMS map is divided into public ownership groups:

```text
Core Routes
User Modules
Pages
```

This makes the generated resource map reflect the same Core/userland/Page separation used by Chaos MVC itself.

The LLMS generator does not use the legacy modules table.

---

## RSS

Controller:

```text
/app/controllers/rss.php
```

Generated artifact:

```text
/public/rss.xml
```

Public controller route:

```text
/rss
```

RSS differs intentionally from sitemap, ROR, and LLMS discovery.

RSS is a content feed and uses the existing Posts subsystem as its authority. Published feed rows are obtained through:

```php
posts_model::get_public_feed()
```

The RSS controller does not independently query the Posts table or create a second publication rule.

Public Post URLs use the established route:

```text
/posts/{slug}
```

Feed items include available Post metadata such as:

- title
- public URL
- permanent-link GUID
- description/content text
- publication date

The channel uses the installation's configured site name and description.

### Generation and Serving

RSS generation and HTTP presentation are separated.

`rss::generate()` rebuilds `/public/rss.xml` and returns the generated XML.

`rss::index()` calls the generator and serves the XML using:

```text
Content-Type: application/rss+xml; charset=UTF-8
```

This allows `/rss` to act as the live Core feed endpoint while permitting administrative maintenance to regenerate the static artifact without emitting XML into an Admin redirect response.

---

## Admin Refresh

Administrative maintenance route:

```text
/admin/refresh_indices
```

Compatibility alias:

```text
/admin/refresh_indexes
```

The refresh action regenerates:

```text
/public/sitemap.xml
/public/ror.xml
/public/llms.txt
/public/rss.xml
```

The operation is restricted to the established Admin authorization boundary.

After generation completes, the request redirects to:

```text
/admin
```

The Admin controller records success or failure status for the maintenance operation.

---

## Router Authorization

Chaos MVC uses an explicit Core HTTP-action boundary.

Because of that boundary, implementing a public controller method alone does not automatically make the method routable.

The Core Router explicitly authorizes the Admin maintenance actions:

```text
refresh_indices
refresh_indexes
```

Without this authorization, `/admin/refresh_indices` is rejected by the Router before the Admin controller can dispatch the action.

The compatibility alias exists only to preserve the alternate historical naming. `refresh_indices` is the canonical Admin route.

---

## Discovery Boundaries

The SEO/discovery subsystem will not:

- use the legacy modules database table as module authority
- execute user-module PHP merely to discover modules
- publish draft Pages
- invent values for dynamic module route parameters
- expose administrative or internal Core controllers as public resources
- independently redefine Post publication state for RSS
- allow discovery output to override Router ownership or routing rules

Generated discovery files describe resources that are already authorized by their owning subsystem. They do not create routes or grant authority.

---

## Files

Core files involved in the SEO/discovery subsystem include:

```text
/app/controllers/sitemap.php
/app/controllers/ror.php
/app/controllers/llms.php
/app/controllers/rss.php
/app/controllers/admin.php
/app/core/router.php
```

Generated public artifacts are:

```text
/public/sitemap.xml
/public/ror.xml
/public/llms.txt
/public/rss.xml
```

---

## Core Boundary

These files are Core infrastructure.

Changes to discovery ownership, route authorization, publication rules, or generated output formats require explicit Core modification authorization.

User modules and Pages remain responsible for their own metadata and publication state. SEO generation consumes those established contracts; it does not replace them.
