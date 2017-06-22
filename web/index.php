<?php

/*
 * This file is part of the Slack Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Joli\SlackSecretSanta\SantaKernel;
use Symfony\Component\Debug\Debug;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require __DIR__ . '/../vendor/autoload.php';

// The check is to ensure we don't use .env in production
if (!getenv('APP_ENV')) {
    (new Dotenv())->load(__DIR__ . '/../.env');
}

if (getenv('APP_DEBUG')) {
    // This check prevents access to debug front controllers that are deployed by accident to production servers.
    // Feel free to remove this, extend it, or make something more sophisticated.
    if (isset($_SERVER['HTTP_CLIENT_IP'])
        || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
        || !(in_array(@$_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'], true) || PHP_SAPI === 'cli-server')
    ) {
        header('HTTP/1.0 403 Forbidden');
        exit('You are not allowed to access this file. Check ' . basename(__FILE__) . ' for more information.');
    }

    Debug::enable();
}

Request::setTrustedProxies(['0.0.0.0/0'], Request::HEADER_FORWARDED | Request::HEADER_X_FORWARDED_ALL);

$kernel = new SantaKernel(getenv('APP_ENV'), getenv('APP_DEBUG'));
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
