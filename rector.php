<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/app/Filament',
        __DIR__.'/bootstrap/cache',

        // Architecture rules are written against namespaces, not classes. Rewriting
        // them to ::class both imports the very things the rules forbid and stops
        // namespace-level expectations such as 'App\Domain' from reading uniformly.
        StringClassNameToClassConstantRector::class => [
            __DIR__.'/tests/Arch',
        ],
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
