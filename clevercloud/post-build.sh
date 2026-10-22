#!/bin/sh
# Runs after "composer install" on Clever Cloud, through CC_POST_BUILD_HOOK=./clevercloud/post-build.sh
# Composer runs there with --no-scripts, so the importmap packages have to be installed by hand.
set -e

php bin/console importmap:install
php bin/console asset-map:compile
