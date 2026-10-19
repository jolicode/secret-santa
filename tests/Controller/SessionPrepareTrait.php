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
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorageFactory;

trait SessionPrepareTrait
{
    /**
     * @param string|array<string, mixed> $key A single key, or several attributes at once
     */
    public function prepareSession(KernelBrowser $client, string|array $key, mixed $value = null): void
    {
        $client->getCookieJar()->set(new Cookie('MOCKSESSID', $this->storeSession(\is_array($key) ? $key : [$key => $value])));
    }

    /**
     * Writes the attributes in a mock session file and returns the session id to put in the MOCKSESSID cookie.
     *
     * @param array<string, mixed> $attributes
     */
    public function storeSession(array $attributes): string
    {
        /** @var MockFileSessionStorageFactory $sessionStorageFactory */
        $sessionStorageFactory = self::$kernel->getContainer()->get('test.session.storage.factory.mock_file');
        /** @var MockFileSessionStorage $sessionStorage */
        $sessionStorage = $sessionStorageFactory->createStorage(null);

        $sessionStorage->setId('session-mock-id');
        $sessionStorage->start();
        $sessionStorage->setSessionData(
            [
                '_sf2_attributes' => $attributes,
            ],
        );

        $sessionStorage->save();

        return 'session-mock-id';
    }
}
