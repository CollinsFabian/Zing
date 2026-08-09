<?php

declare(strict_types=1);

namespace Zing\Compiler;

/**
 * Strips `{{-- comment --}}` blocks entirely — they never reach the compiled PHP output, unlike `@php`-block // comments which do.
 *
 * Must run before EchoCompiler, otherwise a comment containing `{{ }}` would get parsed as an echo before the comment strip removes it.
 */
final class CommentCompiler
{
    public function strip(string $source): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    }
}
