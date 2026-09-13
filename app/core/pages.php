<?php

/**
 * Chaos MVC Core Pages
 *
 * Provides filesystem-backed page discovery and CRUD operations for
 * /user/pages/{slug}. The filesystem is the authoritative page registry.
 *
 * A valid page contains:
 *
 * /user/pages/{slug}/page.json
 * /user/pages/{slug}/main.*
 *
 * Path: /app/core/pages.php
 */

/* [AI:GPT-5.6 Sol | 2026-09-13 02:33:34 UTC] */
final class pages
{
    /**
     * Page metadata filename.
     */
    private const PAGE_FILE = 'page.json';

    /**
     * Default page content filename.
     */
    private const DEFAULT_CONTENT_FILE = 'main.md';

    /**
     * Valid public page slug.
     *
     * Pages intentionally allow hyphens even though controller names do not.
     */
    private const SLUG_PATTERN = '/^[a-z][a-z0-9-]{0,62}$/';

    /**
     * Valid page body filename.
     *
     * Page content must remain a direct child named main.*. Supported passive
     * formats are Markdown, HTML, plain text, and JSON. PHP and other
     * executable filenames are not permitted by this contract.
     */
    /* [AI:GPT-5.6 Sol | 2026-09-13 19:30:45 UTC] */
    private const CONTENT_FILE_PATTERN = '/^main\.(md|html|txt|json)$/';
    /* [End AI:GPT-5.6 Sol] */

    /**
     * Supported publication states.
     */
    private const STATUSES = [
        'draft',
        'published',
    ];

    /**
     * Return all discovered page directories.
     *
     * Valid and invalid page directories are returned so Admin can identify
     * incomplete or damaged page definitions rather than silently hiding
     * them.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        $root = self::root(false);

        if ($root === null) {
            return [];
        }

        $pages = [];

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory)) {
                continue;
            }

            $pages[] = self::inspectDirectory($directory);
        }

        usort(
            $pages,
            static function (array $left, array $right): int {
                $leftTitle = (string) ($left['title'] ?? $left['slug'] ?? '');
                $rightTitle = (string) ($right['title'] ?? $right['slug'] ?? '');

                return strcasecmp($leftTitle, $rightTitle);
            }
        );

        return $pages;
    }

    /**
     * Determine whether a valid page exists.
     *
     * Public routing should use the default published-only behavior.
     * Admin may pass false when checking drafts.
     *
     * @param string $slug          Page slug.
     * @param bool   $publishedOnly Require published status.
     *
     * @return bool
     */
    public static function exists(
        string $slug,
        bool $publishedOnly = true
    ): bool {
        return self::get($slug, $publishedOnly) !== null;
    }

    /**
     * Load one valid page.
     *
     * @param string $slug          Page slug.
     * @param bool   $publishedOnly Require published status.
     *
     * @return array<string, mixed>|null
     */
    public static function get(
        string $slug,
        bool $publishedOnly = false
    ): ?array {
        if (!self::validSlug($slug)) {
            return null;
        }

        $directory = self::pageDirectory($slug, false);

        if ($directory === null) {
            return null;
        }

        $page = self::inspectDirectory($directory);

        if (($page['valid'] ?? false) !== true) {
            return null;
        }

        if (
            $publishedOnly
            && (string) ($page['status'] ?? '') !== 'published'
        ) {
            return null;
        }

        return $page;
    }

