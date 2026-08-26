<?php

/**
 * Site Configuration Admin View
 *
 * Path: /app/views/admin/site.php
 */

/* [AI:GPT-5.6 Sol | 2026-08-25 19:03:00 UTC] */
require APPROOT . '/views/inc/head.php';

$siteConfig = $data['site'] ?? [];
$mailerConfig = $data['mailer'] ?? [];
?>

<p>
    <small>
        <a href="/admin">Admin</a>
        &gt;&gt;
        <strong>Site Configuration</strong>
    </small>
</p>

<div class="container py-5">

    <div class="mb-5">
        <h2 class="fw-bold">
            Site Configuration
        </h2>

        <p class="text-muted">
            Configure installation identity and outbound email settings.
        </p>
    </div>

    <?php if (!empty($data['success'])) : ?>
        <div class="alert alert-success" role="alert">
            <?= htmlspecialchars(
                (string) $data['success'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($data['error'])) : ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars(
                (string) $data['error'],
                ENT_QUOTES,
                'UTF-8'
            ); ?>
        </div>
    <?php endif; ?>

    <div class="card mb-5">

        <div class="card-body">

            <h4 class="card-title mb-4">
                Site Identity
            </h4>

            <form action="/admin/site" method="POST">

                <?= $this->csrf_field(); ?>

                <input
                    type="hidden"
                    name="section"
                    value="site"
                >

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="site-name"
                    >
                        Site Name
                    </label>

                    <input
                        class="form-control"
                        id="site-name"
                        name="name"
                        type="text"
                        required
                        value="<?= htmlspecialchars(
                            (string) ($siteConfig['name'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="copyright-name"
                    >
                        Copyright Name
                    </label>

                    <input
                        class="form-control"
                        id="copyright-name"
                        name="copyright_name"
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($siteConfig['copyright_name'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="site-author"
                    >
                        Author
                    </label>

                    <input
                        class="form-control"
                        id="site-author"
                        name="author"
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($siteConfig['author'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >
                </div>

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="site-description"
                    >
                        Description
                    </label>

                    <textarea
                        class="form-control"
                        id="site-description"
                        name="description"
                        rows="3"
                    ><?= htmlspecialchars(
                        (string) ($siteConfig['description'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></textarea>
                </div>

                <div class="mb-3">
                    <label
                        class="form-label"
                        for="site-keywords"
                    >
                        Keywords
                    </label>

                    <input
                        class="form-control"
                        id="site-keywords"
                        name="keywords"
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($siteConfig['keywords'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >
                </div>

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Save Site Configuration
                </button>

            </form>

        </div>
    </div>

    <div class="card">

        <div class="card-body">

            <h4 class="card-title mb-4">
                Email Server
            </h4>

            <form action="/admin/site" method="POST">

                <?= $this->csrf_field(); ?>

                <input
                    type="hidden"
                    name="section"
                    value="mail"
                >

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="mail-host"
                    >
                        SMTP Host
                    </label>

                    <input
                        class="form-control"
                        id="mail-host"
                        name="host"
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($mailerConfig['host'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                </div>

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label
                            class="form-label"
                            for="mail-port"
                        >
                            SMTP Port
                        </label>

                        <input
                            class="form-control"
                            id="mail-port"
                            name="port"
                            type="number"
                            min="1"
                            max="65535"
                            value="<?= (int) (
                                $mailerConfig['port'] ?? 587
                            ); ?>"
                        >

                    </div>

                    <div class="col-md-6 mb-3">

                        <label
                            class="form-label"
                            for="mail-encryption"
                        >
                            Encryption
                        </label>

                        <?php
                        $encryption = (string) (
                            $mailerConfig['encryption']
                            ?? 'starttls'
                        );
                        ?>

                        <select
                            class="form-select"
                            id="mail-encryption"
                            name="encryption"
                        >
                            <option
                                value=""
                                <?= $encryption === '' ? 'selected' : ''; ?>
                            >
                                None
                            </option>

                            <option
                                value="starttls"
                                <?= $encryption === 'starttls'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                STARTTLS
                            </option>

                            <option
                                value="smtps"
                                <?= $encryption === 'smtps'
                                    ? 'selected'
                                    : ''; ?>
                            >
                                SMTPS
                            </option>
                        </select>

                    </div>

                </div>

                <div class="form-check mb-3">

                    <input
                        type="hidden"
                        name="smtp_auth"
                        value="0"
                    >

                    <input
                        class="form-check-input"
                        id="smtp-auth"
                        name="smtp_auth"
                        type="checkbox"
                        value="1"
                        <?= !empty($mailerConfig['smtp_auth'])
                            ? 'checked'
                            : ''; ?>
                    >

                    <label
                        class="form-check-label"
                        for="smtp-auth"
                    >
                        SMTP Authentication
                    </label>

                </div>

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="mail-username"
                    >
                        Username
                    </label>

                    <input
                        class="form-control"
                        id="mail-username"
                        name="username"
                        type="text"
                        autocomplete="username"
                        value="<?= htmlspecialchars(
                            (string) ($mailerConfig['username'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                </div>

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="mail-password"
                    >
                        Password
                    </label>

                    <input
                        class="form-control"
                        id="mail-password"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                    >

                    <div class="form-text">
                        Leave blank to keep the currently stored password.
                    </div>

                </div>

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="from-email"
                    >
                        From Email
                    </label>

                    <input
                        class="form-control"
                        id="from-email"
                        name="from_email"
                        type="email"
                        value="<?= htmlspecialchars(
                            (string) ($mailerConfig['from_email'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                </div>

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="from-name"
                    >
                        From Name
                    </label>

                    <input
                        class="form-control"
                        id="from-name"
                        name="from_name"
                        type="text"
                        value="<?= htmlspecialchars(
                            (string) ($mailerConfig['from_name'] ?? ''),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                    >

                </div>

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Save Email Configuration
                </button>

            </form>

        </div>
    </div>

</div>

<?php
require APPROOT . '/views/inc/foot.php';

/* [End AI:GPT-5.6 Sol] */