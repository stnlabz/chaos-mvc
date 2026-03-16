<?php
/**
 * Developer Controller
 *
 * Provides documentation and examples for the MVC architecture.
 *
 * @package MVC
 */

class developer extends controller
{
    public static $is_core = true;

    public function index()
    {
        $data = [
            'title' => 'Developer Portal'
        ];

        $this->view('public/developer/index', $data);
    }

    public function example()
    {
        $data = [
            'title' => 'Example Module'
        ];

        $this->view('public/developer/example', $data);
    }

    public function flow()
    {
        $data = [
            'title' => 'MVC Execution Flow'
        ];

        $this->view('public/developer/flow', $data);
    }

    public function database()
    {
        $data = [
            'title' => 'Database Wrapper'
        ];

        $this->view('public/developer/database', $data);
    }

    public function markdown()
    {
        $data = [
            'title' => 'Markdown Rendering'
        ];

        $this->view('public/developer/markdown', $data);
    }

    public function rules()
    {
        $data = [
            'title' => 'Development Rules'
        ];

        $this->view('public/developer/rules', $data);
    }
}
