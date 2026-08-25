<?php

/**
 * Developer documentation controller.
 */
/* [AI:GPT-5.6 Sol | 2026-08-25 UTC] */
class developer extends controller
{
    public static $is_core = true;

    public function index(): void
    {
        $this->show('Developer Portal', 'Choose a topic to understand the framework request flow and conventions.');
    }

    public function example(): void
    {
        $this->show('Example Module', 'A module normally contains a lowercase controller, optional model, and public or admin view.');
    }

    public function flow(): void
    {
        $this->show('MVC Execution Flow', 'Requests pass through the front controller, bootstrap, router, controller, model, and view.');
    }

    public function database(): void
    {
        $this->show('Database Wrapper', 'Models use parameterized PDO queries and validated identifiers through the core model helpers.');
    }

    public function markdown(): void
    {
        $this->show('Markdown Rendering', 'Stored Markdown is HTML-escaped before supported formatting and safe links are rendered.');
    }

    public function theme(): void
    {
        $this->show('MVC Views', 'Views receive controller data and are responsible for escaping values in their HTML context.');
    }

    public function rules(): void
    {
        $this->show('Development Rules', 'Use lowercase names, PSR-12 formatting, DocBlocks, review, and required change annotations.');
    }

    private function show(string $title, string $description): void
    {
        $this->view('public/developer/topic', [
            'title' => $title,
            'description' => $description
        ]);
    }
}
/* [End AI:GPT-5.6 Sol] */
