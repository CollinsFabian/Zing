<?php

declare(strict_types=1);

namespace Zing\Compiler;

/**
 * Extracts `@verbatim ... @endverbatim` blocks before any other compilation pass runs, replaces them with placeholder tokens, and restores the raw content after everything else has compiled.
 * Content inside is never touched by echo or directive parsing.
 */
final class VerbatimCompiler
{
    /** @var array<string, string> */
    private array $blocks = [];

    public function extract(string $source): string
    {
        $this->blocks = [];

        return preg_replace_callback(
            '/@verbatim\s*(.*?)\s*@endverbatim/s',
            function (array $matches): string {
                $token = '__ZING_VERBATIM_' . count($this->blocks) . '__';
                $this->blocks[$token] = $matches[1];

                return $token;
            },
            $source
        );
    }

    public function restore(string $compiled): string
    {
        return strtr($compiled, $this->blocks);
    }
}
