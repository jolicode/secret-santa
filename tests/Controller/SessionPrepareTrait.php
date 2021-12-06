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

trait SessionPrepareTrait
{
    public function prepareSession(KernelBrowser $client, string $key, mixed $value): void
    {
        $sessionStorageFactory = self::$kernel->getContainer()->get('test.session.storage.factory.mock_file');
        $sessionStorage = $sessionStorageFactory->createStorage(null);

        $sessionStorage->start();
        $sessionStorage->setSessionData([$key => $value]);
        $sessionStorage->save();
    }
}
