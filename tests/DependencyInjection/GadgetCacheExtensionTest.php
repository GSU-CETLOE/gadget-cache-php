<?php

declare(strict_types=1);

namespace Gadget\Cache\Tests\DependencyInjection;

use Gadget\Cache\CacheItemPool;
use Gadget\Cache\DependencyInjection\GadgetCacheExtension;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class GadgetCacheExtensionTest extends TestCase
{
    private static function loadedContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new GadgetCacheExtension())->load([], $container);
        return $container;
    }


    public function testRegistersTheCacheItemPoolAsAnAutowiredService(): void
    {
        $container = self::loadedContainer();

        self::assertTrue($container->hasDefinition(CacheItemPool::class));
        $definition = $container->getDefinition(CacheItemPool::class);
        self::assertTrue($definition->isAutowired());
        self::assertTrue($definition->isAutoconfigured());
    }


    public function testCompiledContainerBuildsThePoolFromTheApplicationsPsr6Pool(): void
    {
        $container = self::loadedContainer();
        $container->register(CacheItemPoolInterface::class, ArrayAdapter::class);
        $container->getDefinition(CacheItemPool::class)->setPublic(true);

        $container->compile();

        $pool = $container->get(CacheItemPool::class);
        self::assertInstanceOf(CacheItemPool::class, $pool);
        self::assertSame(hash('SHA256', 'answer'), $pool->key('answer'));
        $pool->set('answer', 42);
        self::assertSame(42, $pool->get('answer'));
    }
}
