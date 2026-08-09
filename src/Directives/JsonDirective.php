<?php

declare(strict_types=1);

namespace Zing\Directives;
use Zing\Compiler\DirectiveCompiler;

final class JsonDirective implements DirectiveCompiler
{
    public function compile(string $expression): string
    {
        return "<?php echo json_encode({$expression}, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); ?>";
    }
}
