<?php
declare(strict_types=1);

final class manifesto extends controller
{
    public function index($url_params = null): void
    {
        $this->view('public/page/manifesto');
    }
}
