<?php

    /*
    * This file is part of the Secret Santa project.
    *
    * (c) JoliCode <coucou@jolicode.com>
    *
    * For the full copyright and license information, please view the LICENSE
    * file that was distributed with this source code.
    */

    namespace JoliCode\SecretSanta\Composer;

    use Composer\Script\Event;
    use Symfony\Component\Yaml\Yaml;

    require __DIR__ . '/../../vendor/autoload.php';

    class UpdateVersion
    {
        public static function frameworkYaml(Event $event)
        {
            $composer = $event -> getComposer();
            $io = $event -> getIO();

            $rootDirectory = sprintf("%s/../../", __DIR__);
            $assetsDirectory = realpath(sprintf("%s/public", $rootDirectory));

            $yaml = realpath(sprintf("%s/config/packages/framework.yaml", $rootDirectory));

            $config = Yaml::parseFile($yaml);

            $version = $config['framework']['assets']['version'];

            $rdi = new \RecursiveDirectoryIterator($assetsDirectory);
            $rii = new \RecursiveIteratorIterator($rdi);

            $rawHash = '';

            foreach($rii as $file) {
                if (substr($file, -1) === '.' || substr($file, -1) === '..') continue;

                $rawHash .= hash_file('sha256', $file);
            }

            $hash = hash('crc32', $rawHash);

            if ($hash !== $version) {
                $io -> write("<comment>New version detected.</comment>");

                $config['framework']['assets']['version'] = $hash;

                if (!file_put_contents($yaml, Yaml::dump($config, 3))) {
                    $io -> write("<error>config/packages/framework.yaml could not be updated.</error>");
                } else {
                    $io -> write("<info>config/packages/framework.yaml updated.</info>");
                }
            }

            return 1;
        }
    }