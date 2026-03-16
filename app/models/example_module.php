<?php
/**
 * Example Model
 * Demonstrates database interaction
 */

class example_model extends model
{

    public function get_message()
    {

        $sql = "SELECT message FROM example LIMIT 1";

        $result = $this->fetch($sql);

        return $result['message'] ?? 'Hello from Chaos MVC';

    }

}
