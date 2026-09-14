<?php

/**
 * Site Resource Declaration Controller
 *
 * Generates /public/site.json from the current ChAoS MVC public ownership
 * model, then validates the candidate against the authoritative remote
 * STN-LABZ Site Resource Schema before publication.
 *
 * LOCKED CORE FILE
 * SEO generation infrastructure
 * Modifications require explicit authorization.
 */

/* [AI:GPT-5.6 Sol | 2026-09-14 18:38:35 UTC] */
class site_json extends controller
{
    public static $is_core = true;

    private const SCHEMA_URL =
        'https://schema.stn-labz.com/site/v1/schema.json';

    private const SCHEMA_VERSION = '1.0';

    private const MAX_SCHEMA_BYTES = 1048576;

    /**
     * Rebuild site.json.
     */
    public function index(): bool
    {
        $this->generate();
        return true;
    }

    /**
     * Generate, remotely validate, and publish site.json.
     *
     * @return string Published JSON document.
     */
    public function generate(): string
    {
        $candidate = [
            '$schema' => self::SCHEMA_URL,
            'version' => self::SCHEMA_VERSION,
            'site' => [
                'url' => rtrim(URLROOT, '/'),
                'name' => $this->siteName(),
                'description' => $this->siteDescription(),
                'language' => $this->siteLanguage(),
            ],
            'resources' => $this->discoverResources(),
        ];

        $schema = $this->fetchRemoteSchema();

        $errors = [];
        $this->validateAgainstSchema(
            $candidate,
            $schema,
            $schema,
            '$',
            $errors
        );

        if ($errors !== []) {
            throw new RuntimeException(
                'site.json schema validation failed: '
                . implode('; ', $errors)
            );
        }

        $json = json_encode(
            $candidate,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        );

        if (!is_string($json) || $json === '') {
            throw new RuntimeException('Could not encode site.json.');
        }

        $json .= PHP_EOL;

        $this->publishValidatedDocument(
            PUBROOT . '/site.json',
            $json
        );

        return $json;
    }

