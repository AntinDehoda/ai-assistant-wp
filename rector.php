<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Set\SymfonySetList;
use Rector\Doctrine\Set\DoctrineSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        // __DIR__ . '/tests',
    ]);

    // Define the sets of rules to apply
    $rectorConfig->sets([
        // Upgrade to PHP 8.2 features
        LevelSetList::UP_TO_PHP_82,
        
        // Apply Symfony 7/Code Quality features
        SymfonySetList::SYMFONY_70,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        
        // Optional: uncomment if Doctrine is used
        // DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
        // DoctrineSetList::DOCTRINE_CODE_QUALITY,
    ]);
};
