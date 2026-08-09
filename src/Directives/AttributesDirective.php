<?php

declare(strict_types=1);

namespace Zing\Directives;

use Zing\Compiler\DirectiveCompiler;

/**
 * `@attributes(['type' => 'submit', 'data-id' => $id])` -> outputs type="submit" data-id="123", each value htmlspecialchars'd.
 *
 * Skips keys with a null or false value entirely.
 */
final class AttributesDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo implode(' ', array_filter(array_map(
            fn(\$k, \$v) => \$v === null || \$v === false ? null : \$k . '=\"' . htmlspecialchars((string) \$v, ENT_QUOTES, 'UTF-8') . '\"',
            array_keys({$expression}), {$expression}
        ))); ?>";
    }
}
