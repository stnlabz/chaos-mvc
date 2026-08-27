<?php
$SITE = $GLOBALS['SITE'] ?? ($SITE ?? []);
$siteName = (string) ($SITE['name'] ?? 'Chaos MVC');
?>
<nav class="classic-nav" aria-label="Primary navigation">
    <a class="classic-brand" href="/">
        <?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8'); ?>
    </a>
    <ul class="classic-links">
        <li><a href="/">Home</a></li>
        <li><a href="/posts">Posts</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ((int) ($_SESSION['user_level'] ?? 0) >= 7): ?>
                <li><a href="/admin">Admin</a></li>
            <?php endif; ?>
            <li>
                <form action="/logout" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <button class="classic-link-button" type="submit">Logout</button>
                </form>
            </li>
        <?php else: ?>
            <li><a href="/login">Login</a></li>
            <li><a href="/register">Register</a></li>
        <?php endif; ?>
    </ul>
</nav>
