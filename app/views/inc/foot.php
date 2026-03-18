</div>
<footer class="sw-footer" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; border-top: 1px solid #333; font-size: 0.9rem;">
    <div style="flex: 1; text-align: left;">
        <p style="margin-left: 20px;">© <?= date('Y'); ?> <?= htmlspecialchars($SITE['name'] ?? 'Chaos MVC', ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    
    <div style="flex: 1; text-align: center">
      <a href="https://www.stn-labz.com" target="_blank"><img alt="Static Badge" src="https://img.shields.io/badge/Sponsored-STN_Labz-blue"></a>
    </div>


    <div style="flex: 1; text-align: right;">
        <a href="/manifesto" style="text-decoration: none; color: inherit; margin-left: 10px;">Our Stance</a>
        <a href="/legal/terms" style="text-decoration: none; color: inherit; margin-left: 10px;">Terms</a>
        <a href="/legal/privacy" style="text-decoration: none; color: inherit; margin-left: 10px;">Privacy</a>
        <a href="/security" style="text-decoration: none; color: inherit; margin-left: 10px; margin-right: 20px;">Security</a>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script src="/assets/js/site.js"></script>
</body>
</html>
