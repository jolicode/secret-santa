<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorageFactory;

trait SessionPrepareTrait
{
    public function prepareSession(KernelBrowser $client, string $key, mixed $value): void
    {
        /** @var MockFileSessionStorageFactory $sessionStorageFactory */
        $sessionStorageFactory = self::$kernel->getContainer()->get('test.session.storage.factory.mock_file');
        $sessionStorage = $sessionStorageFactory->createStorage(null);

        $sessionStorage->setId('session-mock');
        $sessionStorage->start();
        $sessionStorage->setSessionData(
            [
                '_sf2_attributes' => [$key => $value],
            ],
        );

        $sessionStorage->save();

        $cookie = new Cookie('santaSession', 'session-mock');
        $client->getCookieJar()->set($cookie);
    }
}
