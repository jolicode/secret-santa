<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'app' => [
        'path' => './assets/js/app.js',
        'entrypoint' => true,
    ],
    'faq' => [
        'path' => './assets/js/faq.js',
        'entrypoint' => true,
    ],
    'santa/participants' => [
        'path' => './assets/js/santa/participants.js',
        'entrypoint' => true,
    ],
    'santa/exclusions' => [
        'path' => './assets/js/santa/exclusions.js',
        'entrypoint' => true,
    ],
    'santa/message' => [
        'path' => './assets/js/santa/message.js',
        'entrypoint' => true,
    ],
    'santa/message_slack' => [
        'path' => './assets/js/santa/message_slack.js',
        'entrypoint' => true,
    ],
    'santa/load_users' => [
        'path' => './assets/js/santa/load_users.js',
        'entrypoint' => true,
    ],
    'santa/send_messages' => [
        'path' => './assets/js/santa/send_messages.js',
        'entrypoint' => true,
    ],
    'santa/finish' => [
        'path' => './assets/js/santa/finish.js',
        'entrypoint' => true,
    ],
    'flatpickr' => [
        'version' => '4.6.13',
    ],
    // Stylesheets are only here so that importmap:install downloads them: they are linked from
    // the templates with asset('vendor/...'), not imported from JS.
    'flatpickr/dist/flatpickr.min.css' => [
        'version' => '4.6.13',
        'type' => 'css',
    ],
    'purecss/build/pure-min.css' => [
        'version' => '0.6.0',
        'type' => 'css',
    ],
    'purecss/build/grids-responsive-min.css' => [
        'version' => '0.6.0',
        'type' => 'css',
    ],
    '@bugsnag/js' => [
        'version' => '9.1.0',
    ],
    '@bugsnag/browser' => [
        'version' => '9.1.0',
    ],
];
