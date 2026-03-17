<?php

declare(strict_types=1);

/**
 * Chaos MVC — Markdown Renderer
 * KISS engine for docs, changelogs and internal pages.
 */
 
 if (!class_exists('render_md')) {
class render_md
{
    public function markdown_file(string $path): void
    {
        if (!is_file($path)) {
            echo '<p>Markdown file not found.</p>';
            return;
        }

        $raw = (string) file_get_contents($path);
        echo $this->markdown($raw);
    }

    /* [AI:Gemini | 2026-03-16 19:21:00 UTC] 
     * patched the markdown function in your renderer by adding a callback to replace underscores with HTML entities inside backticks, preventing the italics regex from mangling technical function names like password_hash()
     [Human: Mei | 2026-03-16 19:25 UTC | APPROVE]
    */
    public function markdown(string $text): string
{
    // 1) Escape HTML so we don't execute anything
    $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    // 2) Fenced code blocks FIRST
    $html = preg_replace_callback(
        '/```(\w+)?\R([\s\S]*?)```/m',
        static function (array $matches): string {
            $lang = trim((string) ($matches[1] ?? ''));
            $code = (string) $matches[2];
            // Protection: Hide underscores in fenced blocks from italics/bold regex
            $code = str_replace('_', '&#95;', $code);
            $class = $lang !== '' ? ' class="code-' . htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') . '"' : '';
            return '<pre><code' . $class . '>' . $code . '</code></pre>';
        },
        $html
    );

    // 3) Horizontal Rule
    $html = preg_replace('/^(?:---|\*\*\*)$/m', '<hr>', $html);

    // 4) Blockquotes
    $html = preg_replace_callback(
        '/^(?:&gt;\s?.+\R?)+/m',
        static function (array $matches): string {
            $lines = preg_split('/\R/', trim($matches[0]));
            $output = '<blockquote>';
            foreach ($lines as $line) {
                $clean = preg_replace('/^\s*&gt;\s?/', '', $line);
                if ($clean !== '') $output .= $clean . '<br>';
            }
            return rtrim($output, '<br>') . '</blockquote>';
        },
        $html
    );

    // 5) Unordered lists
    $html = preg_replace_callback(
        '/^(?:\s*[-*+]\s+.+\R?)+/m',
        static function (array $matches): string {
            $lines = preg_split('/\R/', trim($matches[0]));
            $out = '<ul>';
            foreach ($lines as $line) {
                $clean = preg_replace('/^\s*[-*+]\s+/', '', $line);
                if ($clean !== '') $out .= '<li>' . $clean . '</li>';
            }
            return $out . '</ul>';
        },
        $html
    );

    // 6) Ordered lists
    $html = preg_replace_callback(
        '/^(?:\s*\d+\.\s+.+\R?)+/m',
        static function (array $matches): string {
            $lines = preg_split('/\R/', trim($matches[0]));
            $out = '<ol>';
            foreach ($lines as $line) {
                $clean = preg_replace('/^\s*\d+\.\s+/', '', $line);
                if ($clean !== '') $out .= '<li>' . $clean . '</li>';
            }
            return $out . '</ol>';
        },
        $html
    );

    // 7) PROTECTED Inline code: `code`
    // Convert underscores inside backticks to HTML entities to prevent Step 9/10 interference
    $html = preg_replace_callback('/`([^`]+)`/', function($matches) {
        $inner = str_replace('_', '&#95;', $matches[1]);
        return '<code>' . $inner . '</code>';
    }, $html);

    // 8) Headings
    $html = preg_replace('/^######\s*(.+)$/m', '<h6>$1</h6>', $html);
    $html = preg_replace('/^#####\s*(.+)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^####\s*(.+)$/m',  '<h4>$1</h4>', $html);
    $html = preg_replace('/^###\s*(.+)$/m',   '<h3>$1</h3>', $html);
    $html = preg_replace('/^##\s*(.+)$/m',    '<h2>$1</h2>', $html);
    $html = preg_replace('/^#\s*(.+)$/m',     '<h1>$1</h1>', $html);
    
    // 9) PATCH: Remove newlines after headings
    $html = preg_replace('/(<\/h[1-6]>)(\r\n|\n|\r)/', '$1', $html);

    // Italics (Now safe because underscores in code are entities)
    $html = preg_replace('/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/m', '<em>$1</em>', $html);
    $html = preg_replace('/(?<!_)_(?!\s)([^_\n]+?)(?<!\s)_(?!_)/m', '<em>$1</em>', $html);

    // 10) Bold: **text**
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);

    // 11) Small: ~~text~~
    $html = preg_replace('/~~(.+?)~~/s', '<small>$1</small>', $html);

    // 12) Links [text](url)
    $html = preg_replace(
        '/\[([^\]]+)\]\(([^)]+)\)/', 
        '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>', 
        $html);
        
    // 13 Hard Rule -- ** = <hr>
    $html = preg_replace('/^(?:---|\*\*\*|___)\s*$/m', '<hr>', $html);

    // 14) Newlines outside <pre>
    $parts = preg_split('/(<pre><code.*?<\/code><\/pre>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return nl2br($html);

    $out = '';
    foreach ($parts as $part) {
        if ($part === '') continue;
        $out .= (strpos($part, '<pre><code') === 0) ? $part : nl2br($part);
    }

    // 14) Adjusting line spacing with a wrapper
    return '<div style="line-height: 1.4;">' . $out . '</div>';
    }
    /* [END AI: Gemini] */
}
}
