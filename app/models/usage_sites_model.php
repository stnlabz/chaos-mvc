<?php

/**
 * Class usage_sites_model
 */
class usage_sites_model
{
    /**
     * Get all usage sites
     *
     * @return array
     */
    public function get_all(): array
    {
        $file = APPROOT . '/data/usage_sites.json';

        if (!file_exists($file)) {
            return [];
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
