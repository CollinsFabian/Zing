<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

/**
 * `@style(['display: none' => !$isVisible, 'color: red' => $hasError])` -> outputs
 * ```js
 * style="display: none"
 * ```
 * if the condition is true, etc.
 *
 * Same shape as `@class` but for inline styles, joined with ';'.
 */
final class StyleDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo 'style=\"' . implode('; ', array_filter(array_map(
            fn(\$k, \$v) => is_int(\$k) ? \$k : (\$v ? \$k : null),
            array_keys({$expression}), {$expression}
        ))) . '\"'; ?>";
    }
}
