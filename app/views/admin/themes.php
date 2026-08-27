<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Themes</h1>

    <?php if (!empty($data['message'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars((string) $data['message'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['error'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars((string) $data['error'], ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <form action="/admin/themes" method="POST">
        <?= $this->csrf_field(); ?>

        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <label class="card h-100 p-4">
                    <input type="radio" name="theme" value=""
                        <?= ($data['active_theme'] ?? '') === '' ? 'checked' : ''; ?>>
                    <strong class="mt-3">Chaos MVC</strong>
                    <span class="text-muted">Built-in Core theme</span>
                </label>
            </div>

            <?php foreach (($data['themes'] ?? []) as $installed): ?>
                <div class="col-md-6 col-lg-4">
                    <label class="card h-100 p-4">
                        <input type="radio" name="theme"
                            value="<?= htmlspecialchars((string) $installed['slug'], ENT_QUOTES, 'UTF-8'); ?>"
                            <?= ($data['active_theme'] ?? '') === $installed['slug'] ? 'checked' : ''; ?>>
                        <strong class="mt-3">
                            <?= htmlspecialchars((string) $installed['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </strong>
                        <?php if ($installed['version'] !== ''): ?>
                            <span>Version <?= htmlspecialchars((string) $installed['version'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if ($installed['author'] !== ''): ?>
                            <span>By <?= htmlspecialchars((string) $installed['author'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <?php if ($installed['description'] !== ''): ?>
                            <p class="text-muted mt-2 mb-0">
                                <?= htmlspecialchars((string) $installed['description'], ENT_QUOTES, 'UTF-8'); ?>
                            </p>
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <button class="btn btn-primary mt-4" type="submit">Apply Theme</button>
    </form>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
