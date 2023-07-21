<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace infra;

use Castor\Attribute\AsOption;
use Castor\Attribute\AsTask;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\ExecutableFinder;

use function Castor\capture;
use function Castor\finder;
use function Castor\fs;
use function Castor\get_context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;

#[AsTask(description: 'Builds the infrastructure', aliases: ['build'])]
function build(): void
{
    $userId = variable('user_id');
    $phpVersion = variable('php_version');

    $command = [
        'build',
        '--build-arg', "USER_ID={$userId}",
        '--build-arg', "PHP_VERSION={$phpVersion}",
    ];

    docker_compose($command, withBuilder: true);
}

#[AsTask(description: 'Builds and starts the infrastructure', aliases: ['up'])]
function up(): void
{
    try {
        docker_compose(['up', '--remove-orphans', '--detach', '--no-build']);
    } catch (ExceptionInterface $e) {
        io()->error('An error occured while starting the infrastructure.');
        io()->note('Did you forget to run "castor infra:build"?');
        io()->note('Or you forget to login to the registry?');

        throw $e;
    }
}

#[AsTask(description: 'Stops the infrastructure', aliases: ['stop'])]
function stop(): void
{
    docker_compose(['stop']);
}

#[AsTask(description: 'Displays infrastructure logs')]
function logs(): void
{
    docker_compose(['logs', '-f', '--tail', '150'], c: get_context()->withTty());
}

#[AsTask(description: 'Lists containers status')]
function ps(): void
{
    docker_compose(['ps'], withBuilder: false);
}

#[AsTask(description: 'Cleans the infrastructure (remove container, volume, networks)')]
function destroy(
    #[AsOption(description: 'Force the destruction without confirmation', shortcut: 'f')]
    bool $force = false,
): void {
    if (!$force) {
        io()->warning('This will permanently remove all containers, volumes, networks... created for this project.');
        io()->note('You can use the --force option to avoid this confirmation.');
        if (!io()->confirm('Are you sure?', false)) {
            io()->comment('Aborted.');

            return;
        }
    }

    docker_compose(['down', '--remove-orphans', '--volumes', '--rmi=local'], withBuilder: true);
    $files = finder()
        ->in(variable('root_dir') . '/infrastructure/docker/services/router/certs/')
        ->name('*.pem')
        ->files()
    ;
    fs()->remove($files);
}

#[AsTask(description: 'Generates SSL certificates (with mkcert if available or self-signed if not)')]
function generate_certificates(
    #[AsOption(description: 'Force the certificates re-generation without confirmation', shortcut: 'f')]
    bool $force = false,
): void {
    if (file_exists(variable('root_dir') . '/infrastructure/docker/services/router/certs/cert.pem') && !$force) {
        io()->comment('SSL certificates already exists.');
        io()->note('Run "castor infra:generate-certificates --force" to generate new certificates.');

        return;
    }

    if ($force) {
        if (file_exists($f = variable('root_dir') . '/infrastructure/docker/services/router/certs/cert.pem')) {
            io()->comment('Removing existing certificates in infrastructure/docker/services/router/certs/*.pem.');
            unlink($f);
        }

        if (file_exists($f = variable('root_dir') . '/infrastructure/docker/services/router/certs/key.pem')) {
            unlink($f);
        }
    }

    $finder = new ExecutableFinder();
    $mkcert = $finder->find('mkcert');

    if ($mkcert) {
        $pathCaRoot = capture(['mkcert', '-CAROOT']);

        if (!is_dir($pathCaRoot)) {
            io()->warning('You must have mkcert CA Root installed on your host with "mkcert -install" command.');

            return;
        }

        $rootDomain = variable('root_domain');

        run([
            'mkcert',
            '-cert-file', 'infrastructure/docker/services/router/certs/cert.pem',
            '-key-file', 'infrastructure/docker/services/router/certs/key.pem',
            $rootDomain,
            "*.{$rootDomain}",
            ...variable('extra_domains'),
        ]);

        io()->success('Successfully generated SSL certificates with mkcert.');

        if ($force) {
            io()->note('Please restart the infrastructure to use the new certificates with "castor up" or "castor start".');
        }

        return;
    }

    run(['infrastructure/docker/services/router/generate-ssl.sh'], quiet: true);

    io()->success('Successfully generated self-signed SSL certificates in infrastructure/docker/services/router/certs/*.pem.');
    io()->comment('Consider installing mkcert to generate locally trusted SSL certificates and run "castor infra:generate-certificates --force".');

    if ($force) {
        io()->note('Please restart the infrastructure to use the new certificates with "castor up" or "castor start".');
    }
}
