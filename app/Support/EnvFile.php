<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Edits .env in place.
 *
 * Rewritten line by line rather than regenerated from a config array, because
 * an operator's .env is a hand-maintained file: it has their database password,
 * their mail settings, and comments they wrote to remind themselves of
 * something. A writer that regenerates it is a writer that eventually eats one
 * of those.
 */
class EnvFile
{
    public function __construct(private readonly string $path) {}

    public function exists(): bool
    {
        return File::exists($this->path);
    }

    /**
     * Set each key, updating in place where it already appears and appending
     * the rest under a comment saying where they came from.
     *
     * @param  array<string, string>  $values
     */
    public function set(array $values, string $appendNote = 'Added automatically'): void
    {
        $lines = $this->exists() ? file($this->path, FILE_IGNORE_NEW_LINES) : [];
        $seen = [];

        foreach ($lines as $i => $line) {
            foreach ($values as $key => $value) {
                // Anchored, so PADDLE_API_KEY does not also match a line for
                // PADDLE_API_KEY_OLD, and a commented-out line stays commented.
                if (preg_match('/^\s*'.preg_quote($key, '/').'\s*=/', $line)) {
                    $lines[$i] = $key.'='.self::quote($value);
                    $seen[$key] = true;
                }
            }
        }

        $missing = array_diff_key($values, $seen);

        if ($missing !== []) {
            if ($lines !== [] && trim(end($lines)) !== '') {
                $lines[] = '';
            }

            $lines[] = '# '.$appendNote;

            foreach ($missing as $key => $value) {
                $lines[] = $key.'='.self::quote($value);
            }
        }

        File::put($this->path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    /**
     * Quote anything that is not a bare token.
     *
     * Laravel's dotenv reader stops a bare value at the first space and treats
     * a leading '#' as a comment, so "€8" and "Hevy Analytics" both need quotes
     * or they arrive truncated with no error anywhere.
     */
    public static function quote(string $value): string
    {
        return preg_match('/^[A-Za-z0-9_.\-\/:]*$/', $value) === 1
            ? $value
            : '"'.str_replace('"', '\"', $value).'"';
    }
}
