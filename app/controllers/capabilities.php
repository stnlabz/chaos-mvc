<?php

/**
 * Class capabilities
 */
class capabilities extends controller
{
    /**
     * Display capabilities
     *
     * @return void
     */
    public function index(): void
    {
        $model = $this->model('capabilities_model');

        $data = [
            'title' => 'System Capabilities',
            'items' => $model->get_all()
        ];

        $this->view('capabilities/index', $data);
    }
}
