<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

final class ContinueDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        if ($expression === '') {
            return '<?php continue; ?>';
        }

        return "<?php if({$expression}) continue; ?>";
    }
}
