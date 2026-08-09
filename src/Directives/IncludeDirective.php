<?php

declare(strict_types=1);

namespace Zing\Directives;

use Zing\Compiler\DirectiveCompiler;

/**
 * Include Directive
 *
 * Compiles `@include('partials.notification', ['count' => 3])` into a runtime call back into the Engine, so included templates go through the same compile+cache path as the parent.
 *
 * Included templates share the same SectionStack/extends-stack as the calling template (see *Engine::evaluate()*) — verified against `@extends/@section`.
 */
final class IncludeDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo \$__engine->render({$expression}); ?>";
    }
}
