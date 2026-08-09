<?php

declare(strict_types=1);

namespace Zing\Section;

/**
 * Backing store for @extends / @section / @endsection / @yield.
 *
 * Not wired up yet — layout inheritance needs the child template to
 * run first (populating sections), then the parent layout to run and
 * pull from what was captured. That two-pass control flow is the
 * trickiest part of the engine and deserves its own design pass once
 * @if/@foreach/echo are working end-to-end.
 *
 * Rough shape, for later:
 *   - startSection(string $name): starts an output buffer
 *   - endSection(): closes the buffer, stores content under the name
 *   - yieldSection(string $name, string $default = ''): returns stored content
 *   - extends(string $layout): marks which layout template wraps this one
 */
final class SectionStack
{
    // Intentionally empty for now.
}
