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
        //declare which model is needed
        $model = $this->model('example_model');
        
        // declare which function of that model you are using
        $message = $model->get_message();
        
        // put the data from the model in an array
        $data = [
            'message' => $message
        ];

        // send the array to the view
        $this->view('public/example/index', $data);
    }

}
