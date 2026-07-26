<?php

namespace App\Enums;

/**
 * The set types Hevy records. Backed by the exact strings the Hevy API uses,
 * so we can compare/store them directly while keeping call sites readable.
 */
enum SetType: string
{
    case Warmup = 'warmup';
    case Normal = 'normal';
    case Failure = 'failure';
    case Dropset = 'dropset';

    /** Warm-up sets don't count toward hard-set volume or tonnage. */
    public function isWorking(): bool
    {
        return $this !== self::Warmup;
    }

    /** Resolve a raw Hevy value to an enum, defaulting to Normal. */
    public static function fromRaw(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Normal;
    }
}
