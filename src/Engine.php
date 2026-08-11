<?php

declare(strict_types=1);

namespace Zing;

use Zing\Compiler\CommentCompiler;
use Zing\Compiler\DirectiveCompiler;
use Zing\Compiler\EchoCompiler;
use Zing\Compiler\VerbatimCompiler;
use Zing\Directives\AttributesDirective;
use Zing\Directives\BooleanAttributeDirective;
use Zing\Directives\BreakDirective;
use Zing\Directives\CaseDirective;
use Zing\Directives\ClassDirective;
use Zing\Directives\ContinueDirective;
use Zing\Directives\DefaultDirective;
use Zing\Directives\ElseIfDirective;
use Zing\Directives\EmptyDirective;
use Zing\Directives\EndSectionDirective;
use Zing\Directives\ExtendsDirective;
use Zing\Directives\ForDirective;
use Zing\Directives\ForeachDirective;
use Zing\Directives\IfDirective;
use Zing\Directives\IncludeDirective;
use Zing\Directives\IssetDirective;
use Zing\Directives\JsonDirective;
use Zing\Directives\LiteralDirective;
use Zing\Directives\OnceDirective;
use Zing\Directives\PhpDirective;
use Zing\Directives\SectionDirective;
use Zing\Directives\StyleDirective;
use Zing\Directives\SwitchDirective;
use Zing\Directives\UnlessDirective;
use Zing\Directives\WhileDirective;
use Zing\Directives\YieldDirective;
use Zing\Exception\CompilationException;
use Zing\Section\SectionStack;

/**
 * Compile-to-PHP template engine.
 *
 * Pipeline: .zing template -> compile (echo pass + directive pass) -> cache compiled PHP to disk -> include + extract(vars)
 *
 * Extension is deliberately ".zing" rather than borrowing Blade's
 * ".blade.php" — pick whatever suffix you want templates resolved by,
 * this is just the default.
 */
final class Engine
{
    /** @var array<string, DirectiveCompiler> */
    private array $compilers = [];
    private ?SectionStack $sectionStack = null;
    private array $extendsStack = [];
    private int $renderDepth = 0;

    private EchoCompiler $echoCompiler;
    private CommentCompiler $commentCompiler;
    private VerbatimCompiler $verbatimCompiler;

    public function __construct(private readonly string $viewPath, private readonly string $cachePath, private readonly string $extension = '.zing',)
    {
        $this->echoCompiler = new EchoCompiler();
        $this->commentCompiler = new CommentCompiler();
        $this->verbatimCompiler = new VerbatimCompiler();
        $this->registerDefaults();
    }

    /**
     * Register or override a directive compiler at runtime, e.g.
     * $engine->directive('auth', new AuthDirective());
     */
    public function directive(string $name, DirectiveCompiler $compiler): void
    {
        $this->compilers[$name] = $compiler;
    }

    private function compile(string $source): string
    {
        $source = $this->verbatimCompiler->extract($source);
        $source = $this->commentCompiler->strip($source);
        $source = $this->echoCompiler->compileRaw($source);
        $source = $this->echoCompiler->compileEscaped($source);
        $source = $this->compileDirectives($source);
        $source = $this->verbatimCompiler->restore($source);

        return $source;
    }

    private function compileDirectives(string $source): string
    {
        $result = '';
        $length = \strlen($source);
        $i = 0;

        while ($i < $length) {
            if ($source[$i] !== '@' || !$this->isDirectiveStart($source, $i)) {
                $result .= $source[$i];
                $i++;
                continue;
            }

            // Match the directive name: @word
            $nameStart = $i + 1;
            $nameEnd = $nameStart;

            while ($nameEnd < $length && (ctype_alnum($source[$nameEnd]) || $source[$nameEnd] === '_')) $nameEnd++;

            $name = substr($source, $nameStart, $nameEnd - $nameStart);
            if ($name === '') {
                // Bare '@' with no identifier following — pass through literally.
                $result .= $source[$i];
                $i++;
                continue;
            }

            $cursor = $nameEnd;
            $expression = '';

            // Skip whitespace between the directive name and an optional '('
            $peek = $cursor;
            while ($peek < $length && ctype_space($source[$peek])) $peek++;

            if ($peek < $length && $source[$peek] === '(') [$expression, $cursor] = $this->extractParenExpression($source, $peek);
            else $cursor = $nameEnd;

            if (!isset($this->compilers[$name])) throw CompilationException::unknownDirective($name);

            $result .= $this->compilers[$name]->compile($expression);
            $i = $cursor;
        }

        return $result;
    }

    /**
     * Extracts the contents between a directive's opening '(' and its matching closing ')', tracking nesting depth and skipping over
     * string literals so a ')' inside a quoted argument (or a nested function call) doesn't terminate the match early.
     *
     * @param int $openParenIndex Index of the opening '(' in $source.
     * @return array{0: string, 1: int} [expression contents, index just past the matching ')']
     */
    private function extractParenExpression(string $source, int $openParenIndex): array
    {
        $length = strlen($source);
        $depth = 0;
        $i = $openParenIndex;
        $contentStart = $openParenIndex + 1;
        $inString = null; // null, or the quote char currently open

        while ($i < $length) {
            $char = $source[$i];

            if ($inString !== null) {
                if ($char === '\\') {
                    // Skip escaped character (\' or \\ etc.) inside the string.
                    $i += 2;
                    continue;
                }

                if ($char === $inString) $inString = null;

                $i++;
                continue;
            }

            if ($char === "'" || $char === '"') {
                $inString = $char;
                $i++;
                continue;
            }

            if ($char === '(') $depth++;
            elseif ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $contents = substr($source, $contentStart, $i - $contentStart);
                    return [$contents, $i + 1];
                }
            }

