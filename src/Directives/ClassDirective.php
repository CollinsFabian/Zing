<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

/**
 * @class(['card', 'card-active' => $isActive, 'card-error' => $hasErrors])
 * -> outputs class="card card-active" if $isActive is true, etc.
 * Unkeyed entries are always included; keyed entries are conditional.
 */
final class ClassDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo 'class=\"' . implode(' ', array_filter(array_map(
            fn(\$k, \$v) => is_int(\$k) ? \$v : (\$v ? \$k : null),
            array_keys({$expression}), {$expression}
        ))) . '\"'; ?>";
    }
}
