<?php

declare(strict_types=1);

namespace Zing\Exception;

use RuntimeException;

final class CompilationException extends RuntimeException
{
    public static function templateNotFound(string $template, string $resolvedPath): self
    {
        return new self("Template [{$template}] not found at path: {$resolvedPath}");
    }

    public static function unknownDirective(string $name): self
    {
        return new self("Unknown directive [@{$name}] — no DirectiveCompiler registered for it.");
    }
}
