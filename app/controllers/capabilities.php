<?php

/**
 * Public framework capabilities.
 */
/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class capabilities extends controller
{
    public static $is_core = true;

    public function index(): void
    {
        $this->view('public/capabilities/index', [
            'title' => 'System Capabilities',
            'items' => [
                'Predictable MVC request routing',
                'PDO-backed model and query helpers',
                'Authentication and account recovery',
                'Role-gated administration',
                'Posts, comments, media, and dynamic modules',
                'Markdown rendering with safe links',
                'Sitemap, ROR, and llms.txt generation',
                'Signed and checksum-verified module updates'
            ]
        ]);
    }
}
/* [End AI:GPT-5.6 Sol] */
