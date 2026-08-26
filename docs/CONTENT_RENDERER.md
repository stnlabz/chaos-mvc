# ChAoS MVC Content Renderer Reference

The ChAoS MVC Content Renderer provides a small, controlled formatting language for content stored in database tables.

Its primary purpose is simple:

> Keep HTML out of data tables.

Content stored in the database remains plain text. When that content is displayed, `content_renderer.php` converts a deliberately limited set of formatting syntax into safe HTML.

This separates stored content from presentation markup while still allowing useful formatting such as headings, links, lists, and emphasis.

---

## Purpose

The Content Renderer exists to prevent application content from requiring HTML to be stored directly in database fields.

Instead of storing:

```html
<h2>Downloads</h2>

<p>
    Visit the <a href="/downloads">Downloads</a> page.
</p>
```

the database can contain:

```text
## Downloads

Visit the [Downloads](/downloads) page.
```

The Content Renderer converts the stored text into the appropriate HTML when the content is displayed.

The database therefore contains content rather than presentation markup.

---

## Design Principle

The Content Renderer follows the ChAoS MVC principle:

> No HTML in data tables.

Formatting is represented using a small Markdown-like syntax.

The renderer:

- Escapes stored content before rendering it
- Provides only explicitly supported formatting
- Validates links
- Prevents arbitrary HTML stored in database content from becoming executable markup
- Keeps database content readable without an HTML renderer
- Keeps formatting behavior small and deterministic

The Content Renderer is intentionally not a complete Markdown implementation.

---

## Quick Reference

| Purpose | Syntax | Rendered As |
| --- | --- | --- |
| Heading 1 | `# Heading` | `<h1>` |
| Heading 2 | `## Heading` | `<h2>` |
| Heading 3 | `### Heading` | `<h3>` |
| Emphasis | `***text***` | `<strong>` |
| Link | `[Text](/path)` | `<a>` |
| Unordered list | `- Item` | `<ul><li>` |
| Ordered list | `1. Item` | `<ol><li>` |
| Plain text | `Text` | `<p>` |

---

## Headings

The Content Renderer supports three heading levels.

### Heading 1

Stored content:

```text
# Heading
```

Rendered as:

```html
<h1>Heading</h1>
```

### Heading 2

Stored content:

```text
## Heading
```

Rendered as:

```html
<h2>Heading</h2>
```

### Heading 3

Stored content:

```text
### Heading
```

Rendered as:

```html
<h3>Heading</h3>
```

Heading levels four through six are not implemented by the current Content Renderer.

---

## Emphasis

The Content Renderer uses three asterisks to create emphasized text.

Stored content:

```text
***Important***
```

Rendered as:

```html
<strong>Important</strong>
```

Example:

```text
***Security maintenance is currently in progress.***
```

The current renderer does not interpret this syntax using standard Markdown semantics.

In the Content Renderer:

```text
***text***
```

means strong emphasis.

The renderer does not currently implement separate Markdown bold and italic syntax.

---

## Links

Links use Markdown-style syntax:

```text
[Link Text](URL)
```

Example:

```text
[Downloads](/downloads)
```

Rendered as a link to:

```text
/downloads
```

This allows database content to contain useful navigation without storing an HTML anchor element.

Instead of storing:

```html
<a href="/downloads">Downloads</a>
```

store:

```text
[Downloads](/downloads)
```

---

## Internal Links

Root-relative internal links are supported.

Examples:

```text
[Home](/)
```

```text
[Downloads](/downloads)
```

```text
[Releases](/releases)
```

```text
[Certification](/certification)
```

Root-relative URLs are useful because database content does not need to know the site's domain name.

---

## External Links

The Content Renderer permits HTTP and HTTPS URLs.

Example:

```text
[Project](https://example.com/project)
```

HTTP URLs are also accepted:

```text
[Project](http://example.com/project)
```

HTTPS should normally be preferred when available.

---

## Email Links

The `mailto:` scheme is supported.

Example:

```text
[Contact Us](mailto:example@example.com)
```

---

## Allowed Link Types

The current Content Renderer permits:

```text
https:
http:
mailto:
```

and root-relative paths beginning with:

```text
/
```

Examples of valid destinations include:

```text
https://example.com
http://example.com
mailto:user@example.com
/downloads
/releases
/
```

---

## Unsafe Links

Unsupported URL schemes are not rendered as active links.

For example:

```text
[Example](javascript:alert(1))
```

must not become an executable JavaScript link.

Protocol-relative URLs beginning with:

```text
//
```

are also rejected by the current renderer.

This prevents stored content from bypassing the renderer's intended URL restrictions.

---

## Unordered Lists

Unordered list items begin with a hyphen.

Stored content:

```text
- Controllers
- Models
- Views
```

Rendered conceptually as:

```html
<ul>
    <li>Controllers</li>
    <li>Models</li>
    <li>Views</li>
</ul>
```

The renderer manages opening and closing the list around consecutive list items.

---

## Ordered Lists

Ordered list items begin with a number followed by a period.

Stored content:

```text
1. Controller
2. Model
3. View
```

Rendered conceptually as:

```html
<ol>
    <li>Controller</li>
    <li>Model</li>
    <li>View</li>
</ol>
```

The renderer manages opening and closing the ordered list around consecutive list items.

---

## Paragraphs

Ordinary text that does not match another supported formatting rule is rendered as paragraph content.

Stored content:

