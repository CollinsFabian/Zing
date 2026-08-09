<?php

declare(strict_types=1);

namespace Zing\Compiler;

/**
 * A DirectiveCompiler knows how to turn one @directive's expression into the raw PHP that should replace it in the compiled template.
 *
 * Each directive (@if, @foreach, @section, ...) gets its own implementation — this is the Strategy pattern piece of Zing.
 */
interface DirectiveCompiler
{
    /**
     * @param string $expression The raw text inside the directive's parentheses,
     *
     * **e.g.**
     *
     * _"$user->isAdmin()"_ for `@if($user->isAdmin())`.
     *
     * Empty string for parameterless directives like `@else` / `@endif`.
     */
    public function compile(string $expression): string;
}
