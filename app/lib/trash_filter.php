<?php
class trash_filter {
    private array $blacklist = ['cunt', 'tranny', 'transvestite'];

    public function clean(string $text, bool $drop = false): string {
        foreach ($this->blacklist as $word) {
            if (stripos($text, $word) !== false) {
                if ($drop) return ''; 
                $text = str_ireplace($word, '...', $text);
            }
        }
        return $text;
    }
}
