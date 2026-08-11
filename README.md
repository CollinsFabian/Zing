[![Tests](https://github.com/CollinsFabian/Zing/actions/workflows/tests.yml/badge.svg)](https://github.com/CollinsFabian/Zing/actions/workflows/tests.yml)

# zi/zing

A Blade-inspired PHP template engine. Compiles `.zing` templates to plain PHP once, caches the compiled output, and includes it on every subsequent render — no runtime AST walking, no per-request parsing overhead.

## Requirements

- PHP `^8.2`

## Installation

```bash
composer require zi/zing
```

## Editor support

[Zing Template Syntax](https://marketplace.visualstudio.com/items?itemName=RexLLins.zing-syntax) is a VS Code extension providing syntax highlighting and snippets for `.zing` files. Install it from the Marketplace, or search "Zing Template Syntax" inside VS Code.

## Quick start

```php
use Zing\Engine;

$engine = new Engine(
    viewPath: __DIR__ . '/resources/views',
    cachePath: __DIR__ . '/storage/zing-cache',
);

echo $engine->render('pages.settings', ['user' => $user]);
```

Template names use dot notation, mapped to directories: `pages.settings` resolves to `resources/views/pages/settings.zing`.

## Architecture

.zing source<br>
↓<br>
**VerbatimCompiler::extract()** — pulls @verbatim blocks out, replaces with placeholder tokens<br>
↓<br>
**CommentCompiler::strip()** — removes {{-- ... --}} entirely<br>
↓<br>
**EchoCompiler::compileRaw()** — {!! !!} →
```php
 <?php echo $x; ?>
 ```
**EchoCompiler::compileEscaped()** — {{ }} →
```php
 <?php echo htmlspecialchars($x, ...); ?>
 ```
↓<br>
**Engine::compileDirectives()** — @directive(...) → PHP, via per-directive DirectiveCompiler<br>
↓<br>
**VerbatimCompiler::restore()** — placeholder tokens swapped back for original raw content<br>
↓<br>
cached .php file (invalidated by source mtime)<br>
↓<br>
include + extract(vars) → output

---

Each directive is its own class implementing `Zing\Compiler\DirectiveCompiler`, living under `Zing\Directives\`. This is the Strategy pattern: adding a new directive means adding a class and one line in `Engine::registerDefaults()` (or calling `$engine->directive('name', new SomeDirective())` at runtime) — never editing the compiler pipeline itself.

Directives with no branching logic (`@endif`, `@endforeach`, `@else`, ...) don't get their own class — they're registered as shared `LiteralDirective` instances, since a dedicated class per fixed-string output is overhead without payoff.

### Directive parsing

`Engine::compileDirectives()` does **not** use a single regex pass. Directive expressions are extracted with a manual paren-depth-tracking scanner (`extractParenExpression()`) that:

- Correctly matches the *outermost* closing `)`, so nested calls like `@if(strtoupper($name) === 'JOHN')` compile correctly instead of truncating at the first `)` encountered.
- Skips over `'...'`/`"..."` string literals (respecting `\'`/`\\` escapes), so a `)` inside a quoted argument doesn't miscount as closing the directive.
- Guards `@` against matching inside things like email addresses — a directive only starts if a letter or underscore immediately follows `@`.

### Compiled output caching

Compiled `.php` files are written to `cachePath`, named by an md5 hash of the template's dot-notation name. Recompilation only happens when the source file's mtime is newer than the compiled file's — editing a `.zing` file invalidates its cache automatically; untouched templates are never recompiled.

## Syntax reference

### Echoing

```php
{{ $user->name }} {{-- escaped via htmlspecialchars --}}

{!! $rawHtml !!} {{-- unescaped, raw output --}}
```

### Comments

```
{{-- this is stripped entirely, never reaches compiled output --}}
```

Comments are removed before any other pass runs, so `{{ }}`/`{!! !!}` sequences written *inside* a comment are never treated as echoes.

### Conditionals

```php
@if($user->isAdmin())
    admin
@elseif($user->isMod())
    mod
@else
    guest
@endif

@unless($user->isBanned())
    welcome
@endunless

@isset($user->avatar)
    has an avatar
@endisset

@empty($course->students)
    no students yet
@endempty
```

`@unless`, `@isset`, and `@empty` all compile to `if(!...)`/`if(isset(...))`/`if(empty(...))` respectively, and all close with `@endif` (or their own alias — `@endunless`, `@endisset`, `@endempty` — which all resolve to the same closing output).

### Loops

```php
@foreach($courses as $course)
    <li>{{ $course->title }}</li>
@endforeach

@for($i = 0; $i < 10; $i++)
    {{ $i }}
@endfor

@while($queue->hasNext())
    {{ $queue->next() }}
@endwhile

@foreach($items as $item)
    @continue($item->isHidden())
    @break($item->isLast())

    {{ $item->name }}
@endforeach
```

`@break`/`@continue` accept an optional condition; called with no arguments they emit a bare `break;`/`continue;`.

### Switch

```php
@switch($course->status)
    @case('draft')
        <span class="badge-draft">Draft</span>
        @break
    @case('published')
        <span class="badge-live">Live</span>
        @break
    @default
        <span class="badge-unknown">Unknown</span>
@endswitch
```

### Once

```php
@foreach($courses as $course)
    @once
        <script>window.courseInit = () => { /* shared setup, emitted only on first pass */ };</script>
    @endonce
        <div>{{ $course->title }}</div>
@endforeach
```

Guards a block so it renders at most once per `render()` call, keyed internally by the compiled block's line number — safe to use inside loops without duplicating output.

### Attribute helpers

```php
<div @class(['card', 'card-active' => $isActive, 'card-error' => $hasErrors])>
    <option value="draft" @selected($course->status === 'draft')>Draft</option>
    <input type="checkbox" @checked($course->isPublished) />
    <input type="text" @disabled(!$user->canEdit) />
    <input type="text" @readonly($isLocked) />
    <input type="text" @required($field->isRequired()) />
    <div @style(['display: none' => !$isVisible, 'color: red' => $hasError])>
        <button @attributes(['type' => 'submit', 'data-course-id' => $course->id, 'aria-label' => $label])>
    </div>
</div>
```

- `@class` / `@style` accept an array; unkeyed entries always render, keyed entries render only when their value is truthy.
- `@checked` / `@selected` / `@disabled` / `@readonly` / `@required` emit the bare HTML boolean attribute only when the expression is truthy (all backed by one shared `BooleanAttributeDirective`).
- `@attributes` spreads an associative array as key="value" pairs, htmlspecialchars-escaping each value, and skips any key whose value is null or false.

### Includes

```php
@include('partials.notification', ['count' => 3])
```

Compiles to a call back into `Engine::render()`, so included templates go through the exact same compile+cache path as top-level templates. Included templates share the calling template's `SectionStack` and extends-stack — verified so that an @include nested inside a `@section` block doesn't get mistaken for the outer template's pending `@extends`.

### Layout inheritance

```php
{{-- layouts/main.zing --}}
<!DOCTYPE html>
<html>
<head><title>{{ $title }}</title></head>
<body>
    <main>@yield('content')</main>
    @yield('sidebar', '<p>No sidebar</p>')
</body>
</html>
```

```php
{{-- pages/settings.zing --}}
@extends('layouts.main')

@section('content')
    <h1>Settings</h1>
    <p>{{ $user->name }}</p>
@endsection
```

`@extends` records the parent template to render once the child finishes executing — it does not render immediately in place. `@section`/`@endsection` capture their block via output buffering into a shared SectionStack rather than printing directly. `@yield('name', 'default')` reads a captured section back, falling back to the given default (or an empty string) if the child never defined it.

Convention, not enforced: a template using `@extends` should contain only `@section` blocks — any markup outside of a `@section` in such a template is silently discarded rather than raising an error, since it never gets captured into the stack the parent later reads from.

### Verbatim

```php
@verbatim
    <script type="text/x-template">
        <div>{{ vueVariable }}</div>
    </script>
@endverbatim
```

Content between `@verbatim`/`@endverbatim` is extracted before the comment/echo/directive passes run and restored untouched afterward — use this for embedding another templating syntax (Vue, Alpine x-data strings, JS template literals) that also uses {{ }}, without Zing trying to compile it.

### Raw PHP escape hatch

```php
@php
    $total = array_sum($prices);
@endphp
```

Compiles to bare <?php / ?> tags. <br>
Note this runs through the echo/comment passes like any other template text — a raw PHP string literal containing {{ }} would be affected. Low risk in practice, but not special-cased.

## Directive registry (built-in)

| Directive                                                        | Closes with  | Notes
| :--------                                                        | :--------    | :--------
`@if` / `@elseif` / `@else`                                        | `@endif`
`@unless`                                                          | `@endunless` | inverse of `@if`
`@isset`                                                           | `@endisset`
`@empty`                                                           | `@endempty`
`@once`                                                            | `@endonce`   | keyed by compiled line number
`@foreach`                                                         | `@endforeach`
`@for`                                                             | `@endfor`
`@while`                                                           | `@endwhile`
`@switch` / `@case` / `@default`                                   | `@endswitch` | pair `@case` with `@break`
`@break` / `@continue`                                             | —            | optional condition argument
`@php`                                                             | `@endphp`    | raw PHP passthrough
`@include`                                                         | —            | `Engine::render()` re-entry
`@extends`                                                         | —            | defers to parent template
`@section`                                                         | `@endsection`
`@yield`                                                           | —            | reads from `SectionStack`
`@class` / `@style`                                                | —            | conditional attribute values
`@checked` / `@selected` / `@disabled` / `@readonly` / `@required` | —            |boolean HTML attributes
`@attributes`                                                      | —            | spreads an associative array
---

### Extending

Register a custom directive at runtime:

```php
$engine->directive('auth', new AuthDirective());
```

Any class implementing `Zing\Compiler\DirectiveCompiler`:

```php
interface DirectiveCompiler
{
    public function compile(string $expression): string;
}
```

`$expression` is the raw text between the directive's parentheses (already balanced and string-literal-safe, per the paren-depth scanner) — return the PHP that should replace `@directive(...)` in the compiled output.

## Not yet implemented
`$loop` variable inside `@foreach` (index, first, last, count, remaining) — flagged as a bigger quality-of-life addition, requires wrapping the compiled loop body rather than a single directive.
Comma-splitting/argument-array parsing beyond what PHP's own parser handles at compile time — `@include`'s second argument works because it's spliced as raw PHP, not because Zing parses it itself.

## Testing

```sh
composer install
composer test
```

**Or via PHPUnit directly:**


```sh
vendor/bin/phpunit tests
vendor/bin/phpunit --filter testIfDirective tests/EngineTest.php
```

## Package naming

**`zi/`** is a vendor prefix shared across my packages (**`zi/ziro`**, **`zi/zquery`**, **`zi/zing`**) — it does not appear in the namespace. Everything in this package lives under **`Zing\`**.