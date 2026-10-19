<?php

declare(strict_types=1);

namespace Gadget\Cache;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class CacheItemPool
{
    /**
     * @param CacheItemPoolInterface $cache
     * @param string[] $namespace
     */
    public function __construct(
        private CacheItemPoolInterface $cache,
        private array $namespace = []
    ) {
    }


    /**
     * @param string $namespace
     * @return self
     */
    public function withNamespace(...$namespace): self
    {
        return new self(
            $this->getCache(),
            $namespace
        );
    }


    /**
     * @param string $key
     * @return string
     */
    public function key(string $key): string
    {
        return hash('SHA256', implode("::", [...$this->namespace, $key]));
    }


    /**
     * @param string $key
     * @return mixed
     */
    public function has(string $key): mixed
    {
        return $this->getItem($key)->isHit();
    }


    /**
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        $item = $this->getItem($key);
        return $item->isHit() ? $item->get() : null;
    }


    /**
     * @template T of object
     * @param string $key
     * @param class-string<T> $class
     * @return T|null
     */
    public function getObject(
        string $key,
        string $class
    ): mixed {
        $value = $this->get($key);
        return (is_object($value) && is_a($value, $class)) ? $value : null;
    }


    /**
     * @param string $key
     * @param mixed $value
     * @param int|null $expires
     * @return bool
     */
    public function set(
        string $key,
        mixed $value,
        int|null $expires = null
    ): bool {
        if ($value === null) {
            return $this->delete($key);
        }

        return $this->setItem(
            $this->getItem($key)
                ->set($value)
                ->expiresAfter(is_int($expires) ? $expires - time() : null)
        );
    }


    /**
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        return $this->getCache()->deleteItem($this->key($key));
    }


    /**
     * @return CacheItemPoolInterface
     */
    protected function getCache(): CacheItemPoolInterface
    {
        return $this->cache;
    }


    /**
     * @param string $key
     * @return CacheItemInterface
     */
    protected function getItem(string $key): CacheItemInterface
    {
        return $this->getCache()->getItem($this->key($key));
    }


    /**
     * @param CacheItemInterface $item
     * @return bool
     */
    protected function setItem(CacheItemInterface $item): bool
    {
        return $this->getCache()->save($item);
    }
}
