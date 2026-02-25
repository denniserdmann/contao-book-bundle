<?php

declare(strict_types=1);

use Contao\Rector\Set\SetList as ContaoSetList;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src'])
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        ContaoSetList::CONTAO,
    ])
    ->withSkip([
        __DIR__.'/src/Resources',
    ])
;
