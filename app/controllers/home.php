<?php
declare(strict_types=1);

final class home extends controller
{
    public function index($url_params = null): void
    {
        // 1. Load the models/plugins
        $announcements = $this->model('announcements_model');
        //$holidays = new holiday(); 

        // 2. Fetch data from both sources
        $latest = $announcements->get_latest_single();
        $holiday_msg = $this->get_holiday();

        // 3. Package everything into ONE data array
        $data = [
            'featured_announcement' => $latest,
            'holiday_message'       => $holiday_msg
        ];

        // 4. Pass the combined $data to the view
        $this->view('public/home/index', $data);
    }
    
    private function get_holiday(): string
    {
        $date = $date ?? gmdate('Y-m-d');
        $date = trim($date);

        if ($date === '' || !preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date)) {
            return '';
        }

        $year = substr($date, 0, 4);

        switch ($date) {
            case $year . '-01-01':
                return 'A new cycle begins.<br><em>Bene Sit.</em>';

            case $year . '-02-01':
                // Imbolc: The return of the light
                return 'The first stirrings of spring and the New Year. Greetings on Imbolc.<br><em>Bene Sit.</em>';

            case $year . '-03-21':
                // Ostara: Balance
                return 'Night and day in balance. Joyous Ostara.<br><em>Aequinoctium Vernum.</em>';

            case $year . '-05-01':
                // Beltane: Fire and Life
                return 'The fires are lit. Happy Beltane.<br><em>Vivat Vita.</em>';

            case $year . '-06-21':
                // Litha: Peak Sun
                return 'The longest day. Standing in the light of Litha.<br><em>Solstitium Aestivum.</em>';

            case $year . '-08-01':
                // Lughnasadh: First Harvest
                return 'The grain is cut. Blessings of the first harvest.<br><em>Macte Virtute.</em>';

            case $year . '-09-21':
                // Mabon: Second Harvest
                return 'The harvest home. Abundance on Mabon.<br><em>Aequinoctium Autumnale.</em>';

            case $year . '-10-31':
                // Samhain: The Thin Veil
                return 'The veil is thin. Honor to the ancestors on Samhain.<br><em>Memoria Aeterna.</em>';

            case $year . '-12-21':
                // Yule: The Rebirth
                return 'The longest night. The sun returns at Yule.<br><em>Sol Invictus.</em>';

            default:
                return '';
        }
    }
}
