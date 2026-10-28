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

use Castor\Attribute\AsOption;
use Castor\Attribute\AsRawTokens;
use Castor\Attribute\AsTask;

use function Castor\capture;
use function Castor\context;
use function Castor\io;
use function Castor\run;
use function Castor\variable;
use function docker\docker_compose_run;
use function docker\docker_exit_code;

const VISUAL_REGRESSIONS_MARKER = '<!-- visual-regressions -->';

#[AsTask(description: 'Runs all QA tasks', aliases: ['test', 'qa'])]
function all(): int
{
    $cs = cs();
    $phpstan = phpstan();
    $phpunit = phpunit();

    return max($cs, $phpstan, $phpunit);
}

#[AsTask(description: 'Installs tooling')]
function install(): void
{
    io()->title('Installing QA tooling');

    docker_compose_run(['composer', 'install', '-o'], workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run(['composer', 'install', '-o'], workDir: '/var/www/tools/phpstan');
}

#[AsTask(description: 'Updates tooling')]
function update(): void
{
    io()->title('Updating QA tooling');

    docker_compose_run(['composer', 'update', '-o'], workDir: '/var/www/tools/php-cs-fixer');
    docker_compose_run(['composer', 'update', '-o'], workDir: '/var/www/tools/phpstan');
}

/**
 * @param list<string> $rawTokens
 */
#[AsTask(description: 'Runs PHPUnit', aliases: ['phpunit'])]
function phpunit(#[AsRawTokens] array $rawTokens = []): int
{
    io()->section('Running PHPUnit...');

    return docker_exit_code(['bin/phpunit', ...$rawTokens]);
}

#[AsTask(description: 'Regenerates the reference screenshots of the E2E tests', name: 'update-screenshots')]
function update_screenshots(): int
{
    io()->section('Regenerating the reference screenshots...');

    return docker_exit_code(['env', 'UPDATE_SCREENSHOTS=1', 'bin/phpunit', '--group', 'screenshot']);
}

/**
 * The images are pushed to the branch ci-screenshots/pr-<number> of the repository, as a single
 * orphan commit replaced on every red run, and linked from a single comment of the pull request:
 * the previous comment is deleted first. A green run or the closing of the pull request removes
 * both the comment and the branch.
 */
#[AsTask(description: 'Posts the visual regressions of the E2E tests as a comment of a pull request', name: 'report-visual-regressions')]
function report_visual_regressions(
    int $pullRequest,
    #[AsOption(description: 'Prints the comment instead of pushing the images and posting it')]
    bool $dryRun = false,
): void {
    $diffs = glob(variable('root_dir') . '/test-failures/*-diff.png') ?: [];

    if (!$dryRun) {
        clear_visual_regressions_report($pullRequest);
    }
    if ([] === $diffs) {
        io()->note('No visual regression to report.');

        return;
    }

    $sha = $dryRun ? '<sha>' : publish_visual_regressions($pullRequest);
    $imagesUrl = \sprintf('https://raw.githubusercontent.com/%s/%s/test-failures', getenv('GITHUB_REPOSITORY'), $sha);
    $runUrl = \sprintf('%s/%s/actions/runs/%s', getenv('GITHUB_SERVER_URL'), getenv('GITHUB_REPOSITORY'), getenv('GITHUB_RUN_ID'));
    $body = VISUAL_REGRESSIONS_MARKER . "\n### Visual regressions\n\n";
    $body .= "The reference is faded out in the diff, the changed pixels are in red. The expected images are in the [artifact of the run]({$runUrl}). If the change is legitimate, run `castor qa:update-screenshots` and commit the result.\n";
    foreach ($diffs as $diff) {
        $name = basename($diff, '-diff.png');
        $body .= "\n#### {$name}\n\n| Actual | Diff |\n|---|---|\n";
        $body .= "| ![{$name} actual]({$imagesUrl}/{$name}-actual.png) | ![{$name} diff]({$imagesUrl}/{$name}-diff.png) |\n";
    }

    if ($dryRun) {
        io()->writeln($body);

        return;
    }

    run(['gh', 'pr', 'comment', (string) $pullRequest, '--body', $body]);
}

#[AsTask(description: 'Deletes the visual regressions comment and images of a pull request, if any', name: 'clear-visual-regressions-report')]
function clear_visual_regressions_report(int $pullRequest): void
{
    $ids = capture(['gh', 'api', "repos/{owner}/{repo}/issues/{$pullRequest}/comments", '--paginate', '--jq', \sprintf('.[] | select(.body | startswith("%s")) | .id', VISUAL_REGRESSIONS_MARKER)]);

    foreach (array_filter(explode("\n", $ids)) as $id) {
        run(['gh', 'api', '-X', 'DELETE', "repos/{owner}/{repo}/issues/comments/{$id}"]);
    }

    // Fails when there is no branch to delete. Keep in sync with .github/workflows/cleanup-screenshots.yml
    run(['gh', 'api', '-X', 'DELETE', "repos/{owner}/{repo}/git/refs/heads/ci-screenshots/pr-{$pullRequest}"], context()->withAllowFailure()->withQuiet());
}

/**
 * Commits test-failures/ alone, through a temporary index so the checkout is left untouched,
 * and force pushes it as the only commit of the branch of the pull request.
 */
function publish_visual_regressions(int $pullRequest): string
{
    $index = variable('root_dir') . '/var/ci-screenshots.index';
    $temporaryIndex = context()->withEnvironment(['GIT_INDEX_FILE' => $index]);

    run(['git', 'add', '--force', 'test-failures'], $temporaryIndex);
    $tree = capture(['git', 'write-tree'], $temporaryIndex);
    $sha = capture(['git', '-c', 'user.name=github-actions[bot]', '-c', 'user.email=41898282+github-actions[bot]@users.noreply.github.com', 'commit-tree', $tree, '-m', "Visual regressions of the pull request #{$pullRequest}"]);
    run(['git', 'push', '--force', 'origin', "{$sha}:refs/heads/ci-screenshots/pr-{$pullRequest}"]);
    unlink($index);

    return $sha;
}

#[AsTask(description: 'Runs PHPStan', aliases: ['phpstan'])]
function phpstan(
    #[AsOption(description: 'Generate baseline file', shortcut: 'b')]
    bool $baseline = false,
): int {
    if (!is_dir(variable('root_dir') . '/tools/phpstan/vendor')) {
        install();
    }

    io()->section('Running PHPStan...');

    $command = ['phpstan', 'analyse', '--memory-limit=-1', '-v'];
    if ($baseline) {
        $command = [...$command, '--generate-baseline', '--allow-empty-baseline'];
    }

    return docker_exit_code($command, workDir: '/var/www');
}

#[AsTask(description: 'Runs Security audit')]
function security_audit(): int
{
    io()->text('Running Composer audit...');

    return docker_exit_code(['composer', 'audit']);
}

#[AsTask(description: 'Fixes Coding Style', aliases: ['cs'])]
function cs(bool $dryRun = false): int
{
    if (!is_dir(variable('root_dir') . '/tools/php-cs-fixer/vendor')) {
        install();
    }

    io()->section('Running PHP CS Fixer...');

    if ($dryRun) {
        return docker_exit_code(['php-cs-fixer', 'fix', '--dry-run', '--diff'], workDir: '/var/www');
    }

    return docker_exit_code(['php-cs-fixer', 'fix', '-v'], workDir: '/var/www');
}
