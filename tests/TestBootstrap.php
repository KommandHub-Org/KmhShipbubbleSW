<?php

declare(strict_types=1);

namespace Kommandhub\ShipbubbleSW\Tests;

$loader = (new TestBootstrapper())
    ->setPlatformEmbedded(true)
    ->addCallingPlugin()
    ->setForceInstallPlugins(true)
    ->addActivePlugins(
        'KmhShipbubbleSW',
    )
    ->bootstrap()
    ->getClassLoader();

$loader->addPsr4('Kommandhub\ShipbubbleSW\\Tests\\', __DIR__);
