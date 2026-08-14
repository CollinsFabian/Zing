# Changelog

## [1.1.0] - 2026-08-14

### Added
- `TemplateCache` — dedicated caching class with atomic writes and stale-on-error fallback, replacing `Engine`'s inline mtime-based caching.
- `Flags` bitmask system (`LOG_ERRORS`, `STRICT_MODE`, `DEBUG`, `NO_CACHE`) with `Engine::enable()`/`disable()`, sharing state with `TemplateCache` via reference.

### Changed
- `Engine`'s caching logic (`needsRecompile()`, `writeCompiled()`, private `getCompiledPath()`) removed in favor of `TemplateCache`.

## [1.0.1] - 2026-08-11

### Changed
- Fix workflow tests section in README.
- Also bumped to overide an older 1.0.0 release in Packagist which had been deleted. Current v1.0.0 on github is up-to-date but won't be released to Composer(Packagist).

## [1.0.0] - 2026-08-11

- Initial stable release.
- Blade-inspired `.zing` template syntax.
- Compile-to-PHP with cached output.
- Built-in directives and layout inheritance.
- Custom directive registration.