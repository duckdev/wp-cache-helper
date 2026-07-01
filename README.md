<p align="center">
<a href="http://duckdev.com" target="_blank">
    <img width="200px" src="https://duckdev.com/wp-content/uploads/2020/12/cropped-duckdev-logo-mid.png">
</a>
</p>

# WP Cache Helper

WP Cache Helper is a small WordPress library that wraps the object cache and transient APIs with a callback-style
`remember()` helper, group-flush support for the object cache (delegating to core's `wp_cache_flush_group()` on
WP 6.1+ backends that support it, with a version-sentinel fallback for backends that don't), and per-prefix
scoping so multiple consumers on the same site never collide.

Inspired by [WP Cache Remember](https://github.com/stevegrunwell/wp-cache-remember).

📖 **Full documentation:** [docs.duckdev.com/wp-libraries/wp-cache-helper/overview](https://docs.duckdev.com/wp-libraries/wp-cache-helper/overview)

## Requirements

* PHP 7.4 or higher
* WordPress 6.1+
* Composer

## Installation

```console
composer require duckdev/wp-cache-helper
```

The library autoloads under the `DuckDev\Cache\` namespace via PSR-4.

## Architecture

The library is organised as a tiny container wired up by the entry class `DuckDev\Cache\Cache`. The folder layout
mirrors the namespace:

```
src/
├── Cache.php                     # Container + entry point
├── Contracts/
│   ├── ObjectCacheInterface.php
│   └── TransientCacheInterface.php
├── Storage/
│   ├── ObjectCache.php           # wp_cache_* wrapper + version-based group flush
│   └── TransientCache.php        # (site_)transient wrapper
├── Support/
│   └── KeyPrefixer.php           # Shared key + group prefixing
└── Exceptions/
    └── CacheException.php
```

Services receive their collaborators by constructor injection so they can be unit-tested without WordPress in the loop.
Construction has no side effects.

## Usage

### Initialisation

Each container instance is scoped to a single prefix. Pass any non-empty string the first time you ask for it; the same
prefix returns the same instance on subsequent calls:

```php
$cache = \DuckDev\Cache\Cache::get_instance( 'my_plugin' );
```

You can also instantiate directly (useful for tests where you want to inject custom drivers):

```php
$cache = new \DuckDev\Cache\Cache( 'my_plugin' );
```

Every key, group, and the `{prefix}_can_cache` toggle filter are namespaced under the supplied prefix.

### Provided helpers

| Method                                                              | Backed by               | Purpose                                                  |
|---------------------------------------------------------------------|-------------------------|----------------------------------------------------------|
| [`remember()`](#cache-remember)                                     | Object cache            | Read, or compute + cache on miss.                        |
| [`forget()`](#cache-forget)                                         | Object cache            | Read then delete; return a default on miss.              |
| [`persist()`](#cache-persist)                                       | Transients              | Read, or compute + cache on miss.                        |
| [`cease()`](#cache-cease)                                           | Transients              | Read then delete; return a default on miss.              |
| [`flush_group()`](#cache-flush_group)                               | Object cache            | Invalidate every entry in a group.                       |
| [`flush()`](#cache-flush)                                           | Object cache            | Flush the entire object cache. **Last resort.**          |
| `object_cache()` / `transient_cache()`                              | —                       | Access the underlying driver for finer-grained control.  |

Every callback-based helper checks the return value with `is_wp_error()` and skips caching when one is returned, so a
transient API failure is not memorised.

### Disabling caching

For debugging, return `false` from the `{prefix}_can_cache` filter:

```php
add_filter( 'my_plugin_can_cache', '__return_false' );
```

The second argument is the cache type — `'object'` or `'transient'` — so the two can be toggled independently.

### `Cache::remember()` <a name="cache-remember"></a>

Retrieve a value from the object cache. If it doesn't exist, run the `$callback` to generate and cache the value.

```php
$cache = \DuckDev\Cache\Cache::get_instance( 'my_plugin' );

function get_latest_posts() {
    global $cache;

    return $cache->remember( 'latest_posts', function () {
        return new WP_Query( array(
            'posts_per_page' => 5,
            'orderby'        => 'post_date',
            'order'          => 'desc',
        ) );
    }, 'queries', HOUR_IN_SECONDS );
}
```

Unlike a naive `wp_cache_get()`-then-fall-back pattern, `remember()` distinguishes a legitimately cached `0`, `''`,
`[]`, or `false` from a true miss — the callback only runs when nothing was cached.

### `Cache::forget()` <a name="cache-forget"></a>

Retrieve a value from the object cache then delete it. Returns `$default` on miss.

```php
$error_message = $cache->forget( 'form_errors', 'flash', false );

if ( $error_message ) {
    echo 'An error occurred: ' . esc_html( $error_message );
}
```

### `Cache::persist()` <a name="cache-persist"></a>

Same shape as `remember()` but backed by the transient API.

```php
$cache->persist( 'latest_tweets_' . $user_id, function () use ( $user_id ) {
    return get_latest_tweets_for_user( $user_id );
}, false, 15 * MINUTE_IN_SECONDS );
```

Pass `true` for the third argument to use site-wide (multisite) transients.

Note: transients use boolean `false` as the miss sentinel, so a legitimately cached `false` value is indistinguishable
from a miss. Reach for `remember()` if you need to cache `false`.

### `Cache::cease()` <a name="cache-cease"></a>

Transient counterpart to `forget()`.

### `Cache::flush_group()` <a name="cache-flush_group"></a>

Invalidate every entry stored under a group, without touching the rest of the object cache. On WP 6.1+ with a
persistent object cache backend that advertises `flush_group` support (via `wp_cache_supports( 'flush_group' )`),
this delegates straight to `wp_cache_flush_group()`. Otherwise it falls back to incrementing a per-group version
sentinel — old entries become unreadable on next access.

### `Cache::flush()` <a name="cache-flush"></a>

Wrapper for `wp_cache_flush()` with a fallback to `$wp_object_cache->flush()` when the function is disabled by a
drop-in. **Clears every group on the site**, so use only as a last resort.

## Upgrading from 1.x

* PHP minimum is now 7.4. PHP 5.6/7.0–7.3 are no longer supported.
* The constructor now requires a prefix: `new Cache( 'my_plugin' )`. In 1.x the prefix was a hardcoded
  `duckdev_cache` shared across every consumer.
* The `can_cache` filter is now `{prefix}_can_cache` (e.g. `my_plugin_can_cache`) rather than the shared
  `duckdev_cache_can_cache`.
* `remember()` and `forget()` now correctly treat a cached `0` / `''` / `[]` / `false` as a hit instead of re-running
  the callback.

The public method surface (`remember`, `forget`, `persist`, `cease`, `flush_group`, `flush`) is otherwise unchanged.

## Development

```console
composer install
composer test     # PHPUnit
composer phpcs    # WordPress Coding Standards
```

### Credits
* Maintained by [Joel James](https://github.com/joel-james/)

### License

[GPLv2+](http://www.gnu.org/licenses/gpl-2.0.html)
