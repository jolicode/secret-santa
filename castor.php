<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Castor\Attribute\AsTask;

use function Castor\import;
use function Castor\io;
use function Castor\notify;
use function Castor\variable;

import(__DIR__ . '/.castor');

/**
 * @return array<string, mixed>
 */
function create_default_variables(): array
{
    $projectName = 'secret-santa';
    $tld = 'test';

    return [
        'project_name' => $projectName,
        'root_domain' => "{$projectName}.{$tld}",
        'extra_domains' => [
            "www.{$projectName}.{$tld}",
        ],
        'project_directory' => '.',
        'php_version' => '8.2',
    ];
}

#[AsTask(description: 'Builds and starts the infrastructure, then install the application (composer, yarn, ...)')]
function start(): void
{
    infra\generate_certificates(false);
    infra\build();
    infra\up();
    cache_clear();
    install();

    notify('The stack is now up and running.');
    io()->success('The stack is now up and running.');

    about();
}

#[AsTask(description: 'Installs the application (composer, yarn, ...)', namespace: 'app')]
function install(): void
{
    $basePath = sprintf('%s/%s', variable('root_dir'), variable('project_directory'));

    if (is_file("{$basePath}/composer.json")) {
        docker_compose_run('composer install -n --prefer-dist --optimize-autoloader');
    }
    if (is_file("{$basePath}/yarn.lock")) {
        docker_compose_run('yarn');
    } elseif (is_file("{$basePath}/package.json")) {
        docker_compose_run('npm install');
    }

    qa\install();
}

#[AsTask(description: 'Clear the application cache', namespace: 'app')]
function cache_clear(): void
{
    docker_compose_run('rm -rf var/cache/ && bin/console cache:warmup');
}
