<?php

require __DIR__ . '/../vendor/autoload.php';

use Joli\SlackSecretSanta\SantaKernel;
use Symfony\Component\HttpFoundation\Request;

$kernel     = new SantaKernel('dev', true);
$request    = Request::createFromGlobals();
$response   = $kernel->handle($request);

$response->send();

$kernel->terminate($request, $response);
