<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center">

        <h1 class="display-1 fw-bold text-secondary">
            <?= htmlspecialchars((string) ($data['code'] ?? 'Error'), ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <h2 class="fs-3">
            <?= htmlspecialchars((string) ($data['title'] ?? 'Something went wrong'), ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p class="lead text-muted">
            <?= htmlspecialchars((string) ($data['msg'] ?? $data['message'] ?? 'The system encountered an unexpected issue.'), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <?php if (!empty($data['reference'])): ?>
            <p class="text-muted">
                Reference:
                <?= htmlspecialchars((string) $data['reference'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <hr class="my-4">

        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="<?= htmlspecialchars(URLROOT, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg px-4 gap-3">
                Return to Home
            </a>
        </div>

    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