    /**
     * Discover all public resources deterministically.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverResources(): array
    {
        $resources = [];

        foreach ($this->discoverCoreRoutes() as $route) {
            $slug = (string) $route['slug'];
            $url = $slug === 'home'
                ? rtrim(URLROOT, '/')
                : rtrim(URLROOT, '/')
                    . '/'
                    . rawurlencode($slug);

            $resources[] = [
                'url' => $url,
                'type' => 'page',
                'title' => (string) $route['title'],
                'description' => $this->nonEmptyDescription(
                    (string) ($route['description'] ?? ''),
                    (string) $route['title']
                ),
                'language' => $this->siteLanguage(),
                'index' => true,
            ];
        }

        foreach ($this->discoverUserModules() as $module) {
            $resources[] = [
                'url' => rtrim(URLROOT, '/')
                    . '/'
                    . rawurlencode((string) $module['slug']),
                'type' => 'other',
                'title' => (string) $module['title'],
                'description' => $this->nonEmptyDescription(
                    (string) ($module['description'] ?? ''),
                    (string) $module['title']
                ),
                'language' => $this->siteLanguage(),
                'index' => true,
            ];
        }

        foreach ($this->discoverPublishedPages() as $page) {
            $resource = [
                'url' => rtrim(URLROOT, '/')
                    . '/'
                    . rawurlencode((string) $page['slug']),
                'type' => 'page',
                'title' => $this->pageTitle($page),
                'description' => $this->nonEmptyDescription(
                    trim((string) ($page['description'] ?? '')),
                    $this->pageTitle($page)
                ),
                'language' => $this->siteLanguage(),
                'index' => true,
            ];

            $published = $this->normalizeDate(
                (string) ($page['created_at'] ?? '')
            );
            $modified = $this->normalizeDate(
                (string) ($page['updated_at'] ?? '')
            );

            if ($published !== null) {
                $resource['published'] = $published;
            }

            if ($modified !== null) {
                $resource['modified'] = $modified;
            }

            $resources[] = $resource;
        }

        foreach ($this->discoverPublishedPosts() as $post) {
            $resources[] = $post;
        }

        $resources[] = [
            'url' => rtrim(URLROOT, '/') . '/rss.xml',
            'type' => 'feed',
            'title' => $this->siteName() . ' RSS',
            'description' => $this->siteName() . ' RSS',
            'language' => $this->siteLanguage(),
            'index' => true,
        ];

        $byUrl = [];

        foreach ($resources as $resource) {
            $url = (string) ($resource['url'] ?? '');

            if ($url === '') {
                continue;
            }

            $byUrl[$url] = $resource;
        }

        ksort($byUrl, SORT_STRING);

        return array_values($byUrl);
    }

    /**
     * Discover public Core controller routes.
     *
     * @return array<int, array{slug: string, title: string, description: string}>
     */
    private function discoverCoreRoutes(): array
    {
        $excluded = [
            'accounts',
            'admin',
            'auth',
            'error_handler',
            'health',
            'install',
            'llms',
            'media',
            'modules',
            'page',
            'ror',
            'rss',
            'sentinel',
            'site',
            'site_json',
            'sitemap',
            'theme_assets',
            'themes',
            'traffic',
            'updater',
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
     * Discover public userland modules without executing module PHP.
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

        usort(
            $published,
            static fn (array $left, array $right): int =>
                strcmp(
                    (string) ($left['slug'] ?? ''),
                    (string) ($right['slug'] ?? '')
                )
        );

        return $published;
    }

    /**
     * Discover published Posts using the established Posts model contract.
     *
     * @return array<int, array<string, mixed>>
     */
    private function discoverPublishedPosts(): array
    {
        $model = $this->model('posts_model');
        $posts = $model->get_public_feed();
        $resources = [];

        foreach ($posts as $post) {
            if (is_object($post)) {
                $post = (array) $post;
            }

            if (!is_array($post)) {
                continue;
            }

            $slug = trim((string) ($post['slug'] ?? ''));

            if ($slug === '') {
                continue;
            }

            $title = trim((string) ($post['title'] ?? ''));

            if ($title === '') {
                $title = $slug;
            }

            $body = trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    strip_tags((string) ($post['body'] ?? ''))
                ) ?? ''
            );

            $resource = [
                'url' => rtrim(URLROOT, '/')
                    . '/posts/'
                    . rawurlencode($slug),
                'type' => 'article',
                'title' => $title,
                'description' => $this->nonEmptyDescription(
                    $body,
                    $title
                ),
                'language' => $this->siteLanguage(),
                'index' => true,
            ];

            $published = $this->normalizeDate(
                (string) ($post['created_at'] ?? '')
            );
            $modified = $this->normalizeDate(
                (string) ($post['updated_at'] ?? '')
            );

            if ($published !== null) {
                $resource['published'] = $published;
            }

            if ($modified !== null) {
                $resource['modified'] = $modified;
            }

            $resources[] = $resource;
        }

        usort(
            $resources,
            static fn (array $left, array $right): int =>
                strcmp(
                    (string) ($left['url'] ?? ''),
                    (string) ($right['url'] ?? '')
                )
        );

