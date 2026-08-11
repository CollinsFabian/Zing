<?php

declare(strict_types=1);

namespace Zing\Section;

/**
 * Backing store for @section / @endsection / @yield.
 *
 * One instance is shared across a full `render()` call tree — the child template populates it via `start()/end()`, the parent template (loaded via `@extends`) reads it back via `yield()`.
 *
 * Engine is responsible for giving each *top-level* `render()` call a fresh instance so section content from a previous page never leaks into the next.
 */
final class SectionStack
{
    /** @var array<string, string> */
    private array $sections = [];

    /** @var list<string> */
    private array $openStack = [];

    public function start(string $name): void
    {
        $this->openStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->openStack);
        if ($name === null) throw new \RuntimeException('@endsection with no matching @section.');

        $this->sections[$name] = ob_get_clean();
    }

    public function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return isset($this->sections[$name]);
    }
}
