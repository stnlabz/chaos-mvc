<?php

/**
 * Class capabilities_model
 */
class capabilities_model extends model
{
    /**
     * Get all capabilities
     *
     * @return array
     */
    public function get_all(): array
    {
        $file = APPROOT . '/data/capabilities.json';

        if (!file_exists($file)) {
            return [];
        }

        $json = file_get_contents($file);
        $data = json_decode($json, true);

        return is_array($data) ? $data : [];
    }
}
