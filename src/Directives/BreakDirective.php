<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

final class BreakDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        if ($expression === '') {
            return '<?php break; ?>';
        }

        return "<?php if({$expression}) break; ?>";
    }
}
