<?php
/* [AI:GPT-5.6 Sol | 2026-08-30 18:10:00 UTC] */

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

<script src="<?= theme::assetUrl('js/site.js'); ?>"></script>

</body>
</html>

<?php /* [End AI:GPT-5.6 Sol] */ ?>