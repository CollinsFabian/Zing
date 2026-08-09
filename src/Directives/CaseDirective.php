<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

final class CaseDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php case {$expression}: ?>";
    }
}
