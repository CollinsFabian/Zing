<?php

declare(strict_types=1);

namespace Zing\Compiler;

/**
 * Handles the two interpolation forms:
 *
 *   `{{ $expr }}` -> escaped output
 *
 *   `{!! $expr !!}` -> raw/unescaped output
 *
 * Not a DirectiveCompiler — echoes aren't @word(...) directives, they get their own regex pass in Engine::compile() before directives run.
 */
final class EchoCompiler
{
    public function compileEscaped(string $source): string
    {
        return preg_replace(
            '/\{\{\s*(.+?)\s*\}\}/',
            '<?php echo htmlspecialchars($1, ENT_QUOTES, \'UTF-8\'); ?>',
            $source
        );
    }

    public function compileRaw(string $source): string
    {
        return preg_replace(
            '/\{!!\s*(.+?)\s*!!\}/',
            '<?php echo $1; ?>',
            $source
        );
    }
}
