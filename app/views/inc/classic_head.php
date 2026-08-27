<?php
$SITE = $GLOBALS['SITE'] ?? ($SITE ?? []);
$og = is_array($og ?? null) ? $og : [];
$siteName = (string) ($SITE['name'] ?? 'Chaos MVC');
$pageTitle = (string) ($og['title'] ?? $siteName);
$description = (string) ($og['desc'] ?? $SITE['description'] ?? '');
$canonicalUrl = (string) ($og['url'] ?? URLROOT);
$ogType = (string) ($og['type'] ?? 'website');
$ogImage = trim((string) ($og['image'] ?? ''));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="keywords" content="<?= htmlspecialchars((string) ($SITE['keywords'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="author" content="<?= htmlspecialchars((string) ($SITE['author'] ?? $siteName), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="copyright" content="<?= htmlspecialchars((string) ($SITE['copyright_name'] ?? $siteName), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="application-name" content="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>">

    <meta property="og:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($ogImage !== ''): ?>
        <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($ogImage !== ''): ?>
        <meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        :root { color-scheme: light; }
        body { background: #f7f5f0; color: #292722; font-family: Georgia, "Times New Roman", serif; }
        a { color: #315c7d; }
        .classic-shell { width: min(1120px, calc(100% - 2rem)); margin-inline: auto; }
        .classic-header { background: #fff; border-bottom: 1px solid #d8d3c8; }
        .classic-nav { display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; padding: 1rem 0; }
        .classic-brand { color: #292722; font-size: 1.35rem; font-weight: 700; text-decoration: none; }
        .classic-links { display: flex; align-items: center; flex-wrap: wrap; gap: 1rem; margin: 0; padding: 0; list-style: none; }
        .classic-links a, .classic-link-button { color: #454138; background: none; border: 0; padding: 0; font: inherit; text-decoration: none; cursor: pointer; }
        .classic-links a:hover, .classic-link-button:hover { color: #000; text-decoration: underline; }
        .classic-main { min-height: 70vh; padding-block: 2rem; }
        .classic-footer { margin-top: 3rem; padding: 1.5rem 0; background: #fff; border-top: 1px solid #d8d3c8; color: #625e55; }
        .classic-footer-inner { display: flex; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .classic-footer p { margin: 0; }
        @media (max-width: 720px) { .classic-nav { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
<header class="classic-header">
    <div class="classic-shell">
        <?php require __DIR__ . '/classic_nav.php'; ?>
    </div>
</header>
<main class="classic-shell classic-main">