        return $resources;
    }

    /**
     * Fetch the authoritative remote Site Resource Schema.
     *
     * No local schema copy is used.
     *
     * @return array<string, mixed>
     */
    private function fetchRemoteSchema(): array
    {
        $raw = $this->fetchRemoteText(self::SCHEMA_URL);

        if (
            strlen($raw) === 0
            || strlen($raw) > self::MAX_SCHEMA_BYTES
        ) {
            throw new RuntimeException(
                'Remote site schema has an invalid size.'
            );
        }

        $schema = json_decode($raw, true);

        if (!is_array($schema)) {
            throw new RuntimeException(
                'Remote site schema is not valid JSON.'
            );
        }

        if ((string) ($schema['$id'] ?? '') !== self::SCHEMA_URL) {
            throw new RuntimeException(
                'Remote site schema identity mismatch.'
            );
        }

        return $schema;
    }

    /**
     * Fetch one HTTPS resource without redirects.
     */
    private function fetchRemoteText(string $url): string
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle === false) {
                throw new RuntimeException(
                    'Could not initialize remote schema request.'
                );
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Chaos-MVC-Site-Schema/1.0',
            ]);

            $raw = curl_exec($handle);
            $status = (int) curl_getinfo(
                $handle,
                CURLINFO_RESPONSE_CODE
            );
            $error = curl_error($handle);

            curl_close($handle);

            if (!is_string($raw) || $status !== 200) {
                throw new RuntimeException(
                    'Remote site schema request failed'
                    . ($error !== '' ? ': ' . $error : '.')
                );
            }

            return $raw;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'follow_location' => 0,
                'ignore_errors' => true,
                'header' => "User-Agent: Chaos-MVC-Site-Schema/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $raw = @file_get_contents(
            $url,
            false,
            $context,
            0,
            self::MAX_SCHEMA_BYTES + 1
        );

        if (!is_string($raw) || $raw === '') {
            throw new RuntimeException(
                'Remote site schema request failed.'
            );
        }

        $statusLine = $http_response_header[0] ?? '';

        if (!preg_match('/\s200\s/', $statusLine)) {
            throw new RuntimeException(
                'Remote site schema returned a non-200 response.'
            );
        }

        return $raw;
    }

    /**
     * Validate a value against the JSON Schema keywords used by Site v1.
     *
     * @param mixed $value
     * @param array<string, mixed> $schema
     * @param array<string, mixed> $rootSchema
     * @param array<int, string> $errors
     */
    private function validateAgainstSchema(
        mixed $value,
        array $schema,
        array $rootSchema,
        string $path,
        array &$errors
    ): void {
        if (isset($schema['$ref'])) {
            $resolved = $this->resolveLocalReference(
                (string) $schema['$ref'],
                $rootSchema
            );

            $this->validateAgainstSchema(
                $value,
                $resolved,
                $rootSchema,
                $path,
                $errors
            );
            return;
        }

        if (array_key_exists('const', $schema)) {
            if ($value !== $schema['const']) {
                $errors[] = $path . ' does not match const.';
                return;
            }
        }

        if (isset($schema['enum']) && is_array($schema['enum'])) {
            if (!in_array($value, $schema['enum'], true)) {
                $errors[] = $path . ' is not an allowed enum value.';
                return;
            }
        }

        $type = isset($schema['type'])
            ? (string) $schema['type']
            : '';

        if ($type !== '' && !$this->matchesType($value, $type)) {
            $errors[] = $path . ' must be ' . $type . '.';
            return;
        }

        if ($type === 'object') {
            $this->validateObject(
                $value,
                $schema,
                $rootSchema,
                $path,
                $errors
            );
        } elseif ($type === 'array') {
            $this->validateArray(
                $value,
                $schema,
                $rootSchema,
                $path,
                $errors
            );
        } elseif ($type === 'string') {
            $this->validateString(
                (string) $value,
                $schema,
                $path,
                $errors
            );
        }
    }

    /**
     * @param mixed $value
     */
    private function validateObject(
        mixed $value,
        array $schema,
        array $rootSchema,
        string $path,
        array &$errors
    ): void {
        if (!is_array($value)) {
            return;
        }

        $required = $schema['required'] ?? [];

        if (is_array($required)) {
            foreach ($required as $property) {
                $property = (string) $property;

                if (!array_key_exists($property, $value)) {
                    $errors[] = $path
                        . ' is missing required property '
                        . $property
                        . '.';
                }
            }
        }

        $properties = $schema['properties'] ?? [];

        if (!is_array($properties)) {
            $properties = [];
        }

        if (($schema['additionalProperties'] ?? null) === false) {
            foreach (array_keys($value) as $property) {
                if (!array_key_exists($property, $properties)) {
                    $errors[] = $path
                        . ' contains unsupported property '
                        . (string) $property
                        . '.';
                }
            }
        }

        foreach ($properties as $property => $propertySchema) {
            if (
                !array_key_exists($property, $value)
                || !is_array($propertySchema)
            ) {
                continue;
            }

            $this->validateAgainstSchema(
                $value[$property],
                $propertySchema,
                $rootSchema,
                $path . '.' . $property,
                $errors
            );
        }
    }

    /**
     * @param mixed $value
     */
    private function validateArray(
        mixed $value,
        array $schema,
        array $rootSchema,
        string $path,
        array &$errors
    ): void {
        if (!is_array($value)) {
            return;
        }

        if (($schema['uniqueItems'] ?? false) === true) {
            $seen = [];

            foreach ($value as $item) {
                $key = json_encode(
                    $item,
                    JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                );

                if (is_string($key) && isset($seen[$key])) {
                    $errors[] = $path . ' contains duplicate items.';
                    break;
                }

                if (is_string($key)) {
                    $seen[$key] = true;
                }
            }
        }

        $itemSchema = $schema['items'] ?? null;

        if (!is_array($itemSchema)) {
            return;
        }

        foreach ($value as $index => $item) {
            $this->validateAgainstSchema(
                $item,
                $itemSchema,
                $rootSchema,
                $path . '[' . (string) $index . ']',
                $errors
            );
        }
    }

    private function validateString(
        string $value,
        array $schema,
        string $path,
        array &$errors
    ): void {
        if (isset($schema['minLength'])) {
            $minimum = (int) $schema['minLength'];
            $length = function_exists('mb_strlen')
                ? mb_strlen($value, 'UTF-8')
                : strlen($value);

            if ($length < $minimum) {
                $errors[] = $path . ' is shorter than minLength.';
            }
        }

        $format = isset($schema['format'])
            ? (string) $schema['format']
            : '';

        if (
            $format === 'uri'
            && filter_var($value, FILTER_VALIDATE_URL) === false
        ) {
            $errors[] = $path . ' is not a valid URI.';
        }

        if ($format === 'date' && !$this->isValidDate($value)) {
            $errors[] = $path . ' is not a valid date.';
        }
    }

    /**
     * @param mixed $value
     */
    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'object' => is_array($value) && !$this->isList($value),
            'array' => is_array($value) && $this->isList($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'null' => $value === null,
            default => false,
        };
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        $expected = 0;

        foreach ($value as $key => $_item) {
            if ($key !== $expected) {
                return false;
            }

            $expected++;
        }

        return true;
    }

    /**
     * Resolve an internal JSON Pointer reference.
     *
     * @return array<string, mixed>
     */
    private function resolveLocalReference(
        string $reference,
        array $rootSchema
    ): array {
        if (!str_starts_with($reference, '#/')) {
            throw new RuntimeException(
                'Unsupported schema reference: ' . $reference
            );
        }

        $value = $rootSchema;
        $segments = explode('/', substr($reference, 2));

        foreach ($segments as $segment) {
            $segment = str_replace(
                ['~1', '~0'],
                ['/', '~'],
                $segment
            );

            if (
                !is_array($value)
                || !array_key_exists($segment, $value)
            ) {
                throw new RuntimeException(
                    'Unresolvable schema reference: ' . $reference
                );
            }

            $value = $value[$segment];
        }

        if (!is_array($value)) {
            throw new RuntimeException(
                'Invalid schema reference target: ' . $reference
            );
        }

        return $value;
    }

    private function publishValidatedDocument(
        string $target,
        string $contents
    ): void {
        $temporary = tempnam(PUBROOT, 'site-json-');

        if (!is_string($temporary)) {
            throw new RuntimeException(
                'Could not create temporary site.json file.'
            );
        }

        try {
            if (
                file_put_contents(
                    $temporary,
                    $contents,
                    LOCK_EX
                ) === false
            ) {
                throw new RuntimeException(
                    'Could not write temporary site.json.'
                );
            }

            $backup = $target . '.previous';
            $hadTarget = is_file($target);

            if ($hadTarget) {
                @unlink($backup);

                if (!rename($target, $backup)) {
                    throw new RuntimeException(
                        'Could not preserve existing site.json.'
                    );
                }
            }

            if (!rename($temporary, $target)) {
                if ($hadTarget && is_file($backup)) {
                    @rename($backup, $target);
                }

                throw new RuntimeException(
                    'Could not publish validated site.json.'
                );
            }

            if ($hadTarget && is_file($backup)) {
                @unlink($backup);
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function siteName(): string
    {
        $site = $GLOBALS['SITE'] ?? [];
        $name = is_array($site)
            ? trim((string) ($site['name'] ?? ''))
            : '';

        return $name !== '' ? $name : 'Chaos MVC';
    }

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

    private function siteLanguage(): string
    {
        $site = $GLOBALS['SITE'] ?? [];

        if (is_array($site)) {
            $language = trim(
                (string) (
                    $site['language']
                    ?? $site['lang']
                    ?? ''
                )
            );

            if ($language !== '') {
                return $language;
            }
        }

        return 'en';
    }

    /**
     * @param array<string, mixed> $page
     */
    private function pageTitle(array $page): string
    {
        $title = trim((string) ($page['title'] ?? ''));

        if ($title !== '') {
            return $title;
        }

        return (string) ($page['slug'] ?? 'Page');
    }

    private function nonEmptyDescription(
        string $description,
        string $fallback
    ): string {
        $description = trim($description);

        return $description !== ''
            ? $description
            : trim($fallback);
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }

        $date = substr($value, 0, 10);

        return $this->isValidDate($date)
            ? $date
            : null;
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map(
            'intval',
            explode('-', $value)
        );

        return checkdate($month, $day, $year);
    }
}
/* [End AI:GPT-5.6 Sol] */
