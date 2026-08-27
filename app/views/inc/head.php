<?php
if (class_exists('theme') && theme::render('head', get_defined_vars())) {
    return;
}

$og = $og ?? [];

$og_title = $og['title'] ?? 'Chaos MVC';
$og_desc  = $og['desc'] ?? 'Lightweight * Model * View * Controller';
$og_url   = $og['url'] ?? URLROOT;
$og_image = $og['image'] ?? URLROOT . '/assets/icons/icon.png';
$og_type = $og['type'] ?? 'article';
//print_r($data['og']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>
        Chaos MVC
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="keywords" content="">

    <meta name="author" content="Chaos MVC">
    <meta name="copyright" content="Chaos MVC">
    <meta name="application-name" content="Chaos MVC">

    <!-- For Facebook -->
    <meta property="og:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($og_image) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($og_url) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($og_desc) ?>">
    <meta property="og:type" content="<?= htmlspecialchars((string) $og_type, ENT_QUOTES, 'UTF-8') ?>">

    <!-- For Twitter -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($og_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($og_desc) ?>">
    <meta name="twitter:url" content="<?= htmlspecialchars($og_url) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($og_image) ?>">
    <meta name="twitter:type" content="<?= htmlspecialchars((string) $og_type, ENT_QUOTES, 'UTF-8') ?>">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">

    <!-- Shadow Witch CSS -->
    <link rel="stylesheet" href="<?= htmlspecialchars(URLROOT, ENT_QUOTES, 'UTF-8') ?>/assets/css/site.css">
    
    <!-- Site Icon -->
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars(URLROOT, ENT_QUOTES, 'UTF-8') ?>/assets/icons/icon.png">
</head>
<body>
<div class="container">
<?php
include __DIR__ . '/nav.php';
?>
</div>
