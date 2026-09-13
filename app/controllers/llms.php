<?php

/**
 * LLMS Map Controller
 *
 * Generates llms.txt from the current ChAoS MVC public ownership model:
 * Core controllers, userland modules, and published filesystem Pages.
 *
 * LOCKED CORE FILE
 * SEO generation infrastructure
 * Modifications require explicit authorization.
 *
 * [Human:Mei | 2026-03-11 02:58:00 UTC]
 */

/* [AI:GPT-5.6 Sol | 2026-09-13 20:06:31 UTC] */
class llms extends controller
{
    public static $is_core = true;

    /**
     * Rebuild llms.txt.
     */
    public function index(): bool
    {
        $host = rtrim(URLROOT, '/');
        $title = str_replace(
            ["[", "]", "\r", "\n"],
            '',
            $this->siteName()
        );

        $txt = '# ' . $title . ' Map' . PHP_EOL . PHP_EOL;

        $txt .= '## Core Routes' . PHP_EOL;

        foreach ($this->discoverCoreRoutes() as $route) {
            $slug = (string) $route['slug'];
            $label = str_replace(
                ["[", "]", "\r", "\n"],
                '',
                (string) $route['title']
            );
            $url = $slug === 'home'
                ? $host
                : $host . '/' . rawurlencode($slug);

            $txt .= '- [' . $label . '](' . $url . ')' . PHP_EOL;
        }

        $txt .= PHP_EOL . '## User Modules' . PHP_EOL;

        foreach ($this->discoverUserModules() as $module) {
            $label = str_replace(
                ["[", "]", "\r", "\n"],
                '',
                (string) $module['title']
            );
            $url = $host
                . '/'
                . rawurlencode((string) $module['slug']);

            $txt .= '- [' . $label . '](' . $url . ')' . PHP_EOL;
        }

        $txt .= PHP_EOL . '## Pages' . PHP_EOL;

        foreach ($this->discoverPublishedPages() as $page) {
            $label = str_replace(
                ["[", "]", "\r", "\n"],
                '',
                (string) $page['title']
            );
            $url = $host
                . '/'
                . rawurlencode((string) $page['slug']);

            $txt .= '- [' . $label . '](' . $url . ')' . PHP_EOL;
        }

        if (
            file_put_contents(
                PUBROOT . '/llms.txt',
                $txt,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException('Could not write llms.txt.');
        }

        return true;
    }


    /**
     * Discover public Core controller routes.
     *
     * @return array<int, array{slug: string, title: string, description: string}>
     */
    private function discoverCoreRoutes(): array
    {
        $excluded = [
            'admin',
            'auth',
            'health',
            'sentinel',
            'modules',
            'ror',
            'llms',
            'sitemap',
            'rss',
            'page',
            'error_handler',
            'media',
            'accounts',
            'traffic',
        ];

        $routes = [];

        foreach (glob(APPROOT . '/controllers/*.php') ?: [] as $file) {
            if (!is_file($file) || is_link($file)) {
                continue;
            }

            $slug = strtolower(basename($file, '.php'));

            if (
                !preg_match('/^[a-z][a-z0-9_]{0,62}$/', $slug)
                || in_array($slug, $excluded, true)
            ) {
                continue;
            }

            $routes[] = [
                'slug' => $slug,
                'title' => $slug === 'home'
                    ? $this->siteName()
                    : ucwords(str_replace('_', ' ', $slug)),
                'description' => '',
            ];
        }

        usort(
            $routes,
            static fn (array $left, array $right): int =>
                strcmp($left['slug'], $right['slug'])
        );

        return $routes;
    }

    /**
     * Discover public userland module routes without executing module PHP.
     *
     * A module owns /{slug} only when its directory, module.json identity,
     * controller file, and declared index route all agree.
     *
     * @return array<int, array{slug: string, title: string, description: string}>
     */
    private function discoverUserModules(): array
    {
        $modules = [];

        foreach (glob(USERROOT . '/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory)) {
                continue;
            }

            $slug = basename($directory);

            if (!preg_match('/^[a-z][a-z0-9_]{0,62}$/', $slug)) {
                continue;
            }

            $metadataPath = $directory . '/module.json';
            $controllerPath = $directory
                . '/controllers/'
                . $slug
                . '.php';

            if (!is_file($metadataPath) || !is_file($controllerPath)) {
                continue;
            }

            $raw = file_get_contents($metadataPath);
            $metadata = is_string($raw) ? json_decode($raw, true) : null;

            if (
                !is_array($metadata)
                || (string) ($metadata['module'] ?? '') !== $slug
            ) {
                continue;
            }

            $routes = $metadata['routes'] ?? [];

            if (
                !is_array($routes)
                || !in_array('index', $routes, true)
            ) {
                continue;
            }

            $title = trim(
                (string) (
                    $metadata['title']
                    ?? $metadata['name']
                    ?? $slug
                )
            );

            $modules[] = [
                'slug' => $slug,
                'title' => $title !== ''
                    ? $title
                    : ucwords(str_replace('_', ' ', $slug)),
                'description' => trim(
                    (string) ($metadata['description'] ?? '')
                ),
            ];
        }

        usort(
            $modules,
            static fn (array $left, array $right): int =>
                strcmp($left['slug'], $right['slug'])
        );

        return $modules;
    }

    /**
     * Discover published filesystem-backed Pages.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverPublishedPages(): array
    {
        require_once APPROOT . '/core/pages.php';

        $published = [];

        foreach (pages::all() as $page) {
            if (
                ($page['valid'] ?? false) !== true
                || (string) ($page['status'] ?? '') !== 'published'
            ) {
                continue;
            }

            $published[] = $page;
        }

        return $published;
    }

    /**
     * Return the configured site name.
     */
    private function siteName(): string
    {
        $site = $GLOBALS['SITE'] ?? [];
        $name = is_array($site)
            ? trim((string) ($site['name'] ?? ''))
            : '';

        return $name !== '' ? $name : 'Chaos MVC';
    }

    /**
     * Return the configured site description.
     */
    private function siteDescription(): string
    {
        $site = $GLOBALS['SITE'] ?? [];
        $description = is_array($site)
            ? trim((string) ($site['description'] ?? ''))
            : '';

        return $description !== ''
            ? $description
            : $this->siteName();
    }

}
/* [End AI:GPT-5.6 Sol] */
