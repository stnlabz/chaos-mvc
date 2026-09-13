<?php

/**
 * RSS Controller
 *
 * Generates rss.xml from published Core Posts.
 *
 * The Posts subsystem remains authoritative for feed content. Public feed
 * rows are loaded through posts_model::get_public_feed(), which restricts
 * results to published posts.
 *
 * LOCKED CORE FILE
 * Feed generation infrastructure
 * Modifications require explicit authorization.
 */

/* [AI:GPT-5.6 Sol | 2026-09-13 20:06:31 UTC] */
class rss extends controller
{
    public static $is_core = true;

    /**
     * Serve the RSS feed and refresh the cached rss.xml artifact.
     *
     * @return void
     */
    public function index(): void
    {
        $xml = $this->generate();

        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo $xml;
    }

    /**
     * Rebuild rss.xml and return the generated XML.
     *
     * @return string
     */
    public function generate(): string
    {
        $host = rtrim(URLROOT, '/');
        $site = $GLOBALS['SITE'] ?? [];
        $siteName = is_array($site)
            ? trim((string) ($site['name'] ?? ''))
            : '';
        $siteDescription = is_array($site)
            ? trim((string) ($site['description'] ?? ''))
            : '';

        if ($siteName === '') {
            $siteName = 'Chaos MVC';
        }

        if ($siteDescription === '') {
            $siteDescription = $siteName;
        }

        $model = $this->model('posts_model');
        $posts = $model->get_public_feed();

        $xmlEscape = static fn (string $value): string => htmlspecialchars(
            $value,
            ENT_XML1 | ENT_QUOTES,
            'UTF-8'
        );

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<rss version="2.0">' . PHP_EOL;
        $xml .= '  <channel>' . PHP_EOL;
        $xml .= '    <title>'
            . $xmlEscape($siteName)
            . '</title>'
            . PHP_EOL;
        $xml .= '    <link>'
            . $xmlEscape($host)
            . '</link>'
            . PHP_EOL;
        $xml .= '    <description>'
            . $xmlEscape($siteDescription)
            . '</description>'
            . PHP_EOL;
        $xml .= '    <lastBuildDate>'
            . $xmlEscape(gmdate(DATE_RSS))
            . '</lastBuildDate>'
            . PHP_EOL;
        $xml .= '    <generator>Chaos MVC</generator>' . PHP_EOL;
        $xml .= '    <atom:link xmlns:atom="http://www.w3.org/2005/Atom"'
            . ' href="'
            . $xmlEscape($host . '/rss.xml')
            . '" rel="self" type="application/rss+xml" />'
            . PHP_EOL;

        foreach ($posts as $post) {
            if (is_object($post)) {
                $post = (array) $post;
            }

            if (!is_array($post)) {
                continue;
            }

            $slug = trim((string) ($post['slug'] ?? ''));
            $title = trim((string) ($post['title'] ?? ''));

            if ($slug === '' || $title === '') {
                continue;
            }

            $url = $host . '/posts/' . rawurlencode($slug);
            $body = trim(strip_tags((string) ($post['body'] ?? '')));
            $createdAt = trim((string) ($post['created_at'] ?? ''));
            $timestamp = $createdAt !== ''
                ? strtotime($createdAt)
                : false;

            $xml .= '    <item>' . PHP_EOL;
            $xml .= '      <title>'
                . $xmlEscape($title)
                . '</title>'
                . PHP_EOL;
            $xml .= '      <link>'
                . $xmlEscape($url)
                . '</link>'
                . PHP_EOL;
            $xml .= '      <guid isPermaLink="true">'
                . $xmlEscape($url)
                . '</guid>'
                . PHP_EOL;

            if ($body !== '') {
                $xml .= '      <description>'
                    . $xmlEscape($body)
                    . '</description>'
                    . PHP_EOL;
            }

            if ($timestamp !== false) {
                $xml .= '      <pubDate>'
                    . $xmlEscape(gmdate(DATE_RSS, $timestamp))
                    . '</pubDate>'
                    . PHP_EOL;
            }

            $xml .= '    </item>' . PHP_EOL;
        }

        $xml .= '  </channel>' . PHP_EOL;
        $xml .= '</rss>' . PHP_EOL;

        if (
            file_put_contents(
                PUBROOT . '/rss.xml',
                $xml,
                LOCK_EX
            ) === false
        ) {
            throw new RuntimeException('Could not write rss.xml.');
        }

        return $xml;
    }
}
/* [End AI:GPT-5.6 Sol] */
