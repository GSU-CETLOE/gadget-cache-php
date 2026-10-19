# Test Plan

Walk through this list before tagging a release or merging a change that touches `composer.json`, the analyzers, or the test suite. Run every command from the repository root.

## Setup

- [ ] `composer install` finishes without errors and without asking to change the lock file.
- [ ] `composer validate` reports the manifest is valid.
- [ ] `working/` exists after the first run and is not shown in `git status`.

## Automated checks

- [ ] `composer phpcs` reports no errors and no warnings for `src/`, `bundle/`, and `tests/`.
- [ ] `composer phpstan` reports `No errors` at level 10 for `src/`, `bundle/`, and `tests/`.
- [ ] `composer test` passes with no warnings, deprecations, or notices in the output.
- [ ] `composer check` runs all three in order and exits 0.

## Functional coverage

Confirm each area has passing tests under `tests/` that cover the listed behaviors.

- [ ] `CacheItemPool::key()`: returns the SHA-256 of the namespace parts and key joined by `::`; with no namespace it hashes the bare key.
- [ ] `CacheItemPool::withNamespace()`: returns a different instance on the same adapter; the new namespace replaces the old one; the original pool is unchanged.
- [ ] `CacheItemPool::has()` and `get()`: `false` and `null` for a missing key; `true` and the value after `set()`.
- [ ] `CacheItemPool::set()`: returns `true`; stores under the hashed key and not the raw one; overwrites an existing value; a `null` value deletes the key; a future timestamp keeps the value; a past timestamp leaves the entry expired; no timestamp sets no expiry; ints, floats, bools, and arrays round-trip unchanged.
- [ ] `CacheItemPool::delete()`: removes the entry and returns `true`; returns `true` for a key that was never set.
- [ ] `CacheItemPool::getObject()`: returns the object when it matches the class; accepts a subclass or interface match; `null` for a different class, a non-object value, or a missing key.
- [ ] Namespaces: the same key in two namespaces holds two separate values and deleting one leaves the other; two pools with the same namespace on one adapter share entries.
- [ ] `GadgetCacheBundle`: extends `Bundle` and exposes `GadgetCacheExtension` under the alias `gadget_cache`.
- [ ] `GadgetCacheExtension`: registers `CacheItemPool` as an autowired, autoconfigured service; with a PSR-6 pool registered under `CacheItemPoolInterface`, a compiled container builds a working `CacheItemPool`.

## Consuming the package

- [ ] In a scratch project, `composer require gsu-cetloe/gadget-cache-php` installs `gsu-cetloe/gadget-core-php`, `gsu-cetloe/symfony-bundle`, and `psr/cache` 3.
- [ ] In a scratch Symfony app with `GadgetCacheBundle` enabled, a service that type-hints `CacheItemPool` autowires and `set()` followed by `get()` returns the value through `cache.app`.
- [ ] In that same app, restarting the process and calling `get()` again returns the value when `cache.app` is backed by a persistent adapter.

## Known gaps

- The suite uses `symfony/cache`'s `ArrayAdapter`. Expiry and `deleteItem()` return values are checked against that adapter only; other PSR-6 implementations may differ on the return value of deleting a missing key.
- The FrameworkBundle alias from `CacheItemPoolInterface` to `cache.app` is not exercised here. The extension test registers its own adapter under the interface. The manual Symfony step above covers the real wiring.
- Tests run on whatever PHP is installed locally. Composer resolves dependencies as if PHP were 8.1.0, but the behavior checked here is that of the local interpreter.
