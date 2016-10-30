<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->exclude('vendor')
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/web')
;

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::SYMFONY_LEVEL)
    ->finder($finder)
    ->fixers([
        '-empty_return',
        '-concat_without_spaces',
        '-double_arrow_multiline_whitespaces',
        'unalign_equals',
        'unalign_double_arrow',
        '-align_double_arrow',
        '-align_equals',
        'concat_with_spaces',
        'newline_after_open_tag',
        'ordered_use',
        'phpdoc_order',
        'short_array_syntax',
    ])
;
