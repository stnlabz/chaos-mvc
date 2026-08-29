<?php
// path: /user/modules/home/controllers/home.php

/* [AI:OpenAI Codex | 2026-08-27 23:06:41 UTC] */
/**
 * home Controller
 */
class home extends controller
{
    /**
     * Display module administration.
     *
     * @param array $params Route parameters.
     *
     * @return void
     */
    public function index($url_params = null): void
    {
        // 1. Load the models/plugins
        $announcements = $this->module_model('announcements', 'announcements_model'); 

        // 2. Fetch data from both sources
        $latest = $announcements->get_latest_single();

        // 3. Package everything into ONE data array
        $data = [
            'featured_announcement' => $latest
        ];

        // 4. Pass the combined $data to the view
        $this->view('index', $data);
    }
}
/* [End AI:OpenAI Codex] */
