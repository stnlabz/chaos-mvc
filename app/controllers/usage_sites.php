<?php

/**
 * Class usage_sites
 */
class usage_sites extends controller
{
    /**
     * Display usage sites
     *
     * @return void
     */
    public function index(): void
    {
        $model = $this->model('usage_sites_model');

        $data = [
            'title' => 'Built with Chaos MVC',
            'sites' => $model->get_all()
        ];

        $this->view('usage_sites/index', $data);
    }
}
