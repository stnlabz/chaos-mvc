<?php
// path: /app/controllers/health.php

class legal extends controller 
{
    public function index() {
        $util = new utility();
        $util->redirect_to('/');
    }
    
    public function terms() {
        $this->view('public/legal/terms');
    }
    
    public function privacy() {
        $this->view('public/legal/privacy');
    }
}
