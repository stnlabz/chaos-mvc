<?php
declare(strict_types=1);

final class home extends controller
{
    public function index($url_params = null): void
    {
        // 1. Load the models/plugins
        $announcements = $this->model('announcements_model'); 

        // 2. Fetch data from both sources
        $latest = $announcements->get_latest_single();

        // 3. Package everything into ONE data array
        $data = [
            'featured_announcement' => $latest
        ];

        // 4. Pass the combined $data to the view
        $this->view('public/home/index', $data);
    }
}
