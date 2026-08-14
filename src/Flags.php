<?php

namespace Zing;

final class Flags
{
    public const NONE         = 0;
    public const LOG_ERRORS   = 1 << 0; // 1
    public const STRICT_MODE  = 1 << 1; // 2 - throw instead of falling back to stale
    public const DEBUG        = 1 << 2; // 4 - verbose compile output, skip cache entirely
    public const NO_CACHE     = 1 << 3; // 8 - always recompile, useful for local dev
}
