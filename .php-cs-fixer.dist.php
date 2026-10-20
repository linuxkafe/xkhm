<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'phpdoc_align' => true,
        'no_unneeded_control_parentheses' => false,
    ])
    ->setFinder(
        (new Finder())
            ->in(__DIR__)
            ->exclude('vendor')
    )
;
