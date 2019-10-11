<?php

    /*
    * This file is part of the Secret Santa project.
    *
    * (c) JoliCode <coucou@jolicode.com>
    *
    * For the full copyright and license information, please view the LICENSE
    * file that was distributed with this source code.
    */

	$container->loadFromExtension('framework', [
		'assets' => [
			'version' => function() {
				$rootDirectory = sprintf("%s/../../", __DIR__);
				$assetsDirectory = realpath(sprintf("%s/public", $rootDirectory));

				$rdi = new \RecursiveDirectoryIterator($assetsDirectory);
				$rii = new \RecursiveIteratorIterator($rdi);

				$rawHash = '';

				foreach($rii as $file) {
					if (substr($file, -1) === '.' || substr($file, -1) === '..') continue;

					$rawHash .= hash_file('sha256', $file);
				}

				return hash('crc32', $rawHash);
			}
		]
	]);
