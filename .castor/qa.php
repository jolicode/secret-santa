<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace qa;

use Castor\Attribute\AsTask;

#[AsTask(description: 'Runs all QA tasks')]
function all(): void
{
    install();
    cs();
    phpstan();
    phpunit();
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    docker_compose_run('composer install --working-dir tools/php-cs-fixer');
    docker_compose_run('composer install --working-dir tools/phpstan');
}

#[AsTask(description: 'Runs PHPStan')]
function phpstan(): void
{
    docker_compose_run('phpstan');
}

#[AsTask(description: 'Runs PHPUnit')]
function phpunit(): void
{
    docker_compose_run('bin/phpunit');
}

#[AsTask(description: 'Fixes Coding Style')]
function cs(bool $dryRun = false): void
{
    if ($dryRun) {
        docker_compose_run('php-cs-fixer fix --dry-run --diff');
    } else {
        docker_compose_run('php-cs-fixer fix');
    }
}
