<?php
// path: /app/models/content_renderer.php

class content_renderer
{
    public static function render(string $text): string
    {
        $lines = preg_split("/\R/u", (string)$text);

        $output = '';
        $inUl = false;
        $inOl = false;

        // Inline formatter (escape first, then apply bold and anchors)
        $inline = function (string $s): string {
            // Escape first to follow "No HTML in DB" doctrine
            $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            
            // Fixed: ***text*** logic
            $s = preg_replace('/\*\*\*(.+?)\*\*\*/u', '<strong>$1</strong>', $s);
            
            // Added: [Link Text](URL) logic
            $s = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/u', '<a href="$2">$1</a>', $s);
            
            return $s;
        };

        foreach ($lines as $line) {

            $line = rtrim($line);

            // Close lists on blank line
            if (trim($line) === '') {
                if ($inUl) { $output .= "</ul>"; $inUl = false; }
                if ($inOl) { $output .= "</ol>"; $inOl = false; }
                continue;
            }

            // H3 (### Heading)
            if (preg_match('/^\s*###\s*(.+)$/u', $line, $match)) {
                if ($inUl) { $output .= "</ul>"; $inUl = false; }
                if ($inOl) { $output .= "</ol>"; $inOl = false; }

                $output .= "<h3 class='mt-3 mb-2'>" . $inline($match[1]) . "</h3>";
                continue;
            }

            // H2 (## Heading)
            if (preg_match('/^\s*##\s*(.+)$/u', $line, $match)) {
                if ($inUl) { $output .= "</ul>"; $inUl = false; }
                if ($inOl) { $output .= "</ol>"; $inOl = false; }

                $output .= "<h2 class='mt-4 mb-3 text-primary'>" . $inline($match[1]) . "</h2>";
                continue;
            }
            
            // H1 (# Heading)
            if (preg_match('/^\s*#\s*(.+)$/u', $line, $match)) {
                if ($inUl) { $output .= "</ul>"; $inUl = false; }
                if ($inOl) { $output .= "</ol>"; $inOl = false; }

                $output .= "<h1 class='mt-4 mb-3 text-primary'>" . $inline($match[1]) . "</h1>";
                continue;
            }

            // Unordered list (- item)
            if (preg_match('/^\s*-\s+(.+)$/u', $line, $match)) {
                if ($inOl) { $output .= "</ol>"; $inOl = false; }
                if (!$inUl) {
                    $output .= "<ul>";
                    $inUl = true;
                }

                $output .= "<li>" . $inline($match[1]) . "</li>";
                continue;
            }

            // Ordered list (1. item)
            if (preg_match('/^\s*\d+\.\s+(.+)$/u', $line, $match)) {
                if ($inUl) { $output .= "</ul>"; $inUl = false; }
                if (!$inOl) {
                    $output .= "<ol>";
                    $inOl = true;
                }

                $output .= "<li>" . $inline($match[1]) . "</li>";
                continue;
            }

            // Paragraph
            if ($inUl) { $output .= "</ul>"; $inUl = false; }
            if ($inOl) { $output .= "</ol>"; $inOl = false; }

            $output .= "<p>" . $inline(trim($line)) . "</p>";
        }

        // Close any open lists
        if ($inUl) { $output .= "</ul>"; }
        if ($inOl) { $output .= "</ol>"; }

        return $output;
    }
}
