# Chaos MVC
*A disciplined PHP framework built for clarity and control.*

![PHP](https://img.shields.io/badge/PHP-8%2B-blue)
![Architecture](https://img.shields.io/badge/Architecture-MVC-darkgreen)
![Status](https://img.shields.io/badge/Status-Active%20Development-orange)
![License](https://img.shields.io/badge/License-TBD-lightgrey)
![Sponsored](https://img.shields.io/badge/Sponsored_By-STN_Labz-blue)

Chaos MVC is a lightweight PHP MVC framework focused on **discipline, traceability, and architectural simplicity**.

The framework was designed to remain predictable and maintainable while avoiding the complexity that often grows inside large CMS systems.
On the Webz at [Chaos MVC](https://www.chaos-mvc.org)

Chaos MVC emphasizes:

- clean architecture  
- strict development practices  
- transparent code flow  
- minimal framework overhead  

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

then the Chaos MVC may be the framework you are looking for.

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
/* [End AI:GPT] */
```
This ensures full accountability in collaborative environments.

### Discipline Over Convenience

Chaos MVC enforces development discipline to prevent long-term code decay.

This includes:

 - Mandatory annotations for AI-generated code
 - PSR-12 code formatting
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

Chaos MVC includes several modules out of the box to support common application needs.

## Authentication

User authentication system providing:

- Login
- Signup
- Logout
- Forgotten Password
- Password reset

---

## Administration Panel

The administration interface provides tools for managing the system and monitoring runtime behavior.

Administrative modules include:

### Health
Displays system diagnostics and environment checks.

### Users
Manage user accounts and authentication roles.

### Modules
Install, enable, disable, or update framework modules.

### Media
Handles file uploads and media storage.

### Posts
Content and article management.

---

# SEO Tools

Chaos MVC includes built-in tools for generating SEO and AI discovery resources.

These tools scan controllers and modules to automatically build updated files.

Generated files include:
- `ror.xml`
- `sitemap.xml`
- `llms.txt`
  
These resources help search engines and AI systems understand the structure of the site.

---

# Developer Documentation

Chaos MVC ships with internal developer documentation accessible through the framework.

Developer portal pages include:
- `/developer`
- `/developer/flow`
- `/developer/example`
- `/developer/database`
- `/developer/markdown`
- `/developer/rules`
  
  These pages explain the architecture, database wrapper, Markdown system, and development standards.

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
- PSR-12 code formatting is required
- Proper DocBlock documentation must be present
- Core files are protected
- Lowercase file and class naming is enforced
- Three-strike removal policy for violations

Full documentation is available at:
- `/developer/rules`

# Installation

Typical installation steps:


1. Clone the repository
2. Configure database credentials in `/app/core/config.php`
3. Import the database schema
4. Configure web root to `/public`
5. Ensure mod_rewrite is enabled

After installation, the framework is ready to run.

---

# Project Structure

```bash
├── app
│   ├── bootstrap.php
│   ├── controllers
│   ├── core
│   ├── lib
│   ├── models
│   └── views
│       ├── admin
│       ├── auth
│       ├── errors
│       ├── inc
│       └── public
├── public
│   ├── assets
│   │   ├── css
│   │   ├── icons
|   │   ├── img
└── README.md
```
---

# Project Status

Chaos MVC is an actively developed framework and currently powers several live systems.

The project continues to evolve with a focus on stability, maintainability, and developer clarity.

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

- PSR-12 compliant code formatting
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

# License

To be determined.
