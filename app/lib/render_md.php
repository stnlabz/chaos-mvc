<?php

declare(strict_types=1);

/**
 * Chaos MVC — Markdown Renderer
 * KISS engine for docs, changelogs and internal pages.
 */

if (!class_exists('render_md')) {
    class render_md
    {
        /**
         * Render a Markdown file.
         *
         * @param string $path Markdown file path.
         *
         * @return void
         */
        public function markdown_file(string $path): void
        {
            if (!is_file($path)) {
                echo '<p>Markdown file not found.</p>';
                return;
            }

            $raw = (string) file_get_contents($path);

            echo $this->markdown($raw);
        }

        /*
         * [AI:Gemini | 2026-03-16 19:21:00 UTC]
         * Patched the markdown function in your renderer by adding a callback
         * to replace underscores with HTML entities inside backticks,
         * preventing the italics regex from mangling technical function names
         * like password_hash().
         *
         * [Human: Mei | 2026-03-16 19:25 UTC | APPROVE]
         */

        /**
         * Convert Markdown into safe HTML.
         *
         * @param string $text Markdown source.
         *
         * @return string
         */
        public function markdown(string $text): string
        {
            // 1) Escape HTML so we don't execute anything.
            $html = htmlspecialchars(
                $text,
                ENT_QUOTES,
                'UTF-8'
            );

            // 2) Fenced code blocks FIRST.
            $html = preg_replace_callback(
                '/```(\w+)?\R([\s\S]*?)```/m',
                static function (array $matches): string {
                    $lang = trim(
                        (string) ($matches[1] ?? '')
                    );

                    $code = (string) $matches[2];

                    /*
                     * Protection: hide underscores in fenced blocks from
                     * italics/bold regex.
                     */
                    $code = str_replace(
                        '_',
                        '&#95;',
                        $code
                    );

                    $class = '';

                    if ($lang !== '') {
                        $class = ' class="code-'
                            . htmlspecialchars(
                                $lang,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            . '"';
                    }

                    return '<pre><code'
                        . $class
                        . '>'
                        . $code
                        . '</code></pre>';
                },
                $html
            );

            /*
             * [Human: Mei | 2026-03-16 21:16:00 UTC]
             * Adding Horizontal Rule --- or *** -> <hr>
             */

            // 3) Horizontal Rule.
            $html = preg_replace(
                '/^(?:---|\*\*\*)$/m',
                '<hr>',
                $html
            );

            // 4) Blockquotes.
            $html = preg_replace_callback(
                '/^(?:&gt;\s?.+\R?)+/m',
                static function (array $matches): string {
                    $lines = preg_split(
                        '/\R/',
                        trim($matches[0])
                    );

                    if ($lines === false) {
                        return '';
                    }

                    $output = '<blockquote>';

                    foreach ($lines as $line) {
                        $clean = preg_replace(
                            '/^\s*&gt;\s?/',
                            '',
                            $line
                        );

                        if ($clean !== null && $clean !== '') {
                            $output .= $clean . '<br>';
                        }
                    }

                    return rtrim(
                        $output,
                        '<br>'
                    ) . '</blockquote>';
                },
                $html
            );

            /* [AI:GPT-5.6 Sol | 2026-09-13 00:12:00 UTC] */

            // 5) Unordered lists, including nested lists.
            $html = preg_replace_callback(
                '/^(?:[ \t]*[-*+]\s+.+(?:\R|$))+/m',
                function (array $matches): string {
                    return $this->renderNestedList(
                        (string) $matches[0],
                        'ul',
                        '/^([ \t]*)[-*+]\s+(.+)$/'
                    );
                },
                $html
            );

            // 6) Ordered lists, including nested lists.
            $html = preg_replace_callback(
                '/^(?:[ \t]*\d+\.\s+.+(?:\R|$))+/m',
                function (array $matches): string {
                    return $this->renderNestedList(
                        (string) $matches[0],
                        'ol',
                        '/^([ \t]*)\d+\.\s+(.+)$/'
                    );
                },
                $html
            );

            /* [End AI:GPT-5.6 Sol] */

            // 7) PROTECTED Inline code: `code`
            $html = preg_replace_callback(
                '/`([^`]+)`/',
                static function (array $matches): string {
                    $inner = str_replace(
                        '_',
                        '&#95;',
                        $matches[1]
                    );

                    return '<code>'
                        . $inner
                        . '</code>';
                },
                $html
            );

            // 8) Headings.
            $html = preg_replace(
                '/^######\s*(.+)$/m',
                '<h6>$1</h6>',
                $html
            );

            $html = preg_replace(
                '/^#####\s*(.+)$/m',
                '<h5>$1</h5>',
                $html
            );

            $html = preg_replace(
                '/^####\s*(.+)$/m',
                '<h4>$1</h4>',
                $html
            );

            $html = preg_replace(
                '/^###\s*(.+)$/m',
                '<h3>$1</h3>',
                $html
            );

            $html = preg_replace(
                '/^##\s*(.+)$/m',
                '<h2>$1</h2>',
                $html
            );

            $html = preg_replace(
                '/^#\s*(.+)$/m',
                '<h1>$1</h1>',
                $html
            );

            // 9) PATCH: Remove newlines after headings.
            $html = preg_replace(
                '/(<\/h[1-6]>)(\r\n|\n|\r)/',
                '$1',
                $html
            );

            /*
             * Italics.
             * Safe because underscores in code are converted to entities.
             */
            $html = preg_replace(
                '/(?<!\*)\*(?!\s)([^*\n]+?)(?<!\s)\*(?!\*)/m',
                '<em>$1</em>',
                $html
            );

            $html = preg_replace(
                '/(?<!_)_(?!\s)([^_\n]+?)(?<!\s)_(?!_)/m',
                '<em>$1</em>',
                $html
            );

            // 10) Bold: **text**
            $html = preg_replace(
                '/\*\*(.+?)\*\*/s',
                '<strong>$1</strong>',
                $html
            );

            // 11) Small: ~~text~~
            $html = preg_replace(
                '/~~(.+?)~~/s',
                '<small>$1</small>',
                $html
            );

            /* [AI:GPT-5.6 Sol | 2026-09-13 00:12:00 UTC] */

            /*
             * 12) Controlled text colors.
             *
             * Markdown:
             *
             * {color:red}Red text{/color}
             * {color:huntergreen}Hunter Green text{/color}
             *
             * Only explicitly approved color names are accepted.
             * Arbitrary CSS values are not accepted from Markdown.
             */
            $html = preg_replace_callback(
                '/\{color:([a-z]+)\}(.+?)\{\/color\}/is',
                static function (array $matches): string {
                    $requestedColor = strtolower(
                        trim((string) $matches[1])
                    );

                    $content = (string) $matches[2];

                    /*
                     * Markdown-facing names remain simple human-readable
                     * color names. CSS values are controlled here.
                     */
                    $colors = [
                        'grey' => 'grey',
                        'white' => 'white',
                        'huntergreen' => '#355e3b',
                        'purple' => 'purple',
                        'red' => 'red',
                        'black' => 'black',
                        'brown' => 'brown',
                        'blue' => 'blue',
                        'pink' => 'pink',
                    ];

                    if (!isset($colors[$requestedColor])) {
                        return $matches[0];
                    }

                    return '<span style="color: '
                        . $colors[$requestedColor]
                        . ';">'
                        . $content
                        . '</span>';
                },
                $html
            );

            /* [End AI:GPT-5.6 Sol] */

            // 13) Links [text](url), limited to safe web and email schemes.
            $html = preg_replace_callback(
                '/\[([^\]]+)\]\(([^)]+)\)/',
                static function (array $matches): string {
                    $url = html_entity_decode(
                        $matches[2],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $scheme = strtolower(
                        (string) parse_url(
                            $url,
                            PHP_URL_SCHEME
                        )
                    );

                    $isRelative = str_starts_with(
                        $url,
                        '/'
                    ) && !str_starts_with(
                        $url,
                        '//'
                    );

                    if (
                        !$isRelative
                        && !in_array(
                            $scheme,
                            [
                                'http',
                                'https',
                                'mailto',
                            ],
                            true
                        )
                    ) {
                        return $matches[1];
                    }

                    return '<a href="'
                        . htmlspecialchars(
                            $url,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '" target="_blank" rel="noopener noreferrer">'
                        . $matches[1]
                        . '</a>';
                },
                $html
            );

            // 14) Hard Rule.
            $html = preg_replace(
                '/^(?:---|\*\*\*|___)\s*$/m',
                '<hr>',
                $html
            );

            // 15) Newlines outside <pre>.
            $parts = preg_split(
                '/(<pre><code.*?<\/code><\/pre>)/s',
                $html,
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            );

            if ($parts === false) {
                return nl2br($html);
            }

            $out = '';

            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }

                if (strpos($part, '<pre><code') === 0) {
                    $out .= $part;
                    continue;
                }

                $out .= nl2br($part);
            }

            // 16) Adjust line spacing with a wrapper.
            return '<div style="line-height: 1.4;">'
                . $out
                . '</div>';
        }

        /**
		 * [AI:GPT-5.6 Sol | 2026-09-13 00:12:00 UTC]
		 * [HUMAN: PM | APPROVE | 2026-09-13 00:15:00 UTC]
		*/

        /**
         * Render a Markdown list block while preserving indentation nesting.
         *
         * Indentation widths are mapped relative to the indentation actually
         * present in the block. This supports conventional four-space nesting
         * while remaining tolerant of existing Markdown using other widths.
         *
         * @param string $block         Raw escaped Markdown list block.
         * @param string $tag           HTML list element, ul or ol.
         * @param string $markerPattern Regex used to extract indent and text.
         *
         * @return string
         */
        private function renderNestedList(
            string $block,
            string $tag,
            string $markerPattern
        ): string {
            $lines = preg_split(
                '/\R/',
                rtrim($block)
            );

            if ($lines === false) {
                return '';
            }

            $items = [];
            $indentWidths = [];

            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }

                if (
                    preg_match(
                        $markerPattern,
                        $line,
                        $matches
                    ) !== 1
                ) {
                    continue;
                }

                $indent = str_replace(
                    "\t",
                    '    ',
                    (string) ($matches[1] ?? '')
                );

                $width = strlen($indent);
                $content = (string) ($matches[2] ?? '');

                $items[] = [
                    'indent' => $width,
                    'content' => $content,
                ];

                $indentWidths[$width] = true;
            }

            if ($items === []) {
                return '';
            }

            $levels = array_keys($indentWidths);

            sort(
                $levels,
                SORT_NUMERIC
            );

            $levelMap = [];

            foreach ($levels as $level => $width) {
                $levelMap[(int) $width] = $level;
            }

            foreach ($items as &$item) {
                $item['level'] = $levelMap[
                    (int) $item['indent']
                ] ?? 0;
            }

            unset($item);

            $position = 0;

            return $this->renderNestedListLevel(
                $items,
                $position,
                0,
                $tag
            );
        }

        /**
         * Render one nesting level from a normalized Markdown list.
         *
         * @param array<int, array{
         *     indent:int,
         *     content:string,
         *     level:int
         * }> $items
         * @param int    $position Current item position.
         * @param int    $level    Nesting level being rendered.
         * @param string $tag      HTML list element, ul or ol.
         *
         * @return string
         */
        private function renderNestedListLevel(
            array $items,
            int &$position,
            int $level,
            string $tag
        ): string {
            $out = '<' . $tag . '>';
            $count = count($items);

            while ($position < $count) {
                $itemLevel = (int) $items[$position]['level'];

                if ($itemLevel < $level) {
                    break;
                }

                if ($itemLevel > $level) {
                    break;
                }

                $out .= '<li>'
                    . $items[$position]['content'];

                $position++;

                while (
                    $position < $count
                    && (int) $items[$position]['level'] > $level
                ) {
                    $childLevel = (int) $items[$position]['level'];

                    $out .= $this->renderNestedListLevel(
                        $items,
                        $position,
                        $childLevel,
                        $tag
                    );
                }

                $out .= '</li>';
            }

            $out .= '</' . $tag . '>';

            return $out;
        }

        /* [End AI:GPT-5.6 Sol] */
    }
}