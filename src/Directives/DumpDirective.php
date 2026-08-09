<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

final class DumpDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo '<pre>' . htmlspecialchars(print_r({$expression}, true)) . '</pre>'; ?>";
    }
}
