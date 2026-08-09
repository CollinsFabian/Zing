<?php

declare(strict_types=1);

namespace Zing\Directives;

use Zing\Compiler\DirectiveCompiler;

/**
 * `@extends('layouts.main')` doesn't render anything itself — it just tells the Engine which parent template to render once this child template finishes executing (see `Engine::evaluate()`).
 */
final class ExtendsDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php \$__engine->extend({$expression}); ?>";
    }
}