    /**
     * Create a page directory, page.json, and main.* content file.
     *
     * Expected input keys:
     * - slug
     * - title
     * - description (optional)
     * - status (optional, defaults to draft)
     * - content_file (optional, defaults to main.md)
     * - content (optional)
     *
     * @param array<string, mixed> $input Page input.
     *
     * @return array<string, mixed>
     */
    public static function create(array $input): array
    {
        $slug = self::normalizeSlug(
            (string) ($input['slug'] ?? '')
        );

        self::assertValidSlug($slug);

        $title = trim((string) ($input['title'] ?? ''));

        if ($title === '') {
            throw new InvalidArgumentException(
                'Page title is required.'
            );
        }

        $contentFile = self::normalizeContentFile(
            (string) (
                $input['content_file']
                ?? self::DEFAULT_CONTENT_FILE
            )
        );

        $status = self::normalizeStatus(
            (string) ($input['status'] ?? 'draft')
        );

        $root = self::root(true);

        if ($root === null) {
            throw new RuntimeException(
                'Pages root could not be created.'
            );
        }

        $directory = $root . '/' . $slug;

        if (file_exists($directory) || is_link($directory)) {
            throw new RuntimeException(
                'A page with that slug already exists.'
            );
        }

        if (!mkdir($directory, 0755)) {
            throw new RuntimeException(
                'Page directory could not be created.'
            );
        }

        $now = gmdate('c');
        $metadata = [
            'slug' => $slug,
            'title' => $title,
            'description' => trim(
                (string) ($input['description'] ?? '')
            ),
            'status' => $status,
            'content_file' => $contentFile,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        try {
            self::writeContent(
                $directory . '/' . $contentFile,
                (string) ($input['content'] ?? '')
            );

            self::writeMetadata(
                $directory . '/' . self::PAGE_FILE,
                $metadata
            );
        } catch (Throwable $exception) {
            self::removeDirectory($directory);
            throw $exception;
        }

        $page = self::get($slug, false);

        if ($page === null) {
            self::removeDirectory($directory);

            throw new RuntimeException(
                'Created page did not pass validation.'
            );
        }

        return $page;
    }

    /**
     * Update a page and optionally rename its slug/content file.
     *
     * The input slug, when supplied, is treated as the desired new slug.
     * Unknown metadata fields already present in page.json are preserved.
     *
     * @param string               $slug  Existing page slug.
     * @param array<string, mixed> $input Updated page input.
     *
     * @return array<string, mixed>
     */
    public static function update(string $slug, array $input): array
    {
        self::assertValidSlug($slug);

        $directory = self::pageDirectory($slug, false);

        if ($directory === null) {
            throw new RuntimeException('Page does not exist.');
        }

        $current = self::inspectDirectory($directory);

        if (($current['valid'] ?? false) !== true) {
            throw new RuntimeException(
                'Invalid page cannot be updated through normal CRUD.'
            );
        }

        $metadataPath = $directory . '/' . self::PAGE_FILE;
        $metadata = self::readMetadata($metadataPath);

        if ($metadata === null) {
            throw new RuntimeException(
                'Page metadata could not be loaded.'
            );
        }

        $newSlug = array_key_exists('slug', $input)
            ? self::normalizeSlug((string) $input['slug'])
            : $slug;

        self::assertValidSlug($newSlug);

        $newTitle = array_key_exists('title', $input)
            ? trim((string) $input['title'])
            : (string) $metadata['title'];

        if ($newTitle === '') {
            throw new InvalidArgumentException(
                'Page title is required.'
            );
        }

        $oldContentFile = self::normalizeContentFile(
            (string) $metadata['content_file']
        );

        $newContentFile = array_key_exists('content_file', $input)
            ? self::normalizeContentFile(
                (string) $input['content_file']
            )
            : $oldContentFile;

        $newStatus = array_key_exists('status', $input)
            ? self::normalizeStatus((string) $input['status'])
            : self::normalizeStatus((string) $metadata['status']);

        $oldContentPath = $directory . '/' . $oldContentFile;
        $oldContent = file_get_contents($oldContentPath);

        if (!is_string($oldContent)) {
            throw new RuntimeException(
                'Page content could not be loaded.'
            );
        }

        $newContent = array_key_exists('content', $input)
            ? (string) $input['content']
            : $oldContent;

        $metadata['slug'] = $newSlug;
        $metadata['title'] = $newTitle;
        $metadata['description'] = array_key_exists('description', $input)
            ? trim((string) $input['description'])
            : trim((string) ($metadata['description'] ?? ''));
        $metadata['status'] = $newStatus;
        $metadata['content_file'] = $newContentFile;
        $metadata['updated_at'] = gmdate('c');

        if (!isset($metadata['created_at'])) {
            $metadata['created_at'] = $metadata['updated_at'];
        }

        $root = self::root(true);

        if ($root === null) {
            throw new RuntimeException(
                'Pages root is unavailable.'
            );
        }

        $targetDirectory = $root . '/' . $newSlug;

        if (
            $newSlug !== $slug
            && (file_exists($targetDirectory) || is_link($targetDirectory))
        ) {
            throw new RuntimeException(
                'A page with the new slug already exists.'
            );
        }

        $workingDirectory = $directory;

        if ($newSlug !== $slug) {
            if (!rename($directory, $targetDirectory)) {
                throw new RuntimeException(
                    'Page directory could not be renamed.'
                );
            }

            $workingDirectory = $targetDirectory;
        }

        $newContentPath = $workingDirectory . '/' . $newContentFile;
        $newMetadataPath = $workingDirectory . '/' . self::PAGE_FILE;

        try {
            self::writeContent($newContentPath, $newContent);
            self::writeMetadata($newMetadataPath, $metadata);

            if (
                $oldContentFile !== $newContentFile
                && is_file($workingDirectory . '/' . $oldContentFile)
            ) {
                unlink($workingDirectory . '/' . $oldContentFile);
            }
        } catch (Throwable $exception) {
            if ($newSlug !== $slug && is_dir($workingDirectory)) {
                @rename($workingDirectory, $directory);
            }

            throw $exception;
        }

        $page = self::get($newSlug, false);

        if ($page === null) {
            throw new RuntimeException(
                'Updated page did not pass validation.'
            );
        }

        return $page;
    }

    /**
     * Delete a page by moving it into the Pages trash directory.
     *
     * The page is removed from routing immediately while remaining available
     * for manual recovery until a separate cleanup policy removes it.
     *
     * @param string $slug Page slug.
     *
     * @return void
     */
    public static function delete(string $slug): void
    {
        self::assertValidSlug($slug);

        $directory = self::pageDirectory($slug, false);

        if ($directory === null) {
            throw new RuntimeException('Page does not exist.');
        }

        $root = self::root(true);

        if ($root === null) {
            throw new RuntimeException(
                'Pages root is unavailable.'
            );
        }

        $trashRoot = $root . '/.trash';

        if (
            !is_dir($trashRoot)
            && !mkdir($trashRoot, 0755)
        ) {
            throw new RuntimeException(
                'Pages trash directory could not be created.'
            );
        }

        if (is_link($trashRoot)) {
            throw new RuntimeException(
                'Pages trash directory is not trusted.'
            );
        }

        $trashName = gmdate('YmdHis')
            . '-'
            . $slug
            . '-'
            . bin2hex(random_bytes(4));

        $target = $trashRoot . '/' . $trashName;

        if (!rename($directory, $target)) {
            throw new RuntimeException(
                'Page could not be moved to trash.'
            );
        }
    }

    /**
     * Normalize a page slug supplied by Admin.
     *
     * @param string $slug Page slug.
     *
     * @return string
     */
    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/\s+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    /**
     * Determine whether a slug follows the Pages contract.
     *
     * @param string $slug Page slug.
     *
     * @return bool
     */
    public static function validSlug(string $slug): bool
    {
        return preg_match(self::SLUG_PATTERN, $slug) === 1;
    }

    /**
     * Inspect a page directory without hiding validation failures.
     *
     * @param string $directory Page directory.
     *
     * @return array<string, mixed>
     */
    private static function inspectDirectory(string $directory): array
    {
        $slug = basename($directory);
        $errors = [];
        $metadata = null;
        $content = '';
        $contentFile = '';
        $contentPath = '';

        if (!self::validSlug($slug)) {
            $errors[] = 'Invalid page directory slug.';
        }

        if (is_link($directory)) {
            $errors[] = 'Page directory may not be a symbolic link.';
        }

        $root = self::root(false);
        $resolved = realpath($directory);

        if (
            $root === null
            || $resolved === false
            || !str_starts_with(
                $resolved,
                $root . DIRECTORY_SEPARATOR
            )
        ) {
            $errors[] = 'Page directory is outside the Pages root.';
        }

        $metadataPath = $directory . '/' . self::PAGE_FILE;

        if (!is_file($metadataPath) || is_link($metadataPath)) {
            $errors[] = 'page.json is missing or invalid.';
        } else {
            $metadata = self::readMetadata($metadataPath);

            if ($metadata === null) {
                $errors[] = 'page.json is not valid JSON metadata.';
            }
        }

        if (is_array($metadata)) {
            $metadataSlug = (string) ($metadata['slug'] ?? '');

            if ($metadataSlug !== $slug) {
                $errors[] = 'page.json slug does not match its directory.';
            }

            if (trim((string) ($metadata['title'] ?? '')) === '') {
                $errors[] = 'Page title is missing.';
            }

            $status = (string) ($metadata['status'] ?? '');

            if (!in_array($status, self::STATUSES, true)) {
                $errors[] = 'Page status is invalid.';
            }

            try {
                $contentFile = self::normalizeContentFile(
                    (string) ($metadata['content_file'] ?? '')
                );
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }

            if ($contentFile !== '') {
                $contentPath = $directory . '/' . $contentFile;

                if (!is_file($contentPath) || is_link($contentPath)) {
                    $errors[] = 'Page content file is missing or invalid.';
                } else {
                    $rawContent = file_get_contents($contentPath);

                    if (!is_string($rawContent)) {
                        $errors[] = 'Page content could not be read.';
                    } else {
                        $content = $rawContent;
                    }
                }
            }
        }

        return [
            'slug' => $slug,
            'title' => is_array($metadata)
                ? (string) ($metadata['title'] ?? $slug)
                : $slug,
            'description' => is_array($metadata)
                ? (string) ($metadata['description'] ?? '')
                : '',
            'status' => is_array($metadata)
                ? (string) ($metadata['status'] ?? '')
                : '',
            'content_file' => $contentFile,
            'content_path' => $contentPath,
            'content' => $content,
            'metadata' => $metadata ?? [],
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Resolve the trusted Pages root.
     *
     * @param bool $create Create the directory when missing.
     *
     * @return string|null
     */
    private static function root(bool $create): ?string
    {
        $root = USERROOT . '/pages';

        if (!is_dir($root)) {
            if (!$create) {
                return null;
            }

            if (!mkdir($root, 0755, true)) {
                return null;
            }
        }

        if (is_link($root)) {
            throw new RuntimeException(
                'Pages root may not be a symbolic link.'
            );
        }

        $resolved = realpath($root);

        if ($resolved === false) {
            return null;
        }

        return $resolved;
    }

    /**
     * Resolve one trusted page directory.
     *
     * @param string $slug   Page slug.
     * @param bool   $create Create Pages root when missing.
     *
     * @return string|null
     */
    private static function pageDirectory(
        string $slug,
        bool $create
    ): ?string {
        $root = self::root($create);

        if ($root === null) {
            return null;
        }

        $candidate = $root . '/' . $slug;

        if (!is_dir($candidate) || is_link($candidate)) {
            return null;
        }

        $resolved = realpath($candidate);

        if (
            $resolved === false
            || !str_starts_with(
                $resolved,
                $root . DIRECTORY_SEPARATOR
            )
        ) {
            return null;
        }

        return $resolved;
    }

    /**
     * Read page.json.
     *
     * @param string $path Metadata path.
     *
     * @return array<string, mixed>|null
     */
    private static function readMetadata(string $path): ?array
    {
        $raw = file_get_contents($path);

        if (!is_string($raw)) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Write page metadata atomically.
     *
     * @param string               $path     Metadata path.
     * @param array<string, mixed> $metadata Metadata.
     *
     * @return void
     */
    private static function writeMetadata(
        string $path,
        array $metadata
    ): void {
        $json = json_encode(
            $metadata,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if (!is_string($json)) {
            throw new RuntimeException(
                'Page metadata could not be encoded.'
            );
        }

        self::atomicWrite($path, $json . PHP_EOL);
    }

    /**
     * Write page content atomically.
     *
     * @param string $path    Content path.
     * @param string $content Page content.
     *
     * @return void
     */
    private static function writeContent(
        string $path,
        string $content
    ): void {
        self::atomicWrite($path, $content);
    }

    /**
     * Atomically replace one regular file.
     *
     * @param string $path    Target path.
     * @param string $content File content.
     *
     * @return void
     */
    private static function atomicWrite(
        string $path,
        string $content
    ): void {
        $directory = dirname($path);

        if (!is_dir($directory) || is_link($directory)) {
            throw new RuntimeException(
                'Target directory is not trusted.'
            );
        }

        if (is_link($path)) {
            throw new RuntimeException(
                'Target file may not be a symbolic link.'
            );
        }

        $temporary = $directory
            . '/.'
            . basename($path)
            . '.'
            . bin2hex(random_bytes(8))
            . '.tmp';

        $written = file_put_contents(
            $temporary,
            $content,
            LOCK_EX
        );

        if ($written === false) {
            @unlink($temporary);

            throw new RuntimeException(
                'Page file could not be written.'
            );
        }

        if (!rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException(
                'Page file could not be committed.'
            );
        }
    }

    /**
     * Normalize and validate a content filename.
     *
     * @param string $filename Content filename.
     *
     * @return string
     */
    private static function normalizeContentFile(string $filename): string
    {
        $filename = strtolower(trim($filename));

        if (
            $filename === ''
            || basename($filename) !== $filename
            || preg_match(
                self::CONTENT_FILE_PATTERN,
                $filename
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Page content file must be main.md, main.html, main.txt, or main.json.'
            );
        }

        return $filename;
    }

    /**
     * Normalize and validate page status.
     *
     * @param string $status Page status.
     *
     * @return string
     */
    private static function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException(
                'Page status must be draft or published.'
            );
        }

        return $status;
    }

    /**
     * Require a valid page slug.
     *
     * @param string $slug Page slug.
     *
     * @return void
     */
    private static function assertValidSlug(string $slug): void
    {
        if (!self::validSlug($slug)) {
            throw new InvalidArgumentException(
                'Page slug must begin with a lowercase letter and contain only lowercase letters, numbers, and hyphens.'
            );
        }
    }

    /**
     * Remove a newly-created page directory after a failed create operation.
     *
     * This helper is intentionally private and is not the normal page delete
     * path. Normal deletion moves the page into the Pages trash directory.
     *
     * @param string $directory Directory to remove.
     *
     * @return void
     */
    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory) || is_link($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;

            if (is_dir($path) && !is_link($path)) {
                self::removeDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($directory);
    }
}
/* [End AI:GPT-5.6 Sol] */
