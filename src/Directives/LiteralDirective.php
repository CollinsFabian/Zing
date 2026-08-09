<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

/**
 * Compiles a directive to a fixed PHP snippet regardless of expression — for closing/no-argument directives like `@endif`, `@endforeach`, `@else`.
 */
final class LiteralDirective implements DirectiveCompiler
{
    public function __construct(private readonly string $output) {}

    public function compile(string $expression): string
    {
        return $this->output;
    }
}
