<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

final class UnlessDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php if(!({$expression})): ?>";
    }
}
