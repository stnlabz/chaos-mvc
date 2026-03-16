<?php
// path: /app/controllers/security.php

class security extends controller 
{

    public function index($url_params = null) 
    {
        $model = $this->model('modules_model');
        $this->view('public/security/index');
    }
}
