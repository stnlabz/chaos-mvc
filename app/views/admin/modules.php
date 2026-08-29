<?php
/**
 * Module Administration
 *
 * CMSEC-2026-4828-A — Authenticated module update requests
 * CMSEC-2026-4828-B — Verified update manifest presentation
 * CMSEC-2026-4828-H — Network-isolated module listing
 * CMSEC-2026-4833-C — Asynchronous verified update discovery
 *
 * Path: /app/views/admin/modules.php
 */

require APPROOT . '/views/inc/head.php';
?>

<p><small><a href="/admin">Admin</a> >> <strong>Modules</strong></small></p>

<div class="container py-5">

    <div class="mb-5">
        <h2 class="fw-bold">Modules</h2>
        <p class="text-muted small text-uppercase">System Components</p>
    </div>

    <?php
    $core = [];
    $addons = [];

    foreach ($data['modules'] as $module) {
        if ($module['is_core']) {
            $core[] = $module;
        } else {
            $addons[] = $module;
        }
    }
    require_once APPROOT . '/core/version.php';
    $systemVersion = defined('CHAOS_VERSION') ? CHAOS_VERSION : '0.0.0';
    ?>

    <!-- CORE -->
    <h5 class="mb-3 text-uppercase text-muted">Core</h5>
    <div class="row g-4 mb-5">
        <?php foreach ($core as $module): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border">
                <div class="card-body d-flex flex-column">

                    <h5 class="fw-bold"><?= htmlspecialchars(ucfirst((string) $module['slug']), ENT_QUOTES, 'UTF-8'); ?></h5>
                    <p class="small text-muted">Core Module</p>

                    <p><small>Version: <strong><?= htmlspecialchars((string) $systemVersion, ENT_QUOTES, 'UTF-8'); ?></strong></small></p>

                    <div class="mt-auto">
                        <a href="/admin/<?= rawurlencode((string) $module['slug']); ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                            Manage
                        </a>

                        <button class="btn btn-sm btn-light w-100" disabled>
                            Managed by System
                        </button>
                    </div>

                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <hr class="my-5">

    <!-- ADDONS -->
    <h5 class="mb-3 text-uppercase text-muted">Addons</h5>
    <div class="row g-4">

        <?php foreach ($addons as $module):

            /*
             * CMSEC-2026-4828-F
             *
             * Database-provided slugs must satisfy the canonical module
             * identifier rule before they can participate in file paths.
             */
            $moduleSlug = trim((string) ($module['slug'] ?? ''));

            if (!preg_match('/^[a-z][a-z0-9_]{1,62}$/', $moduleSlug)) {
                continue;
            }

            /*
             * CMSEC-2026-4830-A — Separated module discovery
             *
             * Disabled view-time behavior read legacy metadata from
             * APPROOT . '/data/modules/' . $moduleSlug . '.json'. Metadata
             * now arrives from the controller's verified user-module scan.
             */
            $config = is_array($module['config'] ?? null)
                ? $module['config']
                : [];
            $version = '0.0.0';
            $desc = '';
            $author = '';
            $domain = '';
            $domainUrl = null;
            $certified = '';
            $updateUrl = '';
            $hasUpdateSource = false;

            if ($config !== []) {
                $version = (string) ($config['version'] ?? '0.0.0');
                $desc = (string) ($config['description'] ?? '');
                $author = (string) ($config['creator'] ?? '');
                $domain = trim((string) ($config['domain'] ?? ''));
                $certified = filter_var(
                    $config['certified'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ) ? 'Yes' : 'No';

                if (
                    $domain !== ''
                    && filter_var(
                        $domain,
                        FILTER_VALIDATE_DOMAIN,
                        FILTER_FLAG_HOSTNAME
                    ) !== false
                ) {
                    $domainUrl = 'https://' . $domain;
                }

                /*
                 * CMSEC-2026-4828-B
                 *
                 * Previous unbounded metadata request retained as a disabled
                 * maintenance record:
                 *
                 * $remote = @file_get_contents(
                 *     $config['update_url'] . '?t=' . time()
                 * );
                 */
                $updateUrl = (string) ($config['update_url'] ?? '');
                $hasUpdateSource =
                    filter_var($updateUrl, FILTER_VALIDATE_URL) !== false
                    && strtolower((string) parse_url($updateUrl, PHP_URL_SCHEME)) === 'https';

                /*
                 * CMSEC-2026-4828-H
                 *
                 * Remote discovery during page rendering is disabled. The
                 * authenticated update action performs the network check.
                 *
                 * Previous behavior opened the HTTPS update URL here and
                 * derived $hasUpdate from the returned manifest.
                 */
                /*
                 * CMSEC-2026-4833-C
                 *
                 * Disabled view-level update URL gate retained for ownership
                 * history. Core discovery is authoritative and validates the
                 * installed signed module's update_url before connecting.
                 *
                 * $hasUpdate =
                 *     filter_var($updateUrl, FILTER_VALIDATE_URL) !== false
                 *     && strtolower(
                 *         (string) parse_url($updateUrl, PHP_URL_SCHEME)
                 *     ) === 'https';
                 */
            }
        ?>

        <div class="col-md-6 col-lg-4">
            <div class="card h-100 border">
                <div class="card-body d-flex flex-column">

                    <h5 class="fw-bold"><?= htmlspecialchars(ucfirst((string) $module['slug']), ENT_QUOTES, 'UTF-8'); ?></h5>

                    <p><small>Version: <strong><?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8'); ?></strong></small></p>
                    <p><small><strong>Description</strong>: <?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'); ?></small></p>
                    <p><small><strong>Author</strong>: <?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8'); ?></small></p>
                    <p><small><strong>Domain</strong>:
                        <?php if ($domainUrl !== null): ?>
                            <a href="<?= htmlspecialchars($domainUrl, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?></a>
                        <?php else: ?>
                            <?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </small></p>
                    <p><small>
                    <strong>Certified</strong>: <?= htmlspecialchars($certified, ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                    </p>

                    <div class="mt-auto">

                        <a href="/admin/<?= rawurlencode((string) $module['slug']); ?>" class="btn btn-sm btn-outline-primary w-100 mb-2">
                            Manage
                        </a>

                        <?php if ($hasUpdateSource): ?>
                            <button class="btn btn-sm btn-secondary w-100 mb-2 btn-update"
                                data-module="<?= htmlspecialchars((string) $module['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-action="check"
                                disabled>
                                Checking for updates...
                            </button>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-secondary w-100 mb-2" disabled
                                title="Add a valid HTTPS update_url to module.json to enable update discovery.">
                                Local Module
                            </button>
                        <?php endif; ?>

                        <form action="/admin/uninstall" method="POST"
                              onsubmit="return confirm('EXTREME DANGER: This will permanently remove all data and files for this module.');">
                            <!-- CMSEC-2026-4828-E: authenticate destructive intent. -->
                            <?= $this->csrf_field(); ?>
                            <input type="hidden" name="module" value="<?= htmlspecialchars($moduleSlug, ENT_QUOTES, 'UTF-8'); ?>">
                            <button type="submit" class="btn btn-sm btn-danger w-100">
                                Nuke
                            </button>
                        </form>

                    </div>

                </div>
            </div>
        </div>

        <?php endforeach; ?>

    </div>

</div>

<script>
/*
 * CMSEC-2026-4828-A — Authenticated module update requests
 * CMSEC-2026-4833-C — Asynchronous verified update discovery
 *
 * Core checks signed remote manifests after page rendering. Individual
 * modules never execute their own network or update logic.
 */
const moduleUpdateCsrfToken = <?= json_encode($this->csrf_token()); ?>;

function moduleRequestBody(module) {
    return new URLSearchParams({
        module,
        csrf_token: moduleUpdateCsrfToken
    }).toString();
}

async function checkModuleUpdate(btn) {
    const module = btn.dataset.module;

    btn.innerText = 'Checking for updates...';
    btn.className = 'btn btn-sm btn-secondary w-100 mb-2 btn-update';
    btn.dataset.action = 'check';
    btn.disabled = true;

    try {
        const response = await fetch('/admin/check_update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: moduleRequestBody(module)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Update check failed.');
        }

        if (data.update_available) {
            btn.innerText = `Update Available → ${data.available_version}`;
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-success');
            btn.dataset.action = 'update';
            btn.disabled = false;
        } else {
            btn.innerText = 'Up to Date';
            btn.dataset.action = 'current';
            btn.disabled = true;
        }
    } catch (error) {
        btn.innerText = 'Check failed — Retry';
        btn.classList.remove('btn-secondary');
        btn.classList.add('btn-outline-secondary');
        btn.dataset.action = 'check';
        btn.disabled = false;
        btn.title = error instanceof Error
            ? error.message
            : 'Update check failed.';
    }
}

async function installModuleUpdate(btn) {
    const module = btn.dataset.module;

    btn.innerText = 'Updating...';
    btn.disabled = true;

    try {
        /*
         * CMSEC-2026-4828-A
         *
         * Previous state-changing GET retained as a disabled record:
         * const res = await fetch(`/admin/update?module=${module}`);
         */
        const response = await fetch('/admin/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: moduleRequestBody(module)
        });
        const data = await response.json();

        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Module update failed.');
        }

        btn.innerText = `Updated → ${data.version}`;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        btn.dataset.action = 'current';
        btn.disabled = true;

        const card = btn.closest('.card');
        const versionEl = card.querySelector('p small strong');

        if (versionEl) {
            versionEl.innerText = data.version;
        }
    } catch (error) {
        btn.innerText = 'Update failed — Retry';
        btn.disabled = false;
        btn.title = error instanceof Error
            ? error.message
            : 'Module update failed.';
    }
}

document.querySelectorAll('.btn-update').forEach(btn => {
    btn.addEventListener('click', () => {
        if (btn.dataset.action === 'update') {
            installModuleUpdate(btn);
        } else if (btn.dataset.action === 'check') {
            checkModuleUpdate(btn);
        }
    });

    /*
     * Each request is asynchronous, so an unavailable developer server does
     * not block the page or checks for other installed modules.
     */
    checkModuleUpdate(btn);
});
</script>

<?php require APPROOT . '/views/inc/foot.php'; ?>
