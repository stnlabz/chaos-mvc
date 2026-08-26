# ChAoS MVC Markdown Reference

This document describes the Markdown syntax implemented by the ChAoS MVC `render_md` library.

The renderer provides a deliberately small Markdown dialect for documentation, changelogs, and internal pages. It does not attempt to implement the complete GitHub Flavored Markdown specification.

> Keep it small, deterministic, and easy to use.

---

## Quick Reference

| Purpose | Syntax | Output |
| --- | --- | --- |
| Heading | `# Heading` | Heading |
| Bold | `**bold**` | Strong text |
| Italic | `*italic*` | Emphasized text |
| Italic | `_italic_` | Emphasized text |
| Small text | `~~small~~` | Small text |
| Inline code | `` `code` `` | Inline code |
| Link | `[Downloads](/downloads)` | Link |
| Blockquote | `> Quote` | Blockquote |
| Unordered list | `- Item` | Unordered list |
| Ordered list | `1. Item` | Ordered list |
| Horizontal rule | `---` | Horizontal rule |
| Fenced code | ` ```php ` | Code block |

---

## Headings

Headings from level one through level six are supported.

```text
# Heading 1
## Heading 2
### Heading 3
#### Heading 4
##### Heading 5
###### Heading 6
```

These are rendered as the corresponding HTML heading elements:

```html
<h1>Heading 1</h1>
<h2>Heading 2</h2>
<h3>Heading 3</h3>
<h4>Heading 4</h4>
<h5>Heading 5</h5>
<h6>Heading 6</h6>
```

---

## Bold Text

Use two asterisks around text to render it as bold.

```text
**This text is bold.**
```

Rendered as:

```html
<strong>This text is bold.</strong>
```

Example:

```text
Chaos MVC keeps the **Core protected**.
```

---

## Italic Text

Italic text may use either asterisks or underscores.

```text
*This text is italic.*
```

or:

```text
_This text is italic._
```

Rendered as:

```html
<em>This text is italic.</em>
```

---

## Small Text

ChAoS MVC uses double tildes to render small text.

```text
~~This text is small.~~
```

Rendered as:

```html
<small>This text is small.</small>
```

### Important

This differs from standard GitHub Flavored Markdown.

In standard GFM:

```text
~~text~~
```

normally represents strikethrough text.

In the ChAoS MVC Markdown renderer, it represents:

```html
<small>text</small>
```

This behavior is part of the current ChAoS MVC Markdown dialect.

---

## Links

Links use standard Markdown link syntax.

```text
[Link Text](URL)
```

Example:

```text
[Downloads](/downloads)
```

External link:

```text
[GitHub](https://github.com/)
```

Email link:

```text
[Contact](mailto:example@example.com)
```

### Supported URL Types

The renderer permits links using:

```text
https:
http:
mailto:
```

It also permits root-relative internal URLs:

```text
/downloads
/releases
/docs
```

For example:

```text
[Downloads](/downloads)
```

or:

```text
[Releases](/releases)
```

### Unsafe URLs

Unsafe URL schemes are rejected by the renderer.

For example:

```text
[Unsafe](javascript:alert(1))
```

must not become an executable JavaScript link.

This restriction protects Markdown-rendered content from unsafe URL schemes.

---

## Inline Code

Inline code uses a single backtick.

```text
`CHAOS_VERSION`
```

Rendered as:

```html
<code>CHAOS_VERSION</code>
```

Example:

```text
The installed version is defined by `CHAOS_VERSION`.
```

---

## Fenced Code Blocks

Code blocks use triple backticks.

````text
```
Example code
```
````

A language identifier may be placed after the opening fence.

````text
```php
echo 'Hello, Chaos MVC';
```
````

The renderer uses the language identifier when generating the code block class.

For example:

````text
```php
echo 'Hello';
```
````

is rendered using a class corresponding to:

```text
code-php
```

This allows site CSS to provide language-specific presentation where desired.

### Example: PHP

````text
```php
<?php

echo 'Chaos MVC';
```
````

### Example: JSON

````text
```json
{
    "name": "Chaos MVC",
    "version": "1.1.10"
}
```
````

### Example: C

````text
```c
#include <stdio.h>

int main(void)
{
    printf("Chaos MVC\n");
    return 0;
}
```
````

---

## Blockquotes

A blockquote begins with `>`.

```text
> Keep it small, deterministic, and easy to use.
```

Multiple quoted lines may be written as:

```text
> Protect the Core.
> Keep behavior deterministic.
> Grow outward.
```

---

## Unordered Lists

Unordered lists may use `-`, `*`, or `+`.

```text
- Controllers
- Models
- Views
```

The following is also valid:

```text
* Controllers
* Models
* Views
```

And:

```text
+ Controllers
+ Models
+ Views
```

---

## Ordered Lists

Ordered lists use a number followed by a period.

```text
1. Controller
2. Model
3. View
```

---

## Horizontal Rules

Horizontal rules may be created using:

```text
---
```

or:

```text
***
```

or:

```text
___
```

These are rendered as:

```html
<hr>
```

---

## Line Breaks

Ordinary Markdown lines are converted into HTML line breaks where applicable.

For example:

```text
First line
Second line
Third line
```

is rendered with line breaks between the lines.

---

## Combining Formatting

Supported inline formatting may be combined when appropriate.

```text
Read the **current release** on the [Releases](/releases) page.
```

Another example:

```text
The `CHAOS_VERSION` constant contains the **installed Core version**.
```

---

## Internal ChAoS MVC Links

For pages within the same ChAoS MVC installation, root-relative links are preferred.

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

This keeps internal documentation independent of the installation's domain name.

---

## External Links

External HTTPS links use normal Markdown syntax.

```text
[Project Repository](https://github.com/example/project)
```

HTTP links are also accepted by the current renderer:

```text
[Example](http://example.com)
```

HTTPS should be preferred when the destination supports it.

---

## GitHub Release Links

A GitHub repository's Releases page can be linked using:

```text
[Releases](https://github.com/OWNER/REPOSITORY/releases)
```

A specific release can be linked using:

```text
[v1.1.10](https://github.com/OWNER/REPOSITORY/releases/tag/v1.1.10)
```

Example documentation:

```text
Stable releases are available from the [Releases](https://github.com/OWNER/REPOSITORY/releases) page.
```

---

## Unsupported or Non-Standard Syntax

The ChAoS MVC Markdown renderer is intentionally small.

Do not assume that every GitHub Flavored Markdown feature is implemented.

The current renderer does not provide complete support for:

- GFM tables
- Task lists
- Markdown images
- Nested lists
- Automatic URL linking
- Raw HTML
- Standard GFM strikethrough behavior

Unsupported syntax may be rendered as ordinary text rather than interpreted as Markdown.

---

## Tables

Markdown table parsing is not currently implemented by the ChAoS MVC Markdown renderer.

For example:

```text
| Name | Version |
| --- | --- |
| Chaos MVC | 1.1.10 |
```

should not be assumed to render as an HTML table.

This reference itself may use GitHub tables because this document is intended for GitHub, but that does not mean the ChAoS MVC renderer supports them.

---

## Task Lists

GitHub-style task lists are not currently implemented.

Example GFM syntax:

```text
- [x] Complete
- [ ] Pending
```

Do not rely on this syntax when writing content for the ChAoS MVC Markdown renderer.

---

## Images

Markdown image syntax is not currently implemented.

Standard Markdown commonly uses:

```text
![Alt Text](/path/to/image.png)
```

Do not rely on this syntax when writing content for the ChAoS MVC Markdown renderer.

---

## Raw HTML

The Markdown renderer does not provide raw HTML as a general Markdown feature.

For example:

```html
<strong>Raw HTML</strong>
```

should not be used as a substitute for supported Markdown syntax.

Use:

```text
**Bold text**
```

instead.

---

## Security Behavior

Markdown content is treated as content rather than trusted executable markup.

The renderer escapes content before applying supported Markdown transformations.

This provides a controlled Markdown surface rather than allowing arbitrary HTML execution.

### Link Validation

Markdown links are validated before becoming HTML links.

Accepted link forms include:

```text
https://example.com
http://example.com
mailto:user@example.com
/internal/path
```

Unsafe or unsupported schemes are rejected.

Examples include:

```text
javascript:
data:
```

These must not be treated as executable Markdown links.

### Code

Inline and fenced code content is rendered as code rather than executable markup.

For example:

````text
```html
<script>alert('Nope');</script>
```
````

The contents of the code block are displayed as code.

---

## PHP API

The Markdown renderer is provided by the `render_md` class.

### Create the Renderer

```php
$renderer = new render_md();
```

### Render Markdown

Markdown text can be rendered using:

```php
$renderer = new render_md();

echo $renderer->markdown($markdown);
```

Example:

```php
$renderer = new render_md();

$markdown = '**Chaos MVC**';

echo $renderer->markdown($markdown);
```

### Render a Markdown File

A Markdown file can be rendered using:

```php
$renderer = new render_md();

$renderer->markdown_file($path);
```

The supplied path identifies the Markdown document to be rendered.

---

## Example Document

The following document uses the primary syntax supported by the ChAoS MVC Markdown renderer:

````text
# Chaos MVC

> Keep it small, deterministic, and easy to use.

Chaos MVC is a **small MVC framework** built around a protected Core.

## Current Release

The installed version is represented by `CHAOS_VERSION`.

See the [Releases](/releases) page for release information.

## Principles

- Protect the Core
- Keep behavior deterministic
- Keep operation understandable

## Example

```php
$renderer = new render_md();

echo $renderer->markdown(
    '**Hello from Chaos MVC**'
);
```

---
````

---

## Design Philosophy

The ChAoS MVC Markdown renderer intentionally does not attempt to become a complete Markdown ecosystem.

Its purpose is to provide the formatting needed by ChAoS MVC while remaining:

- Small
- Deterministic
- Understandable
- Easy to use
- Easy to maintain

Additional Markdown features should be added because ChAoS MVC has a demonstrated requirement for them, not simply because another Markdown implementation provides them.

---

## Summary

For everyday use, remember:

```text
# Heading

**Bold**

*Italic*

~~Small~~

`Code`

[Link](/path)

> Quote

- List item

1. Ordered item

---
```

And the link syntax:

```text
[What people see](where-you-want-them-to-go)
```

Example:

```text
[Releases](https://github.com/OWNER/REPOSITORY/releases)
```

When the syntax inevitably escapes memory again, this reference is the authoritative quick reminder.