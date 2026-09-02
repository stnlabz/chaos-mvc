<?php

$SITE = $GLOBALS['SITE'] ?? [];
$siteName = (string) ($SITE['name'] ?? 'Chaos MVC');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> — Maintenance</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f5f5f5; color: #222; }
        main { width: min(520px, calc(100% - 40px)); padding: 40px; background: #fff; border: 1px solid #ddd; border-radius: 8px; text-align: center; }
        h1 { margin-top: 0; }
        p { margin-bottom: 0; color: #666; }
    </style>
</head>
<body>
<main>
    <h1>Maintenance in progress</h1>
    <p><?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?> is temporarily unavailable. Please check back shortly.</p>
</main>
</body>
</html>
