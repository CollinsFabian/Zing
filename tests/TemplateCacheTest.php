<?php

declare(strict_types=1);

namespace Zing\Tests;

use PHPUnit\Framework\TestCase;
use Zing\Flags;
use Zing\TemplateCache;

final class TemplateCacheTest extends TestCase
{
    private string $sourcePath;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/zing-cache-test-' . uniqid();
        $this->sourcePath = sys_get_temp_dir() . '/zing-source-test-' . uniqid() . '.zing';

        file_put_contents($this->sourcePath, 'hello');
    }

    public function testCompilesAndCachesOnFirstCall(): void
    {
        $flags = Flags::NONE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        $calls = 0;
        $compile = function (string $source) use (&$calls) {
            $calls++;
            return "<?php echo '{$source}'; ?>";
        };

        $path1 = $cache->getCompiledPath($this->sourcePath, $compile);
        $path2 = $cache->getCompiledPath($this->sourcePath, $compile);

        $this->assertSame($path1, $path2);
        $this->assertSame(1, $calls, 'Second call should reuse cache, not recompile.');
    }

    public function testNoCacheFlagForcesRecompileEveryCall(): void
    {
        $flags = Flags::NO_CACHE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        $calls = 0;
        $compile = function (string $source) use (&$calls) {
            $calls++;
            return "<?php echo '{$source}'; ?>";
        };

        $cache->getCompiledPath($this->sourcePath, $compile);
        $cache->getCompiledPath($this->sourcePath, $compile);

        $this->assertSame(2, $calls, 'NO_CACHE should bypass the validity check every time.');
    }

    public function testFallsBackToStaleCompiledFileOnErrorByDefault(): void
    {
        $flags = Flags::NONE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        // First: successful compile, produces a real cached file.
        $goodPath = $cache->getCompiledPath($this->sourcePath, fn($s) => "<?php echo 'good'; ?>");

        // Force staleness so the next call attempts recompilation.
        touch($this->sourcePath, time() + 10);

        // Second: compile throws — should fall back to the stale-but-valid file, not throw.
        $resultPath = $cache->getCompiledPath($this->sourcePath, function () {
            throw new \RuntimeException('simulated compile failure');
        });

        $this->assertSame($goodPath, $resultPath);
        $this->assertStringContainsString('good', file_get_contents($resultPath));
    }

    public function testStrictModeThrowsInsteadOfFallingBackToStale(): void
    {
        $flags = Flags::STRICT_MODE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        $cache->getCompiledPath($this->sourcePath, fn($s) => "<?php echo 'good'; ?>");
        touch($this->sourcePath, time() + 10);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('simulated compile failure');

        $cache->getCompiledPath($this->sourcePath, function () {
            throw new \RuntimeException('simulated compile failure');
        });
    }

    public function testThrowsWhenNoStaleFallbackExistsRegardlessOfStrictMode(): void
    {
        $flags = Flags::NONE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        $this->expectException(\RuntimeException::class);

        // No prior successful compile exists, so there's nothing to fall back to.
        $cache->getCompiledPath($this->sourcePath, function () {
            throw new \RuntimeException('first compile ever fails');
        });
    }

    public function testForgetRemovesCachedFile(): void
    {
        $flags = Flags::NONE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        $compiledPath = $cache->getCompiledPath($this->sourcePath, fn($s) => "<?php ?>");
        $this->assertFileExists($compiledPath);

        $cache->forget($this->sourcePath);
        $this->assertFileDoesNotExist($compiledPath);
    }

    public function testClearRemovesAllCachedFiles(): void
    {
        $flags = Flags::NONE;
        $cache = new TemplateCache($this->cacheDir, $flags);

        $cache->getCompiledPath($this->sourcePath, fn($s) => "<?php ?>");
        $cache->clear();

        $this->assertCount(0, glob($this->cacheDir . '/*.php'));
    }
}
