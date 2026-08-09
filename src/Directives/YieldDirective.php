<?php

declare(strict_types=1);

namespace Zing\Directives;

use Zing\Compiler\DirectiveCompiler;

/**
 * Yield Directive
 *
 * `@yield('content')`                -> no default, empty string if unset
 *
 * `@yield('sidebar', '<p>none</p>')` -> falls back if child never defined it
 *
 * `$expression` is passed straight through since it's already a valid argument list matching
 * ```php
 * SectionStack::yield(string $name, string $default = '')
 * ```
 */
final class YieldDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo \$__engine->sectionStack()->yield({$expression}); ?>";
    }
}
