<?php

/**
 * Chaos MVC Core Updater Admin View
 *
 * Path: /app/views/admin/updater.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 20:15:00 UTC] */

require APPROOT . '/views/inc/head.php';

$currentVersion = (string) (
    $data['current_version']
    ?? '0.0.0'
);

$update = $data['update'] ?? [];
$status = $data['status'] ?? [];

$available =
    !empty($update['success'])
    && !empty($update['available']);

$targetVersion = (string) (
    $update['target_version']
    ?? ''
);
?>

<p>
    <small>
        <a href="/admin">Admin</a>
        &gt;&gt;
        <strong>Core Updater</strong>
    </small>
</p>

<div class="container py-5">

    <div class="mb-5">
        <h2 class="fw-bold">
            Chaos MVC Core Updater
        </h2>

        <p class="text-muted">
            Installed version:
            <strong id="installed-version">
                <?= htmlspecialchars(
                    $currentVersion,
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </strong>
        </p>
    </div>

    <div class="card border mb-4">
        <div class="card-body">

            <?php if (empty($update['success'])) : ?>

                <div class="alert alert-warning mb-0">
                    Update service could not be reached.

                    <?php if (!empty($update['message'])) : ?>
                        <br>
                        <small>
                            <?= htmlspecialchars(
                                (string) $update['message'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </small>
                    <?php endif; ?>
                </div>

            <?php elseif ($available) : ?>

                <h3 class="h5">
                    Update v<?= htmlspecialchars(
                        $targetVersion,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                    available
                </h3>

                <p class="text-muted">
                    Chaos MVC can update this installation automatically.
                </p>

                <form
                    id="update-form"
                    action="/updater/run"
                    method="POST"
                >
                    <?= $this->csrf_field(); ?>

                    <button
                        id="update-button"
                        type="submit"
                        class="btn btn-primary"
                    >
                        Update Now
                    </button>
                </form>

            <?php else : ?>

                <div class="alert alert-success mb-0">
                    Chaos MVC is up to date.
                </div>

            <?php endif; ?>

        </div>
    </div>

    <div
        id="update-progress-card"
        class="card border"
        <?= ($status['status'] ?? 'idle') === 'idle'
            ? 'hidden'
            : ''; ?>
    >
        <div class="card-body">

            <div class="d-flex justify-content-between mb-2">
                <strong id="update-stage">
                    <?= htmlspecialchars(
                        (string) ($status['stage'] ?? 'Idle'),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </strong>

                <span id="update-percent">
                    <?= (int) ($status['percent'] ?? 0); ?>%
                </span>
            </div>

            <div
                class="progress mb-3"
                role="progressbar"
                aria-label="Chaos MVC update progress"
            >
                <div
                    id="update-progress"
                    class="progress-bar"
                    style="width: <?= (int) (
                        $status['percent']
                        ?? 0
                    ); ?>%;"
                ></div>
            </div>

            <p
                id="update-message"
                class="mb-0 text-muted"
            >
                <?= htmlspecialchars(
                    (string) (
                        $status['message']
                        ?? ''
                    ),
                    ENT_QUOTES,
                    'UTF-8'
                ); ?>
            </p>

            <div
                id="update-error"
                class="alert alert-danger mt-3"
                hidden
            ></div>

        </div>
    </div>

</div>

<script>
/* [AI:GPT-5.6 Sol | 2026-08-25 20:15:00 UTC] */
(() => {
    const form = document.getElementById('update-form');
    const button = document.getElementById('update-button');

    const card = document.getElementById(
        'update-progress-card'
    );

    const stage = document.getElementById(
        'update-stage'
    );

    const percent = document.getElementById(
        'update-percent'
    );

    const progress = document.getElementById(
        'update-progress'
    );

    const message = document.getElementById(
        'update-message'
    );

    const errorBox = document.getElementById(
        'update-error'
    );

    let polling = null;

    const renderStatus = (data) => {
        card.hidden = false;

        const value = Number(
            data.percent || 0
        );

        stage.textContent =
            data.stage || 'Updating';

        percent.textContent =
            `${value}%`;

        progress.style.width =
            `${value}%`;

        message.textContent =
            data.message || '';

        if (data.error) {
            errorBox.textContent = data.error;
            errorBox.hidden = false;
        } else {
            errorBox.hidden = true;
        }

        if (
            data.status === 'complete'
            && value >= 100
        ) {
            clearInterval(polling);

            window.setTimeout(() => {
                window.location.reload();
            }, 1200);
        }

        if (
            data.status === 'failed'
            && value >= 100
        ) {
            clearInterval(polling);

            if (button) {
                button.disabled = false;
                button.textContent = 'Retry Update';
            }
        }
    };

    const pollStatus = async () => {
        try {
            const response = await fetch(
                '/updater/status',
                {
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) {
                return;
            }

            const data = await response.json();

            renderStatus(data);
        } catch (error) {
            // The updater remains authoritative.
            // A temporary polling failure does not alter update state.
        }
    };

    if (!form) {
        return;
    }

    form.addEventListener(
        'submit',
        async (event) => {
            event.preventDefault();

            button.disabled = true;
            button.textContent = 'Updating...';

            card.hidden = false;

            polling = window.setInterval(
                pollStatus,
                750
            );

            await pollStatus();

            try {
                const response = await fetch(
                    form.action,
                    {
                        method: 'POST',
                        body: new FormData(form),
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const result =
                    await response.json();

                if (!result.success) {
                    await pollStatus();
                }
            } catch (error) {
                await pollStatus();
            }
        }
    );
})();
/* [End AI:GPT-5.6 Sol] */
</script>

<?php
require APPROOT . '/views/inc/foot.php';

/* [End AI:GPT-5.6 Sol] */
?>