            $i++;
        }

        throw new \RuntimeException("Unclosed directive expression starting at offset {$openParenIndex}");
    }

    /**
     * Guards against matching '@' inside things like email addresses or literal text that happens to contain '@word' — only treat it as a directive if a letter/underscore immediately follows.
     */
    private function isDirectiveStart(string $source, int $atIndex): bool
    {
        $next = $source[$atIndex + 1] ?? '';
        return $next !== '' && (ctype_alpha($next) || $next === '_');
    }

    public function sectionStack(): SectionStack
    {
        return $this->sectionStack ??= new SectionStack();
    }

    public function extend(string $template): void
    {
        $this->extendsStack[] = $template;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function evaluate(string $compiledPath, array $data): string
    {
        $__engine = $this;
        $__onceRendered = [];
        extract($data);

        $stackDepthBefore = \count($this->extendsStack);

        ob_start();
        include $compiledPath;
        $content = ob_get_clean();

        // Only react if THIS script's execution pushed a new entry —
        // an entry that already existed before we started (e.g. from
        // an outer @extends still pending while we render an @include)
        // belongs to that outer call, not us.
        if (\count($this->extendsStack) > $stackDepthBefore) {
            $parent = array_pop($this->extendsStack);
            return $this->render($parent, $data);
        }

        return $content;
    }

    /**
     * Render a template (dot notation, e.g. "pages.settings") with
     * the given data and return the resulting HTML string.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        if ($this->renderDepth === 0) {
            $this->sectionStack = new SectionStack();
        }

        $this->renderDepth++;

        try {
            $sourcePath = $this->resolvePath($template);

            if (!is_file($sourcePath)) throw CompilationException::templateNotFound($template, $sourcePath);

            $compiledPath = $this->getCompiledPath($template);
            if ($this->needsRecompile($sourcePath, $compiledPath)) {
                $compiled = $this->compile(file_get_contents($sourcePath));
                $this->writeCompiled($compiledPath, $compiled);
            }

            return $this->evaluate($compiledPath, $data);
        } finally {
            $this->renderDepth--;
        }
    }

    private function needsRecompile(string $sourcePath, string $compiledPath): bool
    {
        if (!is_file($compiledPath)) return true;
        return filemtime($sourcePath) > filemtime($compiledPath);
    }

    private function writeCompiled(string $compiledPath, string $compiled): void
    {
        $dir = dirname($compiledPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        file_put_contents($compiledPath, $compiled);
    }

    private function resolvePath(string $template): string
    {
        $relative = str_replace('.', DIRECTORY_SEPARATOR, $template) . $this->extension;

        return rtrim($this->viewPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
    }

    private function getCompiledPath(string $template): string
    {
        $hash = md5($template);

        return rtrim($this->cachePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $hash . '.php';
    }

    private function registerDefaults(): void
    {
        $endIf = new LiteralDirective('<?php endif; ?>');
        $endForeach = new LiteralDirective('<?php endforeach; ?>');
        $endFor = new LiteralDirective('<?php endfor; ?>');

        $this->compilers['if'] = new IfDirective();
        $this->compilers['elseif'] = new ElseIfDirective();
        $this->compilers['else'] = new LiteralDirective('<?php else: ?>');
        $this->compilers['endif'] = $endIf;

        $this->compilers['switch'] = new SwitchDirective();
        $this->compilers['case'] = new CaseDirective();
        $this->compilers['default'] = new DefaultDirective();
        $this->compilers['endswitch'] = new LiteralDirective('<?php endswitch; ?>');

        $this->compilers['unless'] = new UnlessDirective();
        $this->compilers['endunless'] = $endIf;

        $this->compilers['isset'] = new IssetDirective();
        $this->compilers['endisset'] = $endIf;

        $this->compilers['empty'] = new EmptyDirective();
        $this->compilers['endempty'] = $endIf;

        $this->compilers['foreach'] = new ForeachDirective();
        $this->compilers['endforeach'] = $endForeach;

        $this->compilers['for'] = new ForDirective();
        $this->compilers['endfor'] = $endFor;

        $this->compilers['php'] = new PhpDirective();
        $this->compilers['endphp'] = new LiteralDirective('?>');

        $this->compilers['break'] = new BreakDirective();
        $this->compilers['continue'] = new ContinueDirective();

        $this->compilers['include'] = new IncludeDirective();

        $this->compilers['class'] = new ClassDirective();
        $this->compilers['style'] = new StyleDirective();
        $this->compilers['json'] = new JsonDirective();

        $this->compilers['checked'] = new BooleanAttributeDirective('checked');
        $this->compilers['selected'] = new BooleanAttributeDirective('selected');
        $this->compilers['disabled'] = new BooleanAttributeDirective('disabled');
        $this->compilers['required'] = new BooleanAttributeDirective('required');
        $this->compilers['readonly'] = new BooleanAttributeDirective('readonly');

        $this->compilers['while'] = new WhileDirective();
        $this->compilers['endwhile'] = new LiteralDirective('<?php endwhile; ?>');

        $this->compilers['once'] = new OnceDirective();
        $this->compilers['endonce'] = $endIf;
        $this->compilers['attributes'] = new AttributesDirective();

        $this->compilers['extends'] = new ExtendsDirective();
        $this->compilers['section'] = new SectionDirective();
        $this->compilers['endsection'] = new EndSectionDirective();
        $this->compilers['yield'] = new YieldDirective();
    }
}
