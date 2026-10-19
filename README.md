# gadget-cache-php

A thin wrapper around any [PSR-6](https://www.php-fig.org/psr/psr-6/) cache pool that gives you namespaced keys and a smaller API. You hand `CacheItemPool` a real pool (Symfony's `cache.app`, a Redis adapter, whatever you have) and a namespace. From then on you call `get`, `set`, `has`, and `delete` with plain string keys, and the wrapper takes care of hashing them so two parts of your app can use the same key without stepping on each other. A Symfony bundle is included so the pool can be autowired into services.

CETLOE at Georgia State University maintains this package. It started as `mjfk23/gadget-cache`, written by Melody Kimball.

## Requirements

- PHP 8.1
- A PSR-6 cache implementation to wrap. The package depends on `psr/cache` for the interfaces only, so pick your own adapter. [symfony/cache](https://symfony.com/doc/current/components/cache.html) is what the test suite uses.

## Setup

Install [Composer](https://getcomposer.org/) if you don't have it, then add the package to your project:

```sh
composer require gsu-cetloe/gadget-cache-php
```

To work on the library itself, clone the repository and install the development dependencies:

```sh
git clone https://github.com/GSU-CETLOE/gadget-cache-php.git
cd gadget-cache-php
composer install
```

Your machine does not have to run PHP 8.1. Composer is told to resolve dependencies as if it were PHP 8.1.0 no matter what version runs it, so the lock file always reflects what the library targets. Before a release, run the suite on an actual 8.1 build.

## Development commands

| Command | What it does |
| --- | --- |
| `composer test` | Runs the PHPUnit suite in `tests/`. |
| `composer phpstan` | Runs PHPStan at level 10 over `src/`, `bundle/`, and `tests/`. |
| `composer phpcs` | Checks `src/`, `bundle/`, and `tests/` against PSR-12. |
| `composer check` | Runs all three in order. Run this before you commit. |

Anything generated, such as the PHPStan and PHPUnit caches, lands in `working/`. Git ignores that directory.

## How it works

There is one class, `Gadget\Cache\CacheItemPool`. It holds a `Psr\Cache\CacheItemPoolInterface` and a list of namespace strings.

```php
use Gadget\Cache\CacheItemPool;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$pool = new CacheItemPool(new FilesystemAdapter(), ['app', 'users']);

$pool->set('42', ['name' => 'Ada']);
$pool->has('42');   // true
$pool->get('42');   // ['name' => 'Ada']
$pool->delete('42');
$pool->get('42');   // null
```

### Keys

Every key you pass is turned into the SHA-256 hash of the namespace parts and the key joined with `::`. With the pool above, `'42'` is stored under `hash('SHA256', 'app::users::42')`. That does two things for you. Your keys never hit the underlying pool, so you can use any string, including ones PSR-6 would reject such as `user/42` or keys with spaces. And two pools with different namespaces can both store `'42'` without colliding.

`key()` is public if you need to find the real key, for instance to clear an entry from the underlying pool directly.

### Namespaces

`withNamespace()` returns a new pool on the same underlying cache with a different namespace. It replaces the namespace; it does not append to it.

```php
$users  = new CacheItemPool($adapter, ['app', 'users']);
$orders = $users->withNamespace('app', 'orders');

$users->set('1', 'Ada');
$orders->set('1', 'first order');

$users->get('1');   // 'Ada'
$orders->get('1');  // 'first order'
```

Two pools built with the same namespace on the same adapter see each other's entries, because the hash is deterministic. That is usually what you want when a service is constructed in more than one place.

### Reading

| Method | Returns |
| --- | --- |
| `has(string $key)` | `true` when the entry exists and has not expired. |
| `get(string $key)` | The stored value, or `null` when missing or expired. |
| `getObject(string $key, string $class)` | The stored value when it is an instance of `$class`, otherwise `null`. |

`getObject()` is the type-safe path for cached objects. It takes a class or interface name and PHPStan understands the return type, so you don't need an `instanceof` check afterward:

```php
$profile = $pool->getObject('profile:42', Profile::class);
if ($profile === null) {
    $profile = $this->loadProfile(42);
    $pool->set('profile:42', $profile);
}
```

A subclass or an implementation of a requested interface counts as a match. A scalar, an array, or an object of some other class comes back as `null`.

### Writing

`set(string $key, mixed $value, ?int $expires = null)` returns whether the underlying pool accepted the save.

- `$expires` is an absolute Unix timestamp, not a number of seconds. Pass `time() + 300` for five minutes. Leave it `null` and the entry never expires on its own.
- A timestamp in the past stores an entry that is already expired, so the next `has()` is `false`.
- Setting a value of `null` deletes the key instead of storing `null`. Because `get()` returns `null` for a miss, there is no way to tell a stored `null` from a missing entry anyway, so the class doesn't pretend there is.

`delete(string $key)` removes the entry and returns what the underlying pool's `deleteItem()` returned. Deleting a key that was never set is fine. The `symfony/cache` ArrayAdapter returns `true` for that; other PSR-6 implementations may not.

## Symfony bundle

Add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Gadget\Cache\GadgetCacheBundle::class => ['all' => true],
];
```

The bundle registers `CacheItemPool` as an autowired, autoconfigured service. Its constructor asks for a `Psr\Cache\CacheItemPoolInterface`, which FrameworkBundle aliases to `cache.app`, so in a normal Symfony app it just works. Outside FrameworkBundle, register any PSR-6 pool under that interface name yourself.

The autowired service has an empty namespace. Most of the time you'll want a scoped one, so inject the pool and call `withNamespace()` in your own constructor:

```php
use Gadget\Cache\CacheItemPool;

final class ProfileRepository
{
    private CacheItemPool $cache;

    public function __construct(CacheItemPool $cache)
    {
        $this->cache = $cache->withNamespace('profiles');
    }
}
```

## Testing

`composer test` runs the suite. The pool tests run against a real `symfony/cache` `ArrayAdapter`, including expiry, and the extension test compiles a real `ContainerBuilder` with an adapter registered as the PSR-6 pool. [TESTPLAN.md](TESTPLAN.md) has the manual walkthrough.

## License

MIT. See [LICENSE](LICENSE).
