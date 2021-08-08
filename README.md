<p align="center">
<a href="http://duckdev.com" target="_blank">
    <img width="200px" src="https://duckdev.com/wp-content/uploads/2020/12/cropped-duckdev-logo-mid.png">
</a>
</p>

# WP Cache Helper

WP Cache Helper is a simple WordPress library class to introduce convenient new caching functions.

Built to support group cache flush for WordPress' object cache, which is [not supported by core yet](https://core.trac.wordpress.org/ticket/4476).

* Inspired from [WP Cache Remember](https://github.com/stevegrunwell/wp-cache-remember).

This helper can simplify something like this:

```php
function do_something() {
    $cache_key = 'some-cache-key';
    $cached    = wp_cache_get( $cache_key );

    // Return the cached value.
    if ( $cached ) {
        return $cached;
    }

    // Do all the work to calculate the value.
    $value = a_whole_lotta_processing();

    // Cache the value.
    wp_cache_set( $cache_key, $value );

    return $value;
}
```

That pattern works well, but there's a lot of repeated code. This package draws inspiration from [Laravel's `Cache::remember()` method](https://laravel.com/docs/5.6/cache#cache-usage); using `Cache::remember()`, the same code from above becomes:

```php
// Use this as a global variable or something.
$cache = new \DuckDev\Cache\Cache();

function do_something() {
    return $cache->remember( 'some-cache-key', function () {
        return a_whole_lotta_processing();
    } );
}
```

## Installation

The recommended way to install this library in your project is [via Composer](https://getcomposer.org/):

```sh
$ composer require duckdev/wp-cache-helper
```

## Usage

WP Cache Remember provides the following functions for WordPress:

* [`$cache->remember()`](#$cache->remember())
* [`$cache->forget()`](#$cache->forget())
* [`$cache->persist()`](#$cache->persist())
* [`$cache->cease()`](#$cache->cease())
* [`$cache->flush_group()`](#$cache->flush_group())
* [`$cache->flush()`](#$cache->flush())

Each function checks the response of the callback for a `WP_Error` object, ensuring you're not caching temporary errors for long periods of time. PHP Exceptions will also not be cached.

### $cache->remember()

Retrieve a value from the object cache. If it doesn't exist, run the `$callback` to generate and cache the value.

#### Parameters

<dl>
    <dt>(string) $key</dt>
    <dd>The cache key.</dd>
    <dt>(callable) $callback</dt>
    <dd>The callback used to generate and cache the value.</dd>
    <dt>(string) $group</dt>
    <dd>Optional. The cache group. Default is empty.</dd>
    <dt>(int) $expire</dt>
    <dd>Optional. The number of seconds before the cache entry should expire. Default is 0 (as long as possible).</dd>
</dl>

#### Example

```php
function get_latest_posts() {
    return $cache->remember( 'latest_posts', function () {
        return new WP_Query( array(
            'posts_per_page' => 5,
            'orderby'        => 'post_date',
            'order'          => 'desc',
        ) );
    }, 'my-cache-group', HOUR_IN_SECONDS );
}
```

### $cache->forget()

Retrieve and subsequently delete a value from the object cache.

#### Parameters

<dl>
    <dt>(string) $key</dt>
    <dd>The cache key.</dd>
    <dt>(string) $group</dt>
    <dd>Optional. The cache group. Default is empty.</dd>
    <dt>(mixed) $default</dt>
    <dd>Optional. The default value to return if the given key doesn't exist in the object cache. Default is null.</dd>
</dl>

#### Example

```php
function show_error_message() {
    $error_message = $cache->forget( 'form_errors', 'my-cache-group', false );

    if ( $error_message ) {
        echo 'An error occurred: ' . $error_message;
    }
}
```

### $cache->persist()

Retrieve a value from transients. If it doesn't exist, run the `$callback` to generate and cache the value.

#### Parameters

<dl>
    <dt>(string) $key</dt>
    <dd>The cache key.</dd>
    <dt>(callable) $callback</dt>
    <dd>The callback used to generate and cache the value.</dd>
    <dt>(string) $site</dt>
    <dd>Should use site transients.</dd>
    <dt>(int) $expire</dt>
    <dd>Optional. The number of seconds before the cache entry should expire. Default is 0 (as long as possible).</dd>
</dl>

#### Example

```php
function get_tweets() {
    $user_id = get_current_user_id();
    $key     = 'latest_tweets_' . $user_id;

    return $cache->persist( $key, function () use ( $user_id ) {
        return get_latest_tweets_for_user( $user_id );
    }, 15 * MINUTE_IN_SECONDS );
}
```

### $cache->cease()

Retrieve and subsequently delete a value from the transient cache.

#### Parameters

<dl>
    <dt>(string) $key</dt>
    <dd>The cache key.</dd>
    <dt>(string) $site</dt>
    <dd>Should use site transients.</dd>
    <dt>(mixed) $default</dt>
    <dd>Optional. The default value to return if the given key doesn't exist in transients. Default is null.</dd>
</dl>

### $cache->flush_group()

Flush a cache group items. Use this and do not flush entire cache.

#### Parameters

<dl>
    <dt>(string) $group</dt>
    <dd>The cache group name.</dd>
</dl>

### $cache->flush()

Wrapper for `wp_cache_flush` to check if other method is available for flushing if `wp_cache_flush` is disabled.

### Credits
* Maintained by [Joel James](https://github.com/joel-james/)

### License

[GPLv2+](http://www.gnu.org/licenses/gpl-2.0.html)
