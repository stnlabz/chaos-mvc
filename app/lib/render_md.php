<?php

declare(strict_types=1);

/**
 * ChAoS MVC — Markdown Renderer
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

        /**
         * Convert Markdown into safe HTML.
         *
         * Supported features include:
         * - headings
         * - heading anchors
         * - bold
         * - italics
         * - strikethrough
         * - small text
         * - controlled named colors
         * - inline code
         * - variable-length fenced code blocks
         * - links
         * - automatic links
         * - blockquotes
         * - GitHub-style alerts
         * - horizontal rules
         * - nested unordered lists
         * - nested ordered lists
         * - task lists
         * - tables
         * - definition lists
         * - footnotes
         * - escaped Markdown characters
         *
         * @param string $text Markdown source.
         *
         * @return string
         */
        public function markdown(string $text): string
        {
            /* [AI:GPT-5.6 Sol | 2026-09-13 00:20:00 UTC] */

            $text = str_replace(
                [
                    "\r\n",
                    "\r",
                ],
                "\n",
                $text
            );

            $codeBlocks = [];
            $inlineCode = [];
            $escapedCharacters = [];
            $footnotes = [];

            /*
             * Protect fenced code blocks before any Markdown processing.
             *
             * Fences may contain three or more backticks.
             *
             * This allows a longer outer fence to document shorter fences:
             *
             *     ````
             *     ```php
             *     echo 'Example';
             *     ```
             *     ````
             */
            $text = preg_replace_callback(
                '/^(`{3,})([A-Za-z0-9_-]*)[ \t]*\n'
                . '([\s\S]*?)'
                . '^\1[ \t]*$/m',
                static function (array $matches) use (&$codeBlocks): string {
                    $index = count($codeBlocks);

                    $language = trim(
                        (string) ($matches[2] ?? '')
                    );

                    $code = htmlspecialchars(
                        (string) ($matches[3] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    $class = '';

                    if ($language !== '') {
                        $class = ' class="code-'
                            . htmlspecialchars(
                                $language,
                                ENT_QUOTES,
                                'UTF-8'
                            )
                            . '"';
                    }

                    $codeBlocks[$index] = '<pre><code'
                        . $class
                        . '>'
                        . $code
                        . '</code></pre>';

                    /*
                     * Placeholder intentionally contains only alphanumeric
                     * characters so later Markdown rules cannot alter it.
                     */
                    return 'CHAOSCODEBLOCKTOKEN'
                        . $index
                        . 'ENDTOKEN';
                },
                $text
            );

            /*
             * Protect inline code before emphasis and other inline parsing.
             */
            $text = preg_replace_callback(
                '/`([^`\n]+)`/',
                static function (array $matches) use (&$inlineCode): string {
                    $index = count($inlineCode);

                    $inlineCode[$index] = '<code>'
                        . htmlspecialchars(
                            (string) $matches[1],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '</code>';

                    /*
                     * Alphanumeric placeholder prevents italic and other
                     * Markdown rules from corrupting protected inline code.
                     */
                    return 'CHAOSINLINECODETOKEN'
                        . $index
                        . 'ENDTOKEN';
                },
                $text
            );

            /*
             * Protect explicitly escaped Markdown characters.
             *
             * Examples:
             *
             * \*literal asterisk\*
             * \# not a heading
             * \- not a list
             */
            $text = preg_replace_callback(
                '/\\\\([\\\\`*_{}\[\]()#+\-.!>|~])/',
                static function (array $matches) use (&$escapedCharacters): string {
                    $index = count($escapedCharacters);

                    $escapedCharacters[$index] = htmlspecialchars(
                        (string) $matches[1],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    return 'CHAOSESCAPETOKEN'
                        . $index
                        . 'ENDTOKEN';
                },
                $text
            );

            /*
             * Extract footnote definitions.
             *
             * Markdown:
             *
             * [^1]: Footnote content.
             */
            $text = preg_replace_callback(
                '/^\[\^([A-Za-z0-9_-]+)\]:\s*(.+)$/m',
                static function (array $matches) use (&$footnotes): string {
                    $id = strtolower(
                        trim((string) $matches[1])
                    );

                    $footnotes[$id] = trim(
                        (string) $matches[2]
                    );

                    return '';
                },
                $text
            );

            /*
             * Escape all remaining source HTML.
             */
            $html = htmlspecialchars(
                $text,
                ENT_QUOTES,
                'UTF-8'
            );

            /*
             * GitHub-style alerts.
             *
             * > [!NOTE]
             * > Information here.
             *
             * Supported:
             * NOTE
             * TIP
             * IMPORTANT
             * WARNING
             * CAUTION
             */
            $html = preg_replace_callback(
                '/^&gt;\s*\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*\n'
                . '((?:&gt;.*(?:\n|$))*)/mi',
                static function (array $matches): string {
                    $type = strtolower(
                        (string) $matches[1]
                    );

                    $body = preg_replace(
                        '/^&gt;\s?/m',
                        '',
                        trim((string) $matches[2])
                    );

                    $title = ucfirst($type);

                    return '<div class="markdown-alert markdown-alert-'
                        . $type
                        . '">'
                        . '<p class="markdown-alert-title">'
                        . $title
                        . '</p>'
                        . '<div class="markdown-alert-body">'
                        . nl2br(
                            (string) $body
                        )
                        . '</div>'
                        . '</div>';
                },
                $html
            );

            /*
             * Tables.
             *
             * | Column | Column |
             * | --- | --- |
             * | Value | Value |
             */
            $html = preg_replace_callback(
                '/^(\|?.+\|.+\|?)\n'
                . '(\|?\s*:?-{3,}:?\s*(?:\|\s*:?-{3,}:?\s*)+\|?)'
                . '((?:\n\|?.+\|.+\|?)+)/m',
                function (array $matches): string {
                    return $this->renderTable(
                        (string) $matches[1],
                        (string) $matches[2],
                        (string) $matches[3]
                    );
                },
                $html
            );

            /*
             * Definition lists.
             *
             * Term
             * : Definition
             */
            $html = preg_replace_callback(
                '/^(?![#>\-*+\d])([^\n]+)\n'
                . '((?::\s+.+(?:\n|$))+)/m',
                static function (array $matches): string {
                    $term = trim(
                        (string) $matches[1]
                    );

                    $definitionLines = preg_split(
                        '/\n/',
                        trim((string) $matches[2])
                    );

                    if ($definitionLines === false) {
                        return $matches[0];
                    }

                    $out = '<dl>';
                    $out .= '<dt>'
                        . $term
                        . '</dt>';

                    foreach ($definitionLines as $line) {
                        $definition = preg_replace(
                            '/^:\s+/',
                            '',
                            $line
                        );

                        if (
                            $definition !== null
                            && trim($definition) !== ''
                        ) {
                            $out .= '<dd>'
                                . trim($definition)
                                . '</dd>';
                        }
                    }

                    $out .= '</dl>';

                    return $out;
                },
                $html
            );

            /*
             * Nested unordered lists.
             */
            $html = preg_replace_callback(
                '/^(?:[ \t]*[-*+]\s+.+(?:\n|$))+/m',
                function (array $matches): string {
                    return $this->renderNestedList(
                        (string) $matches[0],
                        'ul',
                        '/^([ \t]*)[-*+]\s+(.+)$/'
                    );
                },
                $html
            );

            /*
             * Nested ordered lists.
             */
            $html = preg_replace_callback(
                '/^(?:[ \t]*\d+\.\s+.+(?:\n|$))+/m',
                function (array $matches): string {
                    return $this->renderNestedList(
                        (string) $matches[0],
                        'ol',
                        '/^([ \t]*)\d+\.\s+(.+)$/'
                    );
                },
                $html
            );

            /*
             * Normal blockquotes.
             */
            $html = preg_replace_callback(
                '/^(?:&gt;\s?.+(?:\n|$))+/m',
                static function (array $matches): string {
                    $lines = preg_split(
                        '/\n/',
                        trim((string) $matches[0])
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

                        if (
                            $clean !== null
                            && $clean !== ''
                        ) {
                            $output .= $clean
                                . '<br>';
                        }
                    }

                    return rtrim(
                        $output,
                        '<br>'
                    ) . '</blockquote>';
                },
                $html
            );

            /*
             * Horizontal rules.
             */
            $html = preg_replace(
                '/^(?:---|\*\*\*|___)\s*$/m',
                '<hr>',
                $html
            );

            /*
             * Headings with URL-friendly anchor IDs.
             */
            $html = preg_replace_callback(
                '/^(#{1,6})\s+(.+)$/m',
                function (array $matches): string {
                    $level = strlen(
                        (string) $matches[1]
                    );

                    $content = trim(
                        (string) $matches[2]
                    );

                    $id = $this->slugifyHeading(
                        $content
                    );

                    return '<h'
                        . $level
                        . ' id="'
                        . $id
                        . '">'
                        . $content
                        . '</h'
                        . $level
                        . '>';
                },
                $html
            );

            /*
             * Remove a newline immediately following a heading.
             */
            $html = preg_replace(
                '/(<\/h[1-6]>)\n/',
                '$1',
                $html
            );

            /*
             * Explicit Markdown links.
             */
            $html = preg_replace_callback(
                '/\[([^\]]+)\]\(([^)]+)\)/',
                static function (array $matches): string {
                    $label = (string) $matches[1];

                    $url = html_entity_decode(
                        (string) $matches[2],
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    if (!self::isSafeUrl($url)) {
                        return $label;
                    }

                    return '<a href="'
                        . htmlspecialchars(
                            $url,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '" target="_blank" rel="noopener noreferrer">'
                        . $label
                        . '</a>';
                },
                $html
            );

            /*
             * Automatic HTTP and HTTPS links.
             *
             * Example:
             *
             * https://chaos-mvc.org
             */
            $html = preg_replace_callback(
                '~(?<!["\'=])(https?://[^\s<]+)~i',
                static function (array $matches): string {
                    $original = (string) $matches[1];

                    $url = html_entity_decode(
                        rtrim(
                            $original,
                            '.,;:!?)]'
                        ),
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    if (!self::isSafeUrl($url)) {
                        return $matches[0];
                    }

                    $suffixLength = strlen($original)
                        - strlen($url);

                    $suffix = $suffixLength > 0
                        ? substr(
                            $original,
                            -$suffixLength
                        )
                        : '';

                    return '<a href="'
                        . htmlspecialchars(
                            $url,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '" target="_blank" rel="noopener noreferrer">'
                        . htmlspecialchars(
                            $url,
                            ENT_QUOTES,
                            'UTF-8'
                        )
                        . '</a>'
                        . $suffix;
                },
                $html
            );

            /*
             * Bold.
             */
            $html = preg_replace(
                '/\*\*(.+?)\*\*/s',
                '<strong>$1</strong>',
                $html
            );

            /*
             * Italics.
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

            /*
             * GitHub-style strikethrough.
             *
             * ~~deprecated text~~
             */
            $html = preg_replace(
                '/~~(.+?)~~/s',
                '<del>$1</del>',
                $html
            );

            /*
             * Controlled small-text extension.
             *
             * {small}small text{/small}
             */
            $html = preg_replace(
                '/\{small\}(.+?)\{\/small\}/is',
                '<small>$1</small>',
                $html
            );

            /*
             * Controlled named text colors.
             *
             * {color:red}Red{/color}
             */
            $html = preg_replace_callback(
                '/\{color:([a-z]+)\}(.+?)\{\/color\}/is',
                static function (array $matches): string {
                    $requestedColor = strtolower(
                        trim((string) $matches[1])
                    );

                    $content = (string) $matches[2];

                    $colors = [
                        'grey' => 'grey',
                        'gray' => 'gray',
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

            /*
             * Footnote references.
             *
             * Example:
             *
             * Some statement.[^1]
             */
            $html = preg_replace_callback(
                '/\[\^([A-Za-z0-9_-]+)\]/',
                static function (array $matches) use ($footnotes): string {
                    $id = strtolower(
                        trim((string) $matches[1])
                    );

                    if (!isset($footnotes[$id])) {
                        return $matches[0];
                    }

                    $safeId = htmlspecialchars(
                        $id,
                        ENT_QUOTES,
                        'UTF-8'
                    );

                    return '<sup class="footnote-ref" id="fnref-'
                        . $safeId
                        . '">'
                        . '<a href="#fn-'
                        . $safeId
                        . '">'
                        . $safeId
                        . '</a>'
                        . '</sup>';
                },
                $html
            );

            /*
             * Restore escaped Markdown characters.
             *
             * Tokens use no Markdown-significant punctuation and therefore
             * survive all preceding parser stages unchanged.
             */
            foreach ($escapedCharacters as $index => $character) {
                $html = str_replace(
                    'CHAOSESCAPETOKEN'
                    . $index
                    . 'ENDTOKEN',
                    $character,
                    $html
                );
            }

            /*
             * Restore protected inline code.
             */
            foreach ($inlineCode as $index => $code) {
                $html = str_replace(
                    'CHAOSINLINECODETOKEN'
                    . $index
                    . 'ENDTOKEN',
                    $code,
                    $html
                );
            }

            /*
             * Restore protected fenced code blocks.
             */
            foreach ($codeBlocks as $index => $code) {
                $html = str_replace(
                    'CHAOSCODEBLOCKTOKEN'
                    . $index
                    . 'ENDTOKEN',
                    $code,
                    $html
                );
            }

            /*
             * Convert remaining line breaks outside fenced code blocks.
             */
            $parts = preg_split(
                '/(<pre><code.*?<\/code><\/pre>)/s',
                $html,
                -1,
                PREG_SPLIT_DELIM_CAPTURE
            );

            if ($parts === false) {
                $out = nl2br($html);
            } else {
                $out = '';

                foreach ($parts as $part) {
                    if ($part === '') {
                        continue;
                    }

                    if (
                        strpos(
                            $part,
                            '<pre><code'
                        ) === 0
                    ) {
                        $out .= $part;
                        continue;
                    }

                    $out .= nl2br($part);
                }
            }

            /*
             * Append footnotes.
             */
            if ($footnotes !== []) {
                $out .= $this->renderFootnotes(
                    $footnotes
                );
            }

            return '<div class="markdown-body" style="line-height: 1.4;">'
                . $out
                . '</div>';

            /* [End AI:GPT-5.6 Sol] */
        }

        /**
         * Render a Markdown table.
         *
         * @param string $headerLine    Header row.
         * @param string $separatorLine Alignment row.
         * @param string $bodyBlock     Table body rows.
         *
         * @return string
         */
        private function renderTable(
            string $headerLine,
            string $separatorLine,
            string $bodyBlock
        ): string {
            $headers = $this->splitTableRow(
                $headerLine
            );

            $separators = $this->splitTableRow(
                $separatorLine
            );

            if (
                $headers === []
                || count($headers) !== count($separators)
            ) {
                return $headerLine
                    . "\n"
                    . $separatorLine
                    . $bodyBlock;
            }

            $alignments = [];

            foreach ($separators as $separator) {
                $separator = trim($separator);

                $left = str_starts_with(
                    $separator,
                    ':'
                );

                $right = str_ends_with(
                    $separator,
                    ':'
                );

                if ($left && $right) {
                    $alignments[] = 'center';
                    continue;
                }

                if ($right) {
                    $alignments[] = 'right';
                    continue;
                }

                if ($left) {
                    $alignments[] = 'left';
                    continue;
                }

                $alignments[] = '';
            }

            $bodyLines = preg_split(
                '/\n/',
                trim($bodyBlock)
            );

            if ($bodyLines === false) {
                $bodyLines = [];
            }

            $out = '<table class="markdown-table">';
            $out .= '<thead><tr>';

            foreach ($headers as $index => $header) {
                $align = $alignments[$index] ?? '';

                $attribute = $align !== ''
                    ? ' style="text-align: '
                        . $align
                        . ';"'
                    : '';

                $out .= '<th'
                    . $attribute
                    . '>'
                    . trim($header)
                    . '</th>';
            }

            $out .= '</tr></thead>';
            $out .= '<tbody>';

            foreach ($bodyLines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $cells = $this->splitTableRow(
                    $line
                );

                $out .= '<tr>';

                foreach ($headers as $index => $unused) {
                    $cell = trim(
                        (string) ($cells[$index] ?? '')
                    );

                    $align = $alignments[$index] ?? '';

                    $attribute = $align !== ''
                        ? ' style="text-align: '
                            . $align
                            . ';"'
                        : '';

                    $out .= '<td'
                        . $attribute
                        . '>'
                        . $cell
                        . '</td>';
                }

                $out .= '</tr>';
            }

            $out .= '</tbody>';
            $out .= '</table>';

            return $out;
        }

        /**
         * Split a Markdown table row into individual cells.
         *
         * @param string $line Table row.
         *
         * @return array<int, string>
         */
        private function splitTableRow(
            string $line
        ): array {
            $line = trim($line);

            if (str_starts_with($line, '|')) {
                $line = substr(
                    $line,
                    1
                );
            }

            if (str_ends_with($line, '|')) {
                $line = substr(
                    $line,
                    0,
                    -1
                );
            }

            $cells = preg_split(
                '/(?<!\\\\)\|/',
                $line
            );

            if ($cells === false) {
                return [];
            }

            foreach ($cells as &$cell) {
                $cell = str_replace(
                    '\|',
                    '|',
                    $cell
                );
            }

            unset($cell);

            return $cells;
        }

        /**
         * Render a Markdown list block while preserving indentation nesting.
         *
         * @param string $block         Raw Markdown list block.
         * @param string $tag           HTML list element.
         * @param string $markerPattern List marker pattern.
         *
         * @return string
         */
        private function renderNestedList(
            string $block,
            string $tag,
            string $markerPattern
        ): string {
            $lines = preg_split(
                '/\n/',
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

                $content = (string) (
                    $matches[2] ?? ''
                );

                $items[] = [
                    'indent' => $width,
                    'content' => $content,
                ];

                $indentWidths[$width] = true;
            }

            if ($items === []) {
                return '';
            }

            $levels = array_keys(
                $indentWidths
            );

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

            $class = $this->containsTaskItems(
                $items
            )
                ? ' class="task-list"'
                : '';

            return $this->renderNestedListLevel(
                $items,
                $position,
                0,
                $tag,
                $class
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
         * @param int    $position Current list position.
         * @param int    $level    Current nesting level.
         * @param string $tag      HTML list element.
         * @param string $class    Optional list class.
         *
         * @return string
         */
        private function renderNestedListLevel(
            array $items,
            int &$position,
            int $level,
            string $tag,
            string $class = ''
        ): string {
            $out = '<'
                . $tag
                . $class
                . '>';

            $count = count($items);

            while ($position < $count) {
                $itemLevel = (int) $items[
                    $position
                ]['level'];

                if ($itemLevel < $level) {
                    break;
                }

                if ($itemLevel > $level) {
                    break;
                }

                $content = (string) $items[
                    $position
                ]['content'];

                $itemClass = '';

                /*
                 * GitHub-style task lists.
                 *
                 * - [ ] Pending
                 * - [x] Complete
                 */
                if (
                    preg_match(
                        '/^\[([ xX])\]\s+(.+)$/',
                        $content,
                        $task
                    ) === 1
                ) {
                    $checked = strtolower(
                        (string) $task[1]
                    ) === 'x';

                    $content = '<input type="checkbox" disabled'
                        . ($checked ? ' checked' : '')
                        . '> '
                        . $task[2];

                    $itemClass = ' class="task-list-item"';
                }

                $out .= '<li'
                    . $itemClass
                    . '>'
                    . $content;

                $position++;

                while (
                    $position < $count
                    && (int) $items[
                        $position
                    ]['level'] > $level
                ) {
                    $childLevel = (int) $items[
                        $position
                    ]['level'];

                    $out .= $this->renderNestedListLevel(
                        $items,
                        $position,
                        $childLevel,
                        $tag
                    );
                }

                $out .= '</li>';
            }

            $out .= '</'
                . $tag
                . '>';

            return $out;
        }

        /**
         * Determine whether a parsed list contains task-list items.
         *
         * @param array<int, array<string, mixed>> $items Parsed list items.
         *
         * @return bool
         */
        private function containsTaskItems(
            array $items
        ): bool {
            foreach ($items as $item) {
                if (
                    preg_match(
                        '/^\[[ xX]\]\s+/',
                        (string) ($item['content'] ?? '')
                    ) === 1
                ) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Generate a heading anchor.
         *
         * @param string $heading Heading content.
         *
         * @return string
         */
        private function slugifyHeading(
            string $heading
        ): string {
            $heading = html_entity_decode(
                strip_tags($heading),
                ENT_QUOTES,
                'UTF-8'
            );

            $heading = preg_replace(
                '/[`*_~{}\[\]()]/',
                '',
                $heading
            );

            $heading = strtolower(
                trim((string) $heading)
            );

            $heading = preg_replace(
                '/[^a-z0-9\s-]/',
                '',
                $heading
            );

            $heading = preg_replace(
                '/[\s-]+/',
                '-',
                (string) $heading
            );

            $heading = trim(
                (string) $heading,
                '-'
            );

            return $heading !== ''
                ? htmlspecialchars(
                    $heading,
                    ENT_QUOTES,
                    'UTF-8'
                )
                : 'section';
        }

        /**
         * Render collected footnotes.
         *
         * @param array<string, string> $footnotes Footnote definitions.
         *
         * @return string
         */
        private function renderFootnotes(
            array $footnotes
        ): string {
            $out = '<section class="footnotes">';
            $out .= '<hr>';
            $out .= '<ol>';

            foreach ($footnotes as $id => $content) {
                $safeId = htmlspecialchars(
                    $id,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $safeContent = htmlspecialchars(
                    $content,
                    ENT_QUOTES,
                    'UTF-8'
                );

                $out .= '<li id="fn-'
                    . $safeId
                    . '">'
                    . $safeContent
                    . ' '
                    . '<a href="#fnref-'
                    . $safeId
                    . '" class="footnote-backref">'
                    . '↩'
                    . '</a>'
                    . '</li>';
            }

            $out .= '</ol>';
            $out .= '</section>';

            return $out;
        }

        /**
         * Determine whether a URL is permitted.
         *
         * @param string $url URL to inspect.
         *
         * @return bool
         */
        private static function isSafeUrl(
            string $url
        ): bool {
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

            if ($isRelative) {
                return true;
            }

            return in_array(
                $scheme,
                [
                    'http',
                    'https',
                    'mailto',
                ],
                true
            );
        }

        /* [End AI:GPT-5.6 Sol] */
    }
}