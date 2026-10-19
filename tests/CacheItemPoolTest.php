<?php

declare(strict_types=1);

namespace Gadget\Cache\Tests;

use Gadget\Cache\CacheItemPool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Exercises the pool against a real in-memory PSR-6 adapter.
 */
final class CacheItemPoolTest extends TestCase
{
    private ArrayAdapter $adapter;

    private CacheItemPool $pool;


    protected function setUp(): void
    {
        $this->adapter = new ArrayAdapter();
        $this->pool = new CacheItemPool($this->adapter, ['app', 'users']);
    }


    public function testKeyIsTheSha256OfTheNamespaceAndKeyJoinedByDoubleColons(): void
    {
        self::assertSame(hash('SHA256', 'app::users::42'), $this->pool->key('42'));
    }


    public function testKeyWithNoNamespaceHashesTheBareKey(): void
    {
        $pool = new CacheItemPool($this->adapter);

        self::assertSame(hash('SHA256', '42'), $pool->key('42'));
    }


    public function testWithNamespaceReturnsANewPoolOnTheSameAdapter(): void
    {
        $scoped = $this->pool->withNamespace('app', 'orders');

        self::assertNotSame($this->pool, $scoped);
        self::assertSame(hash('SHA256', 'app::orders::1'), $scoped->key('1'));
        self::assertSame(hash('SHA256', 'app::users::1'), $this->pool->key('1'));

        $scoped->set('1', 'order one');
        self::assertTrue($this->adapter->hasItem($scoped->key('1')));
    }


    public function testWithNamespaceReplacesRatherThanAppendsTheNamespace(): void
    {
        $scoped = $this->pool->withNamespace('orders');

        self::assertSame(hash('SHA256', 'orders::1'), $scoped->key('1'));
    }


    public function testHasIsFalseForAMissingKey(): void
    {
        self::assertFalse($this->pool->has('missing'));
    }


    public function testGetIsNullForAMissingKey(): void
    {
        self::assertNull($this->pool->get('missing'));
    }


    public function testSetStoresAValueThatGetAndHasReport(): void
    {
        self::assertTrue($this->pool->set('name', 'Ada'));

        self::assertTrue($this->pool->has('name'));
        self::assertSame('Ada', $this->pool->get('name'));
    }


    public function testSetStoresTheValueUnderTheHashedKey(): void
    {
        $this->pool->set('name', 'Ada');

        self::assertSame('Ada', $this->adapter->getItem($this->pool->key('name'))->get());
        self::assertFalse($this->adapter->hasItem('name'));
    }


    public function testSetOverwritesAnExistingValue(): void
    {
        $this->pool->set('name', 'Ada');
        $this->pool->set('name', 'Grace');

        self::assertSame('Grace', $this->pool->get('name'));
    }


    public function testSetWithNullDeletesTheKey(): void
    {
        $this->pool->set('name', 'Ada');

        self::assertTrue($this->pool->set('name', null));

        self::assertFalse($this->pool->has('name'));
        self::assertNull($this->pool->get('name'));
    }


    public function testSetWithAFutureTimestampKeepsTheValue(): void
    {
        $this->pool->set('name', 'Ada', time() + 3600);

        self::assertTrue($this->pool->has('name'));
    }


    public function testSetWithAPastTimestampExpiresTheValueImmediately(): void
    {
        $this->pool->set('name', 'Ada', time() - 3600);

        self::assertFalse($this->pool->has('name'));
        self::assertNull($this->pool->get('name'));
    }


    public function testSetWithNoTimestampNeverExpires(): void
    {
        $this->pool->set('name', 'Ada');

        self::assertNull($this->adapter->getItem($this->pool->key('name'))->getMetadata()['expiry'] ?? null);
    }


    public function testDeleteRemovesTheKeyAndReturnsTrue(): void
    {
        $this->pool->set('name', 'Ada');

        self::assertTrue($this->pool->delete('name'));

        self::assertFalse($this->pool->has('name'));
    }


    public function testDeleteOfAMissingKeyReturnsTrue(): void
    {
        self::assertTrue($this->pool->delete('missing'));
    }


    public function testGetObjectReturnsTheStoredObjectWhenItMatchesTheClass(): void
    {
        $value = new \ArrayObject(['a' => 1]);
        $this->pool->set('object', $value);

        $found = $this->pool->getObject('object', \ArrayObject::class);

        self::assertInstanceOf(\ArrayObject::class, $found);
        self::assertSame(['a' => 1], $found->getArrayCopy());
    }


    public function testGetObjectAcceptsASubclassOfTheRequestedClass(): void
    {
        $this->pool->set('object', new \ArrayObject());

        self::assertInstanceOf(\ArrayObject::class, $this->pool->getObject('object', \Countable::class));
    }


    public function testGetObjectIsNullWhenTheValueIsADifferentClass(): void
    {
        $this->pool->set('object', new \SplStack());

        self::assertNull($this->pool->getObject('object', \ArrayObject::class));
    }


    public function testGetObjectIsNullWhenTheValueIsNotAnObject(): void
    {
        $this->pool->set('scalar', 'Ada');

        self::assertNull($this->pool->getObject('scalar', \ArrayObject::class));
    }


    public function testGetObjectIsNullForAMissingKey(): void
    {
        self::assertNull($this->pool->getObject('missing', \ArrayObject::class));
    }


    public function testTheSameKeyInDifferentNamespacesAreSeparateEntries(): void
    {
        $users = $this->pool;
        $orders = $this->pool->withNamespace('app', 'orders');

        $users->set('1', 'Ada');
        $orders->set('1', 'order one');

        self::assertSame('Ada', $users->get('1'));
        self::assertSame('order one', $orders->get('1'));

        $users->delete('1');

        self::assertFalse($users->has('1'));
        self::assertTrue($orders->has('1'));
    }


    public function testTwoPoolsWithTheSameNamespaceShareEntries(): void
    {
        $other = new CacheItemPool($this->adapter, ['app', 'users']);

        $this->pool->set('name', 'Ada');

        self::assertSame('Ada', $other->get('name'));
    }


    public function testStoresScalarAndArrayValuesAsGiven(): void
    {
        $this->pool->set('int', 7);
        $this->pool->set('float', 1.5);
        $this->pool->set('bool', false);
        $this->pool->set('array', ['x' => [1, 2]]);

        self::assertSame(7, $this->pool->get('int'));
        self::assertSame(1.5, $this->pool->get('float'));
        self::assertFalse($this->pool->get('bool'));
        self::assertSame(['x' => [1, 2]], $this->pool->get('array'));
    }
}
