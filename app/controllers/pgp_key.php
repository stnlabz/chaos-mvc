<?php

class pgp_key extends controller
{
    public function index($url_params = null)
    {
        // You can store the key in a file instead of DB (recommended)
        $keyPath = APPROOT . '/keys/release.asc';

        if (!file_exists($keyPath)) {
            $data = [
                'title' => 'Chaos MVC Release PGP Key',
                'content' => 'The Organizations PGP Key is not configured'
            ];
            $this->view('public/page/pgp_key', $data);
        }
        else {

            $data = [
              'title' => 'Chaos MVC Relese Public Key',
              'content' => file_get_contents($keyPath)
            ];

            $this->view('public/page/pgp_key', $data);
      }
   }
}

