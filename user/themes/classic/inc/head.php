<?php
/* [AI:GPT-5.6 Sol | 2026-08-30 18:10:00 UTC] */

$og = $og ?? [];

$siteName = $SITE['name'] ?? 'Chaos MVC';
$siteDescription = $SITE['description'] ?? 'Powered by Chaos MVC';

$ogTitle = $og['title'] ?? $siteName;
$ogDescription = $og['desc'] ?? $siteDescription;
$ogUrl = $og['url'] ?? URLROOT;
$ogImage = $og['image'] ?? theme::assetUrl('icons/icon.png');
$ogType = $og['type'] ?? 'website';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        <?= htmlspecialchars($ogTitle, ENT_QUOTES, 'UTF-8'); ?>
    </title>

    <meta
        name="description"
        content="<?= htmlspecialchars(
            $ogDescription,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        name="author"
        content="<?= htmlspecialchars(
            $siteName,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        property="og:title"
        content="<?= htmlspecialchars(
            $ogTitle,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        property="og:description"
        content="<?= htmlspecialchars(
            $ogDescription,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        property="og:url"
        content="<?= htmlspecialchars(
            $ogUrl,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        property="og:image"
        content="<?= htmlspecialchars(
            $ogImage,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        property="og:type"
        content="<?= htmlspecialchars(
            $ogType,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        name="twitter:card"
        content="summary"
    >

    <meta
        name="twitter:title"
        content="<?= htmlspecialchars(
            $ogTitle,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        name="twitter:description"
        content="<?= htmlspecialchars(
            $ogDescription,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <meta
        name="twitter:image"
        content="<?= htmlspecialchars(
            $ogImage,
            ENT_QUOTES,
            'UTF-8'
        ); ?>"
    >

    <link
        rel="stylesheet"
        href="<?= theme::assetUrl('css/site.css'); ?>"
    >

    <link
        rel="icon"
        type="image/png"
        href="<?= theme::assetUrl('icons/icon.png'); ?>"
    >
</head>

<body>
<div class="site">

    <?php include __DIR__ . '/nav.php'; ?>

    <main class="site-main">
        <div class="site-content">

<?php /* [End AI:GPT-5.6 Sol] */ ?>