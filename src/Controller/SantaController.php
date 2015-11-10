<?php

namespace Joli\SlackSecretSanta\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SantaController
{
    private $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    public function homepage(Request $request)
    {
        var_dump($this->session);
        return new Response("Coucou");
    }
}
