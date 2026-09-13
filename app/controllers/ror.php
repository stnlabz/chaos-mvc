<?php

/**
 * ROR Controller
 *
 * Generates ror.xml from the current ChAoS MVC public ownership model.
 * ROR remains a resource-discovery document and does not use the legacy
 * modules table as an authority.
 *
 * LOCKED CORE FILE
 * SEO generation infrastructure
 * Modifications require explicit authorization.
 *
 * [Human:Mei | 2026-03-11 02:58:00 UTC]
 */

/* [AI:GPT-5.6 Sol | 2026-09-13 20:06:31 UTC] */
class ror extends controller
{
    public static $is_core = true;

    /**
     * Rebuild ror.xml.
     */
    public function index(): bool
    {
        $host = rtrim(URLROOT, '/');
        $xmlEscape = static fn (string $value): string => htmlspecialchars(
            $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );

        $resources = [];

        foreach ($this->discoverCoreRoutes() as $route) {
            $slug = (string) $route['slug'];

            $resources[] = [
                'title' => (string) $route['title'],
                'description' => (string) $route['description'],
                'url' => $slug === 'home'
                    ? $host
                    : $host . '/' . rawurlencode($slug),
            ];
        }

        foreach ($this->discoverUserModules() as $module) {
            $resources[] = [
                'title' => (string) $module['title'],
                'description' => (string) $module['description'],
                'url' => $host
                    . '/'
                    . rawurlencode((string) $module['slug']),
            ];
        }

        foreach ($this->discoverPublishedPages() as $page) {
            $resources[] = [
                'title' => (string) $page['title'],
                'description' => trim(
                    (string) ($page['description'] ?? '')
                ),
                'url' => $host
                    . '/'
                    . rawurlencode((string) $page['slug']),
            ];
        }

        usort(
            $resources,
            static fn (array $left, array $right): int =>
                strcmp($left['url'], $right['url'])
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<rss version="2.0">' . PHP_EOL;
        $xml .= '  <channel>' . PHP_EOL;
        $xml .= '    <title>'
            . $xmlEscape($this->siteName() . ' Resources')
            . '</title>'
            . PHP_EOL;
        $xml .= '    <link>'
            . $xmlEscape($host)
            . '</link>'
            . PHP_EOL;
        $xml .= '    <description>'
            . $xmlEscape($this->siteDescription())
            . '</description>'
            . PHP_EOL;
        $xml .= '    <docs>'
            . $xmlEscape($host . '/ror.xml')
            . '</docs>'
            . PHP_EOL;

        foreach ($resources as $resource) {
            $xml .= '    <item>' . PHP_EOL;
            $xml .= '      <title>'
                . $xmlEscape((string) $resource['title'])
                . '</title>'
                . PHP_EOL;
            $xml .= '      <link>'
                . $xmlEscape((string) $resource['url'])
                . '</link>'
                . PHP_EOL;
            $xml .= '      <guid isPermaLink="true">'
                . $xmlEscape((string) $resource['url'])
                . '</guid>'
                . PHP_EOL;

            if ((string) $resource['description'] !== '') {
                $xml .= '      <description>'
                    . $xmlEscape((string) $resource['description'])
                    . '</description>'
                    . PHP_EOL;
            }

            $xml .= '    </item>' . PHP_EOL;
        }

        $xml .= '  </channel>' . PHP_EOL;
        $xml .= '</rss>' . PHP_EOL;

        if (
            file_put_contents(
                PUBROOT . '/ror.xml',
                $xml,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException('Could not write ror.xml.');
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
