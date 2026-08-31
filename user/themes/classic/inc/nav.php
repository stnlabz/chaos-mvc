<?php
/* [AI:GPT-5.6 Sol | 2026-08-30 18:10:00 UTC] */

$siteName = $SITE['name'] ?? 'Chaos MVC';
$siteDescription = $SITE['description'] ?? '';
?>

<header class="site-header">
    <div class="site-header-inner">

        <div class="site-identity">
            <a
                class="site-title"
                href="/"
            >
                <?= htmlspecialchars(
                    $siteName,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </a>

            <?php if ($siteDescription !== ''): ?>
                <p class="site-description">
                    <?= htmlspecialchars(
                        $siteDescription,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </p>
            <?php endif; ?>
        </div>

        <nav
            class="site-navigation"
            aria-label="Primary navigation"
        >
            <ul class="site-navigation-list">

                <li>
                    <a href="/">Home</a>
                </li>

                <?php if (isset($_SESSION['user_id'])): ?>

                    <?php if (
                        isset($_SESSION['user_level'])
                        && (int) $_SESSION['user_level'] >= 9
                    ): ?>
                        <li>
                            <a href="/admin">Admin</a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a href="/logout">Logout</a>
                    </li>

                <?php else: ?>

                    <li>
                        <a href="/login">Login</a>
                    </li>

                <?php endif; ?>

            </ul>
        </nav>

    </div>
</header>

<?php /* [End AI:GPT-5.6 Sol] */ ?>