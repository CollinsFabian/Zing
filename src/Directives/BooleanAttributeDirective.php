<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

/**
 * Emits a boolean HTML attribute (checked, selected, disabled, required) only when the given expression is truthy — avoids the
 * ```
 * <?= $x ? 'checked' : '' ?>
 * ```
 * pattern scattered through raw-PHP views.
 */
final class BooleanAttributeDirective implements DirectiveCompiler
{
    public function __construct(private readonly string $attribute) {}

    public function compile(string $expression): string
    {
        return "<?php echo ({$expression}) ? '{$this->attribute}' : ''; ?>";
    }
}
