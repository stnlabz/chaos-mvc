# Chaos MVC
*A disciplined PHP framework built for clarity and control.*

![PHP](https://img.shields.io/badge/PHP-8%2B-blue)
![Architecture](https://img.shields.io/badge/Architecture-MVC-darkgreen)
![Status](https://img.shields.io/badge/Status-Active%20Development-orange)
![License](https://img.shields.io/badge/License-ChAoS-lightgrey)
![Sponsored](https://img.shields.io/badge/Sponsored-STN_Labz-blue)

Chaos MVC is a lightweight PHP MVC framework focused on **discipline, traceability, and architectural simplicity**.

**NOTICE** Current Source does **NOT** mean Current Version. Please see the [Releases](https://github.com/stnlabz/chaos-mvc/releases) for current version.

**Current Release**: 1.2.1

**Current Source**: 1.2.1

The framework was designed to remain predictable and maintainable while avoiding the complexity that often grows inside large CMS systems.
On the Webz at [Chaos MVC](https://www.chaos-mvc.org)

Chaos MVC emphasizes:

- clean architecture  
- strict development practices  
- transparent code flow  
- minimal framework overhead

## Become Chaos Certified
Become a Chaos Certified Developer via our **Developers Portal** and 3 Levels of **Certification**
[Chaos Certified](https://www.chaos-mvc.org/certification)

---

## Minimal Core Philosophy

Chaos MVC intentionally maintains a very small core footprint.

The framework provides only the essential components required to operate an MVC architecture.

Developers are encouraged to extend their applications through modules or project-specific code rather than expanding the core framework itself.

This approach keeps the framework fast, understandable, and maintainable over time.

---

# Why Chaos MVC Exists

Chaos MVC was created to solve a simple problem:

Modern PHP frameworks often grow into complex ecosystems that hide core application behavior behind layers of abstraction.

Chaos MVC takes a different approach.

It focuses on:

- **Clarity** – Every request path should be understandable.
- **Control** – Developers should know exactly what their system is doing.
- **Traceability** – Code changes must be attributable and documented.
- **Discipline** – Development rules exist to keep the framework stable long-term.

Chaos MVC avoids unnecessary framework magic and instead provides a predictable environment where developers can build applications without fighting the framework itself.

If you prefer:

- clear architecture
- simple routing
- minimal dependencies
- transparent execution flow

then ChAoS MVC may be the framework you are looking for.

---

# Design Principles

Chaos MVC follows a set of strict design principles intended to keep the framework stable, understandable, and maintainable over time.

### Simplicity

Chaos MVC avoids unnecessary abstraction.  
The framework should remain small, readable, and easy to reason about.

Complex systems often fail because they hide how things work.

Chaos MVC does the opposite.

---

### Predictable Architecture

Every request follows the same execution path:
```bash
Request
→ Router
→ Controller
→ Model
→ Database
→ View
→ Response
```
Developers should always know where data is coming from and where it is going.

---

### Code Traceability

All architectural changes must be traceable.

AI-generated code and developer modifications must include annotation markers so the origin of the code is always known.

Example:

```php
/* [AI:GPT | YYYY-MM-DD HH:MM:SS UTC] */
/* modified code */
/* [End AI:GPT | YYYY-MM-DD] */
/* [HUMAN: YOU | APPROVED | YYY-MM-DD] */
```
This ensures full accountability in collaborative environments.

### Discipline Over Convenience

Chaos MVC enforces development discipline to prevent long-term code decay.

This includes:

 - Mandatory annotations for AI-generated code
 - PSR-12 code formatting when ChAoS Development Standard do not apply
 - Proper DocBlock documentation
 - Controlled modification of core files
 - Strict code review expectations

### Lowercase Convention

Chaos MVC enforces lowercase naming conventions for:
```bash
files
classes
controllers
modules
```
This rule exists to ensure filesystem consistency and predictable behavior across environments.

### Transparent Development

Chaos MVC does not hide system behavior behind framework magic.

Instead, it exposes the architecture clearly so developers can understand exactly how the application works.

---

# Architecture

Chaos MVC follows the traditional MVC pattern.

### Model

Handles database interaction and data logic.

Models are responsible for querying the database and returning structured data to controllers.

### View

Responsible for presentation.

Views render the HTML output displayed to the user and should contain minimal logic.

### Controller

Acts as the traffic coordinator.

Controllers process requests, communicate with models, and pass data to views.

---

# Request Flow

A typical request inside Chaos MVC follows this path:
```bash
Request
↓
Controller
↓
Model
↓
Database
↓
Controller
↓
View
↓
HTML Output
```
This predictable execution flow keeps application behavior easy to understand and maintain.

---

# Core Features

Chaos MVC provides a protected Core together with installation-owned userland for Modules, Themes, Pages, and site data. Current development emphasizes keeping reusable application behavior outside Core unless it belongs to framework infrastructure.

## Authentication

User authentication system providing:

- Login
- Signup
- Logout
- Forgotten Password
- Password reset

## Updating

The ChAoS MVC Core includes an authenticated updater for published releases.

Current update architecture includes:

- whole-Core replacement within the established Core release boundary
- signed release verification
- SHA-256 integrity verification
- bounded HTTPS retrieval
- maintenance handling during update operations
- rollback/recovery support
- preservation of installation-owned configuration and userland
- release artifact validation before deployment

Core updates do not overwrite installation-owned user Modules, Themes, Pages, or user data.

---

## Administration Panel

The administration interface provides authenticated controls for framework and installation management.

Current administrative facilities include:

- site and installation management
- users and authentication
- Posts
- Media
- Pages
- Modules
- Themes
- Core updates
- health/runtime information
- SEO and discovery refresh operations

User Modules may also expose their own authenticated Admin interfaces without moving module-specific behavior into Core.

---

# SEO and Discovery

Chaos MVC includes Core generation for public search, feed, and machine-discovery resources.

Generated artifacts include:

- `sitemap.xml`
- `ror.xml`
- `llms.txt`
- `rss.xml`
- `site.json`

Public Core routes, valid filesystem-backed user Modules, and published filesystem-backed Pages are discovered from their established ownership boundaries rather than treating the legacy modules database table as authoritative.

RSS publishes content through the existing Posts publication contract.

`site.json` provides a machine-readable site resource declaration and is validated against the authoritative remote schema before a known-good generated artifact is replaced.

The Admin refresh operation regenerates the discovery artifacts through the established administrative boundary.

---

# Developer Documentation

Current repository documentation covers the framework architecture and the systems added or expanded during current development, including:

- Module creation and lifecycle
- Themes
- filesystem-backed Pages
- Markdown rendering
- SEO and discovery
- RSS
- `site.json`
- installation and updating
- development and contribution rules

The ChAoS MVC website also provides developer, Forge, and certification material for developers learning or building against the framework.

Certification is emphasized for developers who intend to publish signed work within the ChAoS MVC ecosystem. The Forge provides a practical starting point for learning the development and submission process.

---

# Current Extension Architecture

## User Modules

User Modules live under:

```text
/user/modules/{slug}/
```

Modules may own controllers, models, views, libraries, SQL lifecycle files, documentation, and module-specific data.

Reusable userland libraries do not automatically belong in Core. A Module may own reusable domain libraries that another Module consumes while the owning Module retains responsibility for that implementation.

## Module and Data Lifecycles

Current Module development distinguishes schema/module lifecycle from module-owned data lifecycle.

Where applicable, a Module may provide deterministic operations for:

- schema state detection
- initial schema installation
- exact-version schema migration
- module-owned data deletion
- canonical/reference-data restoration
- explicit Data Reset

Data Reset is distinct from uninstall: it can return module-managed data to its canonical initial state while leaving the Module and its schema installed.

## Themes

Installation-owned Themes live under:

```text
/user/themes/{theme}/
```

Themes own the surrounding presentation shell while Core and Module views continue to render page content. Core retains a built-in fallback layout.

## Pages

Filesystem-backed Pages live under:

```text
/user/pages/{slug}/
├── page.json
└── main.*
```

Pages support draft/published state and passive Markdown, HTML, text, or JSON content. Published Pages resolve through the established root-level routing fallback after Core controller and user-Module ownership checks.

## Markdown

The Core Markdown renderer supports the syntax documented by the project while escaping raw source HTML and validating generated links. Current support includes common GitHub-style Markdown facilities together with ChAoS MVC extensions documented in the Markdown reference.

---

# Development Philosophy

Chaos MVC follows several guiding principles.

### Simplicity

Avoid unnecessary complexity.  
Favor clear architecture over feature bloat.

### Traceability

All architectural modifications must be annotated and traceable.

### Discipline

The framework enforces strict development rules to maintain long-term stability.

### Predictability

Every component should behave in a consistent and understandable way.

---

# Development Rules

Key rules enforced by the framework:

- GitHub is required for core changes
- AI-generated code must be annotated
- HUMAN Approval is required for all AI edited/created work
- PSR-12 code formatting is required
- Proper DocBlock documentation must be present
- Core files are protected
- Lowercase file and class naming is enforced
- Three-strike removal policy for violations

Full documentation is available at:
- `/developer/rules`

# Installation

ChAoS MVC includes its own installation flow and persistent installation identity.

A deployment requires:

1. the complete release artifact
2. a supported PHP environment
3. a configured web server with the document root directed to `/public`
4. database access for installations using database-backed facilities
5. writable installation-owned data/log locations required by the framework

Use the release artifact and installation documentation for the version being deployed rather than treating the development branch as a release package.

---

# Project Structure

```text
├── app/
│   ├── bootstrap.php
│   ├── controllers/
│   ├── core/
│   ├── data/
│   ├── lib/
│   ├── models/
│   └── views/
├── public/
├── user/
│   ├── data/
│   ├── modules/
│   ├── pages/
│   └── themes/
└── README.md
```

`app/` contains protected framework infrastructure.

`user/` contains installation-owned extension and content space. User Modules, Themes, and Pages remain outside the protected Core boundary.

---

# Project Status

Chaos MVC is an actively developed framework and currently powers live systems.

The current source is the released 1.2.1 line. Changes since 1.2.0 include the filesystem-backed Pages subsystem, expanded Markdown support, modernized SEO/discovery generation, RSS, schema-validated `site.json`, Theme infrastructure, updater/release hardening, and continued refinement of user-Module lifecycle and development practices.

The current development objective remains a small, deterministic, understandable framework with a protected Core and clearly owned userland.

---

# Philosophy

Chaos MVC was created to demonstrate that a framework can remain:
- simple
- predictable
- disciplined

while still providing the tools necessary to build modern applications.

---

---

# Contributing

Chaos MVC welcomes contributions from developers who respect the architectural discipline of the project.

Because this framework prioritizes stability and traceability, contributions must follow the established development rules.

### Requirements

All contributions must follow the Chaos MVC development standards:

- PSR-12 compliant code formatting where ChAoS Coding standards end.
- Proper DocBlock documentation
- Lowercase file and class naming
- No modification of core files without approval
- AI generated code must include annotation markers
- All core changes must be reviewed before merge

***Example annotation format***:

```php
/* [AI:MODEL_NAME | YYYY-MM-DD HH:MM:SS UTC] */
/* modified code */
/* [End AI:MODEL_NAME] */
/* [HUMAN: YOU | APPROVE | YYYY-DD-MM HH:MM:SS UTC] */
```
### Core File Protection

Files located in:
 - `/app/core`
 - `/app/controllers`
 - `/app/models`
 - `/app/views/admin`
 - `/app/views/auth`

are considered protected infrastructure.

Changes to these files require explicit approval through repository commits and code review.

### Development Workflow

***Typical contribution workflow***:

1. fork repository
2. create feature branch
3. commit annotated code
4. submit pull request
5. maintainer review
6. merge

### Code of Conduct

Chaos MVC expects contributors to maintain professional communication and respect project structure and development standards.

# documentation

 - [ISSUES](docs/CURRENT_ISSUES.md)
 - [CHANGELOG](docs/CHANGELOG.md)
 - [CONTRIBUTING](docs/CONTRIBUTING.md)

# License

The ChAoS MVC License is a [Proprietary License](docs/LICENSE.md)
