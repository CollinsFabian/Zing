<?php

declare(strict_types=1);

namespace Zing\Directives;

use Zing\Compiler\DirectiveCompiler;

final class EndSectionDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return '<?php $__engine->sectionStack()->end(); ?>';
    }
}
