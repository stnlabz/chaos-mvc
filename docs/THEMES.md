# Themes

Chaos MVC themes are installation-owned PHP layouts stored outside Core.

## Directory Structure

```text
user/themes/{theme}/
├── theme.json
├── inc/
│   ├── head.php
│   ├── nav.php
│   └── foot.php
└── assets/
```

The theme slug must begin with a lowercase letter and may contain lowercase
letters, numbers, underscores, and hyphens.

## Manifest

```json
{
    "theme": "example",
    "name": "Example Theme",
    "version": "1.0.0",
    "author": "Example Author",
    "description": "Example site presentation"
}
```

The `theme` value must exactly match the theme directory name. All three PHP
layout files are required before a theme appears in administration. For
compatibility with the initial theme implementation, Core also recognizes
layout files placed directly in the theme root, but new themes should use
`inc/`.

## Layout Context

Theme layout files receive the established site context:

```text
$SITE
$data
$og
$render_md
URLROOT
$_SESSION
```

`head.php` may load its sibling navigation directly:

```php
<?php require __DIR__ . '/nav.php'; ?>
```

Core and module views continue to render page content. The active theme owns
the surrounding header, navigation, and footer.

## Assets

Theme assets belong under the theme's `assets/` directory. Generate a public
URL from a theme layout with:

```php
<?= htmlspecialchars(theme::assetUrl('css/site.css'), ENT_QUOTES, 'UTF-8'); ?>
```

Supported assets are CSS, JavaScript, common web images, icons, and web fonts.
Asset resolution is confined to the active theme directory.

## Applying a Theme

Navigate to:

```text
/admin/themes
```

Select an installed theme and choose **Apply Theme**. The request requires an
administrator session, POST, and CSRF verification. Chaos MVC records the
selected slug as `active_theme` in the installation-owned `app/data/site.json`.

The built-in Core fallback is a neutral Classic layout and has no dependency on `/public/assets/`.

Selecting **Chaos MVC** restores the built-in Core layout. An absent, invalid,
or incomplete selected theme also falls back to the Core layout.

Core updates do not overwrite `user/themes/`.
