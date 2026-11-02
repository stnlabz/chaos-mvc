<?php

$siteName = $SITE['name'] ?? 'Chaos MVC';

$copyrightName = $SITE['copyright_name']
    ?? $siteName;
?>

        </div>
    </main>

    <footer class="site-footer">
        <div class="site-footer-inner">

            <p class="site-copyright">
                &copy;
                <?= date('Y'); ?>
                <?= htmlspecialchars(
                    $copyrightName,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>

            <p class="site-powered-by">
                Built with
                <a
                    href="https://www.chaos-mvc.org"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    ChAoS MVC
                </a>
            </p>

        </div>
    </footer>

</div>

<script src="<?= htmlspecialchars(theme::assetUrl('js/site.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

</body>
</html>