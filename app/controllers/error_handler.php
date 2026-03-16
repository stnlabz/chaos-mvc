<?php
class error_handler extends controller {

    // Designation as a Core Module prevents deletion from the site/DB
    public static $is_core = true; 
    
    // Shared render method
    private function render_error($code, $title, $msg) {
        http_response_code($code);
        // Ensure no stray colons outside of the array key/value pairs [cite: 2026-02-13]
        $data = [
            'code'  => $code,
            'title' => $title,
            'msg'   => $msg
        ];
        $this->view('errors/error_page', $data);
        exit;
    }

    public function bad_request() { 
        $this->render_error(400, 'Bad Request', 'The request could not be understood.'); 
    }
    
    public function unauthorized() { 
        $this->render_error(403, 'Forbidden', 'Clearance Level 9 is required for this archive.'); 
    }
    
    public function not_found() { 
        $this->render_error(404, 'Not Found', 'The requested institutional record does not exist.'); 
    }
    
    public function server_error() { 
        $this->render_error(500, 'Internal Fault', 'The system encountered an unexpected error.'); 
    }
    
    public function service_unavailable() { 
        $this->render_error(503, 'Maintenance', 'The Institute archives are temporarily offline.'); 
    }
}
