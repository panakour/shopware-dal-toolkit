<?php

declare(strict_types=1);

use Shopware\Core\TestBootstrapper;

$loader = (new TestBootstrapper)
    ->setPlatformEmbedded(false)
    ->setForceInstallPlugins(true)
    ->bootstrap()
    ->setProjectDir(dirname(__DIR__))

    ->getClassLoader();

$loader->addPsr4('Panakour\\ShopwareDALToolkit\\Tests\\', __DIR__);
