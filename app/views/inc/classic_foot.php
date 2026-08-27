<?php
$SITE = $GLOBALS['SITE'] ?? ($SITE ?? []);
$siteName = (string) ($SITE['name'] ?? 'Chaos MVC');
$copyrightName = (string) ($SITE['copyright_name'] ?? $siteName);
?>
</main>
<footer class="classic-footer">
    <div class="classic-shell classic-footer-inner">
        <p>© <?= date('Y'); ?> <?= htmlspecialchars($copyrightName, ENT_QUOTES, 'UTF-8'); ?></p>
        <p>Built with <a href="https://www.chaos-mvc.org" rel="noopener noreferrer">Chaos MVC</a></p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoFForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcX/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
