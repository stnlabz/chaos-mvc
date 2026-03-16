<?php
declare(strict_types=1);

class utility
{
    public function nav_active(string $href, string $current): string
    {
        // 1. Normalize both strings to remove trailing slashes for the comparison
        $href = rtrim($href, '/');
        $current = rtrim($current, '/');

        // 2. Exact match check (handles home page and exact paths)
        if ($current === $href) {
            return 'active';
        }

        // 3. Drill-down check: Current must start with href + a slash
        // This ensures /admin/dashboard matches /admin/dashboard/edit 
        // but / does NOT match /logout
        if ($href !== '' && strpos($current, $href . '/') === 0) {
            return 'active';
        }

        return '';
    }

    public function redirect_to(string $url): never
    {
        header("Location: " . $url);
        exit;
    }
}
