<?php
/**
 * Example Controller
 * Demonstrates basic MVC flow
 * Controller → Model → View
 */

class example extends controller
{

    public function index()
    {
        $model = $this->model('example_model');

        $message = $model->get_message();

        $data = [
            'message' => $message
        ];

        $this->view('public/example/index', $data);
    }

}
