# Changelog

All notable changes to this project will be documented in this file.

## [2.0.1] - 2026-07-01

### Added
- `ObjectCache::flush_group()` now delegates to core `wp_cache_flush_group()` on WP 6.1+ backends that advertise support via `wp_cache_supports( 'flush_group' )`. The version-sentinel workaround remains the fallback for backends that don't.
- Native-backend read/write paths skip the internal envelope so values are stored raw. Legacy envelopes left behind by the fallback path are unwrapped transparently on read.

### Changed
- Minimum WordPress version bumped from **5.0** to **6.1**.
- `ObjectCache` refactored: public methods reduced to thin dispatchers; native and fallback paths split into single-responsibility private helpers (`get_native` / `get_fallback` / `set_fallback` / `flush_group_fallback`) and small utilities (`prefix`, `version_key`, `ensure_version`, `wrap`, `is_envelope`).

### Docs
- README and docs site updated to describe the new native/fallback behaviour and the WP 6.1 requirement.

## [2.0.0]

### Changed
- Refactored the library into `Contracts` / `Storage` / `Support` with dependency injection and a full test suite.
- Prefix is now required at construction time (`new Cache( 'my_plugin' )`); previously a shared `duckdev_cache` prefix was hardcoded.
- `can_cache` filter renamed to `{prefix}_can_cache`.

### Fixed
- `remember()` / `forget()` now correctly treat a cached `0`, `''`, `[]`, or `false` as a hit instead of re-running the callback.
