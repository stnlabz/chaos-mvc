<?php require APPROOT . '/views/inc/head.php'; ?>

<div class="d-flex align-items-center justify-content-center" style="min-height: 70vh;">
    <div class="text-center">

        <h1 class="display-1 fw-bold text-secondary">
            <?= $data['code'] ?? 'Error' ?>
        </h1>

        <h2 class="fs-3">
            <?= $data['title'] ?? 'Something went wrong' ?>
        </h2>

        <p class="lead text-muted">
            <?= $data['msg'] ?? 'The system encountered an unexpected issue.' ?>
        </p>

        <hr class="my-4">

        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <a href="<?= URLROOT; ?>" class="btn btn-primary btn-lg px-4 gap-3">
                Return to Home
            </a>
        </div>

    </div>
</div>

<?php require APPROOT . '/views/inc/foot.php'; ?>
