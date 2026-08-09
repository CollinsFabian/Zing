<?php

declare(strict_types=1);

namespace Zing\Tests;

use PHPUnit\Framework\TestCase;
use Zing\Engine;

final class EngineTest extends TestCase
{
    private string $viewPath;
    private string $cachePath;

    protected function setUp(): void
    {
        $this->viewPath = sys_get_temp_dir() . '/zing-views-' . uniqid();
        $this->cachePath = sys_get_temp_dir() . '/zing-cache-' . uniqid();

        mkdir($this->viewPath, 0755, true);
        mkdir($this->cachePath, 0755, true);
    }

    public function testEscapedEchoRenders(): void
    {
        file_put_contents($this->viewPath . '/greeting.zing', 'Hello, {{ $name }}!');

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertSame('Hello, world!', $engine->render('greeting', ['name' => 'world']));
    }

    public function testEscapedEchoEscapesHtml(): void
    {
        file_put_contents($this->viewPath . '/greeting.zing', '{{ $name }}');

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertSame(
            '&lt;script&gt;',
            $engine->render('greeting', ['name' => '<script>'])
        );
    }

    public function testIfDirective(): void
    {
        file_put_contents(
            $this->viewPath . '/admin.zing',
            '@if($isAdmin) admin @else guest @endif'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertStringContainsString('admin', $engine->render('admin', ['isAdmin' => true]));
        $this->assertStringContainsString('guest', $engine->render('admin', ['isAdmin' => false]));
    }

    public function testForeachDirective(): void
    {
        file_put_contents(
            $this->viewPath . '/list.zing',
            '@foreach($items as $item){{ $item }}@endforeach'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertSame('abc', $engine->render('list', ['items' => ['a', 'b', 'c']]));
    }

    public function testCommentsAreStripped(): void
    {
        file_put_contents(
            $this->viewPath . '/commented.zing',
            'before {{-- {{ $leaked }} this should vanish --}} after'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertSame('before  after', $engine->render('commented', []));
    }

    public function testVerbatimBlockIsUntouched(): void
    {
        file_put_contents(
            $this->viewPath . '/verbatim.zing',
            '@verbatim
                <div>{{ vueVar }}</div>
            @endverbatim'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertSame('<div>{{ vueVar }}</div>', $engine->render('verbatim', []));
    }

    public function testIncludeDoesNotHijackPendingExtends(): void
    {
        mkdir($this->viewPath . '/layouts');
        mkdir($this->viewPath . '/partials');

        file_put_contents($this->viewPath . '/layouts/main.zing', 'LAYOUT: @yield(\'content\')');
        file_put_contents($this->viewPath . '/partials/header.zing', 'HEADER');
        file_put_contents(
            $this->viewPath . '/page.zing',
            '@extends(\'layouts.main\')@section(\'content\')@include(\'partials.header\')@endsection'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);

        $this->assertSame('LAYOUT: HEADER', $engine->render('page'));
    }

    public function testNestedParenExpressionCompiles(): void
    {
        file_put_contents($this->viewPath . '/nested.zing', '{{ strtoupper($name) }}');

        $engine = new Engine($this->viewPath, $this->cachePath);
        $this->assertSame('JOHN', $engine->render('nested', ['name' => 'john']));
    }

    public function testDirectiveWithNestedFunctionCallExpression(): void
    {
        file_put_contents(
            $this->viewPath . '/nested-if.zing',
            '@if(strtoupper($name) === \'JOHN\')match@endif'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);
        $this->assertSame('match', $engine->render('nested-if', ['name' => 'john']));
    }

    public function testStringLiteralContainingParenDoesNotBreakMatching(): void
    {
        file_put_contents(
            $this->viewPath . '/paren-string.zing',
            '@if($label === "closing)paren")matched@endif'
        );

        $engine = new Engine($this->viewPath, $this->cachePath);
        $this->assertSame('matched', $engine->render('paren-string', ['label' => 'closing)paren']));
    }
}
