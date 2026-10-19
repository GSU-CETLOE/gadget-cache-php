<?php

declare(strict_types=1);

namespace Gadget\Cache\Tests;

use Gadget\Cache\DependencyInjection\GadgetCacheExtension;
use Gadget\Cache\GadgetCacheBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class GadgetCacheBundleTest extends TestCase
{
    public function testIsASymfonyBundle(): void
    {
        self::assertInstanceOf(Bundle::class, new GadgetCacheBundle());
    }


    public function testExposesTheGadgetCacheExtension(): void
    {
        $extension = (new GadgetCacheBundle())->getContainerExtension();

        self::assertInstanceOf(GadgetCacheExtension::class, $extension);
        self::assertSame('gadget_cache', $extension->getAlias());
    }
}
