<?php

use Joli\SlackSecretSanta\SantaKernel;
use Symfony\Component\HttpFoundation\Request;

$kernel     = new SantaKernel('prod', false);
$request    = Request::createFromGlobals();
$response   = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
