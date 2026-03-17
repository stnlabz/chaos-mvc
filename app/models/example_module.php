<?php
/**
 * Example Model
 * Demonstrates database interaction
 */

class example_model extends model
{

    public function get_message()
    {
        // Run the query
        $sql = "SELECT message FROM example LIMIT 1";
        
        // Get the reult
        $result = $this->fetch($sql);
        
        // return the result or a default message
        // ?? 'Hello from Chaos MVC Example Module';
        if(!$result) {
            return 'ERROR: Result was empty';
        }
        return $result['message'] ?? 'Hello from Chaos MVC Example Module';
    }
}
