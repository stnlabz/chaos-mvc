<?php require APPROOT . '/views/inc/head.php'; ?>

<p>
    <small>
        <a href="/admin">Admin</a> >>
        <strong>Pages</strong>
    </small>
</p>

<?php
$pages = is_array($data['pages'] ?? null)
    ? $data['pages']
    : [];

$current = is_array($data['page'] ?? null)
    ? $data['page']
    : null;

$isEdit = $current !== null;

$slug = (string) ($current['slug'] ?? '');
$title = (string) ($current['title'] ?? '');
$description = (string) ($current['description'] ?? '');
$status = (string) ($current['status'] ?? 'draft');
$contentFile = (string) ($current['content_file'] ?? 'main.md');
$content = (string) ($current['content'] ?? '');
?>

<h1>Pages</h1>

<p>
    Filesystem-backed pages stored under
    <code>/user/pages/{slug}/</code>.
</p>

<p>
    <a href="/admin/page">Create New Page</a>
</p>

<?php if ($pages === []): ?>
    <p>No pages have been created yet.</p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Content File</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pages as $page): ?>
                <?php
                $pageSlug = (string) ($page['slug'] ?? '');
                $pageTitle = (string) ($page['title'] ?? $pageSlug);
                $pageStatus = (string) ($page['status'] ?? 'invalid');
                $pageContentFile = (string) ($page['content_file'] ?? '');
                $pageValid = (bool) ($page['valid'] ?? true);
                ?>
                <tr>
                    <td>
                        <?= htmlspecialchars(
                            $pageTitle,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </td>
                    <td>
                        <code><?= htmlspecialchars(
                            $pageSlug,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?></code>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $pageValid ? $pageStatus : 'invalid',
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </td>
                    <td>
                        <?= htmlspecialchars(
                            $pageContentFile,
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>
                    </td>
                    <td>
                        <?php if ($pageValid): ?>
                            <a
                                href="/admin/page?edit=<?= rawurlencode(
                                    $pageSlug
                                ); ?>"
                            >Edit</a>

                            <?php if ($pageStatus === 'published'): ?>
                                |
                                <a
                                    href="/<?= rawurlencode($pageSlug); ?>"
                                    target="_blank"
                                    rel="noopener"
                                >View</a>
                            <?php endif; ?>
                        <?php endif; ?>

                        |
                        <form
                            action="/admin/page"
                            method="POST"
                            style="display:inline;"
                            onsubmit="return confirm('Delete this page?');"
                        >
                            <?= $this->csrf_field(); ?>

                            <input
                                type="hidden"
                                name="action"
                                value="delete"
                            >

                            <input
                                type="hidden"
                                name="slug"
                                value="<?= htmlspecialchars(
                                    $pageSlug,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ); ?>"
                            >

                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<hr>

<h2><?= $isEdit ? 'Edit Page' : 'Create Page'; ?></h2>

<form action="/admin/page" method="POST">
    <?= $this->csrf_field(); ?>

    <input
        type="hidden"
        name="action"
        value="<?= $isEdit ? 'update' : 'create'; ?>"
    >

    <?php if ($isEdit): ?>
        <input
            type="hidden"
            name="original_slug"
            value="<?= htmlspecialchars(
                $slug,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
        >
    <?php endif; ?>

    <p>
        <label for="page-title"><strong>Title</strong></label><br>
        <input
            id="page-title"
            type="text"
            name="title"
            value="<?= htmlspecialchars(
                $title,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            required
        >
    </p>

    <p>
        <label for="page-slug"><strong>Slug</strong></label><br>
        <input
            id="page-slug"
            type="text"
            name="slug"
            value="<?= htmlspecialchars(
                $slug,
                ENT_QUOTES,
                'UTF-8'
            ); ?>"
            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
            placeholder="about-us"
            required
        ><br>
        <small>Lowercase letters, numbers, and hyphens only.</small>
    </p>

    <p>
        <label for="page-description">
            <strong>Description</strong>
        </label><br>

        <textarea
            id="page-description"
            name="description"
            rows="3"
            cols="80"
        ><?= htmlspecialchars(
            $description,
            ENT_QUOTES,
            'UTF-8'
        ); ?></textarea>
    </p>

    <p>
        <label for="page-status"><strong>Status</strong></label><br>

        <select id="page-status" name="status">
            <option
                value="draft"
                <?= $status === 'draft' ? 'selected' : ''; ?>
            >Draft</option>

            <option
                value="published"
                <?= $status === 'published' ? 'selected' : ''; ?>
            >Published</option>
        </select>
    </p>

    <p>
        <label for="page-content-file">
            <strong>Content File</strong>
        </label><br>

        <select id="page-content-file" name="content_file">
            <option
                value="main.md"
                <?= $contentFile === 'main.md' ? 'selected' : ''; ?>
            >Markdown</option>

            <option
                value="main.html"
                <?= $contentFile === 'main.html' ? 'selected' : ''; ?>
            >HTML</option>

            <option
                value="main.txt"
                <?= $contentFile === 'main.txt' ? 'selected' : ''; ?>
            >Plain Text</option>
        </select>
    </p>

    <p>
        <label for="page-content"><strong>Content</strong></label><br>

        <textarea
            id="page-content"
            name="content"
            rows="24"
            cols="100"
        ><?= htmlspecialchars(
            $content,
            ENT_QUOTES,
            'UTF-8'
        ); ?></textarea>
    </p>

    <p>
        <button type="submit">
            <?= $isEdit ? 'Save Page' : 'Create Page'; ?>
        </button>

        <?php if ($isEdit): ?>
            <a href="/admin/page">Cancel</a>
        <?php endif; ?>
    </p>
</form>

<?php require APPROOT . '/views/inc/foot.php'; ?>
