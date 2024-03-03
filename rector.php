<?php
/**
 * Copyright (c) Dimitri BOUTEILLE (https://github.com/dimitriBouteille)
 * See LICENSE.txt for license details.
 *
 * Author: Dimitri BOUTEILLE <bonjour@dimitri-bouteille.fr>
 */

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Identical\SimplifyBoolIdenticalTrueRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\Property\TypedPropertyFromStrictConstructorRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withRules([
        TypedPropertyFromStrictConstructorRector::class,
    ])
    ->withPreparedSets(
        codeQuality: true,
        typeDeclarations: true,
        instanceOf: true,
    )
    ->withSets([
        SetList::PHP_81,
    ])
    ->withSkip([
        SimplifyBoolIdenticalTrueRector::class,
        CallableThisArrayToAnonymousFunctionRector::class,
        SimplifyIfReturnBoolRector::class,
    ]);