```text
Chaos MVC keeps content separate from presentation markup.
```

Rendered as:

```html
<p>Chaos MVC keeps content separate from presentation markup.</p>
```

This allows ordinary database content to remain ordinary readable text.

---

## Example Database Content

A database field can contain:

```text
# Core Maintenance

***Core is undergoing planned updates.***

Following the release of v1.1.8, we have begun a series of pre-planned Core updates spanning versions 1.1.9 through 1.1.10.

These updates include:

- Security improvements
- Updating improvements
- Core maintenance

For release information, visit [Releases](/releases).

For downloadable packages, visit [Downloads](/downloads).
```

The database contains no HTML.

The Content Renderer provides the HTML presentation when the content is displayed.

---

## Why HTML Is Not Stored

Storing presentation HTML directly in database content creates an unnecessary relationship between the data and the presentation layer.

For example:

```html
<p>
    Download the latest version from
    <a href="/downloads">Downloads</a>.
</p>
```

contains both content and HTML presentation instructions.

Using the Content Renderer, the stored value becomes:

```text
Download the latest version from [Downloads](/downloads).
```

The content remains readable as data.

The renderer determines how that content becomes HTML.

---

## Escaping

Stored content is escaped before supported formatting is introduced.

This means database content such as:

```html
<script>alert('example');</script>
```

is treated as content rather than trusted HTML.

Arbitrary HTML stored in a data field is therefore not intended to become active page markup.

Only formatting explicitly recognized by the Content Renderer is converted into HTML.

---

## Controlled Rendering

The renderer provides a controlled boundary:

```text
Database
    |
    | Plain text content
    v
Content Renderer
    |
    | Controlled formatting
    v
HTML Output
```

The database does not need to contain:

```text
<h1>
<p>
<a>
<ul>
<ol>
<li>
<strong>
```

Those elements are generated by the renderer when appropriate.

---

## Content and Presentation Separation

The intended relationship is:

```text
DATA
    Content and formatting intent

RENDERER
    Interpretation and HTML generation

VIEW
    Presentation of rendered content
```

The database owns the content.

The renderer owns the conversion.

The view owns presentation.

---

## Content Renderer vs. Markdown Renderer

ChAoS MVC also contains a broader Markdown renderer.

The two components serve different purposes.

### Content Renderer

`content_renderer.php`

Designed primarily for content originating from data tables.

Its goal is:

> Keep HTML out of data tables.

It provides a deliberately small formatting language.

### Markdown Renderer

`render_md.php`

Designed for broader Markdown rendering, including documentation-oriented content.

It supports more Markdown syntax than the Content Renderer.

The two renderers should not be assumed to support identical syntax.

---

## Supported Content Renderer Syntax

The current Content Renderer supports:

```text
# Heading 1

## Heading 2

### Heading 3

***Strong emphasis***

[Link Text](/path)

- Unordered item

1. Ordered item

Plain paragraph text
```

---

## Unsupported Syntax

The Content Renderer intentionally implements only a small formatting surface.

The current implementation does not provide general support for:

- Heading levels 4 through 6
- Standard `**bold**` Markdown
- Standard `*italic*` Markdown
- Inline code
- Fenced code blocks
- Blockquotes
- Horizontal rules
- Images
- Tables
- Task lists
- Nested lists
- Automatic URL linking
- Raw HTML
- Complete GitHub Flavored Markdown

Do not assume syntax supported by `render_md.php` is also supported by `content_renderer.php`.

---

## Raw HTML

Raw HTML should not be stored in database content intended for the Content Renderer.

Do not store:

```html
<strong>Important</strong>
```

Use:

```text
***Important***
```

Do not store:

```html
<a href="/downloads">Downloads</a>
```

Use:

```text
[Downloads](/downloads)
```

Do not store:

```html
<h2>Releases</h2>
```

Use:

```text
## Releases
```

This is the central reason the Content Renderer exists.

---

## Security Boundary

The Content Renderer deliberately limits what stored content can cause the application to emit as markup.

Its security-related behavior includes:

- Escaping stored content
- Not treating arbitrary HTML as trusted markup
- Restricting supported formatting
- Validating link destinations
- Rejecting unsupported URL schemes
- Rejecting protocol-relative links
- Generating known HTML structures from controlled syntax

The renderer should remain small enough that its transformation behavior can be understood and reviewed.

---

## Adding Features

New formatting features should not be added merely to make the Content Renderer equivalent to a full Markdown implementation.

A new feature should have a demonstrated content requirement.

The design question should remain:

> Does database content need this formatting capability without requiring HTML in the data table?

If the answer is no, the feature does not belong in the Content Renderer.

If substantially richer Markdown is required, the ChAoS MVC Markdown renderer may be the more appropriate component.

---

## Design Philosophy

The Content Renderer exists to preserve a simple boundary:

> Data contains content. Code produces markup.

It is intentionally:

- Small
- Deterministic
- Limited
- Understandable
- Easy to audit
- Easy to use

Its purpose is not to reproduce every feature of Markdown.

Its purpose is to provide enough formatting for database-backed content without putting HTML into the database.

---

## Quick Reminder

For normal database content:

```text
# Main Heading

## Section

### Subsection

***Important text***

Read the [Downloads](/downloads) page.

- First item
- Second item
- Third item

1. First step
2. Second step
3. Third step

Ordinary paragraph text goes here.
```

No HTML needs to be stored.

That is the point.