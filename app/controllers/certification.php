<?php
/**
 * Certification Controller
 * Public face of the Chaos Certified Program
 */
class certification extends controller {

    public function index() {
        // Data to be presented to the 'Mainstream Interwebz'
        $data = [
            'title' => 'Chaos Certified Program',
            'status' => 'Active',
            'version_standard' => '1.1.6',
            'requirements' => [
                'Lowercase convention for all files/classes',
                'PGP Signature verified against registered Key/IP',
                'Zero-Bloat and No-Magic architectural compliance',
                'Full documentation and AI-model attribution',
                'Intrinsic understanding of the MVC Flow from Controller->Model->Database->Controller->View',
                'and Understand that views carry NO logic what so ever.'
            ]
        ];

        $this->view('public/certification/index', $data);
    }
}
