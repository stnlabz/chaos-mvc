<?php

/** Core resolver for installation-owned PHP themes. */
final class theme
{
    private const PARTS = ['head', 'nav', 'foot'];

    public static function activeSlug(): string
    {
        $slug = (string) ($GLOBALS['SITE']['active_theme'] ?? '');
        return self::validSlug($slug) && self::details($slug) !== null
            ? $slug
            : '';
    }

    /** @return array<int, array<string, string>> */
    public static function installed(): array
    {
        $themes = [];

        foreach (glob(USERROOT . '/themes/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $details = self::details(basename($directory));

            if ($details !== null) {
                $themes[] = $details;
            }
        }

        usort($themes, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $themes;
    }

    /** @return array<string, string>|null */
    public static function details(string $slug): ?array
    {
        $root = self::root($slug);
        $manifestFile = $root !== null ? $root . '/theme.json' : '';

        if ($manifestFile === '' || is_link($manifestFile) || !is_file($manifestFile)) {
            return null;
        }

        $raw = file_get_contents($manifestFile);
        $manifest = is_string($raw) ? json_decode($raw, true) : null;

        if (!is_array($manifest) || (string) ($manifest['theme'] ?? '') !== $slug) {
            return null;
        }

        foreach (self::PARTS as $part) {
            if (self::partFile($slug, $part) === null) {
                return null;
            }
        }

        return [
            'slug' => $slug,
            'name' => trim((string) ($manifest['name'] ?? $slug)) ?: $slug,
            'version' => trim((string) ($manifest['version'] ?? '')),
            'author' => trim((string) ($manifest['author'] ?? '')),
            'description' => trim((string) ($manifest['description'] ?? '')),
        ];
    }

    public static function render(string $part, array $scope = []): bool
    {
        $slug = self::activeSlug();
        $file = $slug !== '' ? self::partFile($slug, $part) : null;

        if ($file === null) {
            return false;
        }

        $SITE = $GLOBALS['SITE'] ?? [];
        $data = is_array($scope['data'] ?? null) ? $scope['data'] : [];
        $og = is_array($scope['og'] ?? null) ? $scope['og'] : [];
        $render_md = $scope['render_md'] ?? null;
        require $file;
        return true;
    }

    public static function assetUrl(string $path): string
    {
        return URLROOT
            . '/theme_assets?theme=' . rawurlencode(self::activeSlug())
            . '&file=' . rawurlencode($path);
    }

    public static function activeAssetFile(string $path): ?string
    {
        $slug = self::activeSlug();

        if ($slug === '' || !self::validRelativePath($path)) {
            return null;
        }

        $root = self::root($slug);
        $assetRootPath = $root !== null ? $root . '/assets' : '';
        $assetRoot = $assetRootPath !== '' && !is_link($assetRootPath)
            ? realpath($assetRootPath)
            : false;
        $candidate = $root !== null ? $root . '/assets/' . $path : '';

        if ($assetRoot === false || $candidate === '' || is_link($candidate)) {
            return null;
        }

        $resolved = realpath($candidate);
        return $resolved !== false
            && str_starts_with($resolved, $assetRoot . DIRECTORY_SEPARATOR)
            && is_file($resolved)
                ? $resolved
                : null;
    }

    private static function partFile(string $slug, string $part): ?string
    {
        if (!in_array($part, self::PARTS, true)) {
            return null;
        }

        $root = self::root($slug);

        if ($root === null) {
            return null;
        }

        /*
         * CMSEC-2026-4830-J — Theme Builder layout compatibility.
         *
         * Installation themes conventionally group their PHP layout parts
         * under /inc. Retain root-level lookup as a compatibility fallback
         * for themes created against the initial theme contract.
         */
        $candidates = [
            $root . '/inc/' . $part . '.php',
            $root . '/' . $part . '.php',
        ];

        foreach ($candidates as $candidate) {
            if (is_link($candidate)) {
                continue;
            }

            $resolved = realpath($candidate);

            if (
                $resolved !== false
                && str_starts_with(
                    $resolved,
                    $root . DIRECTORY_SEPARATOR
                )
                && is_file($resolved)
            ) {
                return $resolved;
            }
        }

        return null;
    }

    private static function root(string $slug): ?string
    {
        if (!self::validSlug($slug)) {
            return null;
        }

        $themesRoot = realpath(USERROOT . '/themes');
        $candidate = USERROOT . '/themes/' . $slug;

        if ($themesRoot === false || is_link($candidate)) {
            return null;
        }

        $resolved = realpath($candidate);
        return $resolved !== false
            && str_starts_with($resolved, $themesRoot . DIRECTORY_SEPARATOR)
            && is_dir($resolved)
                ? $resolved
                : null;
    }

    private static function validSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_-]{0,62}$/', $slug);
    }

    private static function validRelativePath(string $path): bool
    {
        return $path !== ''
            && !str_contains($path, '..')
            && !str_contains($path, '\\')
            && !str_starts_with($path, '/')
            && (bool) preg_match('#^[a-zA-Z0-9_./-]+$#', $path);
    }
}
