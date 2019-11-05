<?php

    /*
    * This file is part of the Secret Santa project.
    *
    * (c) JoliCode <coucou@jolicode.com>
    *
    * For the full copyright and license information, please view the LICENSE
    * file that was distributed with this source code.
    */

	$rootDirectory = sprintf("%s/../../", __DIR__);
	$assetsDirectory = realpath(sprintf("%s/public", $rootDirectory));

	$finder = new Symfony\Component\Finder\Finder();
	$files = $finder->in($assetsDirectory)->files();

	$hashes = hash_init('crc32b');

	foreach($files as $file) {
		hash_update_file($hashes, $file);
	}

	$hash = hash_final($hashes);

	$container->loadFromExtension('framework', [
		'assets' => [
			'version' => $hash
		]
	]);
