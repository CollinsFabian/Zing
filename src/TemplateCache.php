<?php

namespace Zing;

final class TemplateCache
{
    private int $flags;

    /**
     * @param int $flags Note: mutation on Engine silently syncs here
     */
    public function __construct(private readonly string $cacheDir, int &$flags,)
    {
        $this->flags = &$flags;
        if (!is_dir($this->cacheDir)) mkdir($this->cacheDir, 0755, recursive: true);
    }

    /**
     * Returns the path to a valid compiled file for $sourcePath, compiling and storing it first if missing or stale.
     *
     * @param callable(string $source): string $compile Receives raw template source, returns compiled PHP code.
     */
    public function getCompiledPath(string $sourcePath, callable $compile): string
    {
        $compiledPath = $this->pathFor($sourcePath);

        if (!($this->flags & Flags::NO_CACHE) && $this->isValid($sourcePath, $compiledPath)) return $compiledPath;

        try {
            $compiled = $compile(file_get_contents($sourcePath));
        } catch (\Throwable $e) {
            if ($this->flags & Flags::LOG_ERRORS) error_log("[Zing]: Compile error for {$sourcePath}: {$e->getMessage()}");

            if (!($this->flags & Flags::STRICT_MODE) && is_file($compiledPath)) return $compiledPath;

            throw $e;
        }

        $this->write($compiledPath, $compiled);
        return $compiledPath;
    }

    public function isValid(string $sourcePath, ?string $compiledPath = null): bool
    {
        $compiledPath ??= $this->pathFor($sourcePath);

        if (!is_file($compiledPath) || !is_file($sourcePath)) return false;
        return filemtime($compiledPath) >= filemtime($sourcePath);
    }

    public function forget(string $sourcePath): void
    {
        $compiledPath = $this->pathFor($sourcePath);

        if (is_file($compiledPath)) unlink($compiledPath);
    }

    public function clear(): void
    {
        foreach (glob($this->cacheDir . '/*.php') as $file) unlink($file);
    }

    private function pathFor(string $sourcePath): string
    {
        $hash = substr(md5($sourcePath), 0, 16);

        return $this->cacheDir . "/{$hash}.php";
    }

    private function write(string $compiledPath, string $compiled): void
    {
        $uniq = uniqid('', true);
        $tmpPath = "{$compiledPath}.{$uniq}.tmp";

        file_put_contents($tmpPath, $compiled);
        rename($tmpPath, $compiledPath);
    }
}
