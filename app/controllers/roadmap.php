<?php
/**
 * Certification Controller
 * Public face of the Chaos Certified Program
 */
class roadmap extends controller {

    public function index() {
        // Data to be presented to the 'Mainstream Interwebz'
        $data = [
            'title' => 'Chaos MVC Development Roadmap',
            'status' => 'Stable',
            'version' => '1.1.6',
        ];

        $this->view('roadmap/index', $data);
    }
}
