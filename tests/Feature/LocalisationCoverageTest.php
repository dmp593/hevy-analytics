<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Static guard against English hardcoded into a template.
 *
 * LocalisationTest keeps the language files level with each other. It cannot see
 * the other half of the problem: a sentence written straight into a Blade file
 * never reaches a language file at all, so every language ships it in English
 * and every parity check passes. That is how the guide page sat untranslated
 * while the suite was green.
 *
 * The check works off COMPILED Blade rather than the source. In the compiled
 * output every echo and directive has become a <?php ?> block, so whatever text
 * survives their removal is text that was written literally into the template —
 * which is exactly the question being asked. Scanning the source instead means
 * writing a Blade parser by regex, and it produces false positives on every
 * `$user->name` inside an attribute.
 */
class LocalisationCoverageTest extends TestCase
{
    /**
     * A whole tag, including attribute values that contain '>'.
     *
     * The obvious `<[^>]*>` cuts an Alpine expression in half the moment it
     * contains a comparison — `x-show="box.right > window.innerWidth"` — and the
     * remains of the JavaScript then read like an untranslated sentence. Quoted
     * values are consumed atomically here so that cannot happen.
     */
    private const TAG = '/<\/?[A-Za-z][^\s>\/]*(?:\s+[^\s=\/>]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*))?)*\s*\/?>/s';

    /**
     * Words and fragments that are legitimately not translated.
     *
     * Kept deliberately short. Every entry is a decision that some piece of the
     * interface will read in English to a Portuguese speaker, and the bar for
     * that is "translating it would make it worse".
     */
    private const ALLOWED = [
        // Scientific acronyms. They appear untranslated in the literature and in
        // the app's own language files, so translating them here would make the
        // guide disagree with the pages it explains.
        'MV', 'MEV', 'MAV', 'MRV', 'BMR', 'TDEE', 'FFMI', 'RPE', 'RIR', 'BW', 'PAL', 'BIA',
        'e1RM', '1RM', 'DEXA', 'CC BY', 'CC0',

        // Proper nouns.
        'Hevy', 'Paddle', 'OpenPowerlifting', 'FitnessVolt', 'Symmetric Strength',
        'Renaissance Periodization', 'Epley', 'Brzycki', 'Mifflin', 'Katch', 'McArdle',
        'Wilks', 'DOTS', 'Navy', 'JSON',

        // Units and symbols.
        'kg', 'cm', 'kcal', 'wk', 'mo', 'sets', 'reps', 'n=',
    ];

    /** @return array<string, array{0: string}> */
    public static function viewFiles(): array
    {
        $files = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/views'),
        );

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $relative = str_replace(dirname(__DIR__, 2).'/', '', $file->getPathname());
            $files[$relative] = [$file->getPathname()];
        }

        ksort($files);

        return $files;
    }

    #[DataProvider('viewFiles')]
    public function test_a_view_holds_no_untranslated_sentences(string $path): void
    {
        $offenders = $this->literalTextIn($path);

        $this->assertSame([], $offenders, sprintf(
            "%s contains text that no language file can reach:\n  %s\n".
            'Move it to lang/en/*.php and lang/pt/*.php and render it with __().',
            str_replace(dirname(__DIR__, 2).'/', '', $path),
            implode("\n  ", $offenders),
        ));
    }

    /**
     * `__('Log in')` is a string key: it resolves from lang/<code>.json, and
     * this app ships PHP language files only. So it renders its own English
     * argument in every language — while looking, in the template, exactly like
     * a translated call. That is what left the entire sign-in and registration
     * path in English with a green suite behind it.
     */
    #[DataProvider('viewFiles')]
    public function test_a_view_translates_by_key_rather_than_by_english_string(string $path): void
    {
        preg_match_all(
            "/(?:__|@lang|trans)\(\s*'([^']+)'/",
            file_get_contents($path),
            $matches,
        );

        $stringKeys = array_values(array_filter(
            $matches[1],
            // A key is a dotted identifier with no spaces or punctuation.
            // A trailing dot is allowed: `__('app.levels.tier.'.$level)` is a
            // key built by concatenation, not a sentence.
            fn (string $key) => ! preg_match('/^[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)*\.?$/', $key)
                || ! str_contains($key, '.'),
        ));

        $this->assertSame([], $stringKeys, sprintf(
            "%s translates by English string rather than by key:\n  %s\n".
            'Those resolve from lang/<code>.json, which this app does not ship, so they render in English everywhere.',
            str_replace(dirname(__DIR__, 2).'/', '', $path),
            implode("\n  ", $stringKeys),
        ));
    }

    /**
     * The one that proves the check works. If this stops failing, the detector
     * has been broken and every other case in this class is passing vacuously.
     */
    public function test_the_check_actually_detects_a_hardcoded_sentence(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'blade').'.blade.php';

        file_put_contents($file, <<<'BLADE'
            <div class="mt-4">
                <p>{{ __('app.brand') }}</p>
                <p>This sentence was written straight into the template.</p>
            </div>
            BLADE);

        $found = $this->literalTextIn($file);

        unlink($file);

        $this->assertNotSame([], $found);
        $this->assertStringContainsString('written straight into the template', $found[0]);
    }

    public function test_the_check_does_not_fire_on_a_properly_translated_view(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'blade').'.blade.php';

        file_put_contents($file, <<<'BLADE'
            <div class="mt-4 flex items-center justify-between gap-3">
                <p title="{{ __('app.brand') }}">{{ __('app.common.save') }}</p>
                @if ($user->hasHevyKey())
                    <span>{{ $user->name }} · {{ $weight }} kg</span>
                @endif
            </div>
            BLADE);

        $found = $this->literalTextIn($file);

        unlink($file);

        $this->assertSame([], $found);
    }

    /**
     * @return array<int, string>
     */
    private function literalTextIn(string $path): array
    {
        $compiled = Blade::compileString(file_get_contents($path));

        // Everything Blade generated — echoes, directives, @php — is now PHP.
        // What is left is what the template says on its own.
        $literal = preg_replace('/<\?php.*?\?>/s', ' ', $compiled);
        $literal = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/s', ' ', $literal);

        // Comments and the doctype are not interface copy.
        $literal = preg_replace('/<!--.*?-->/s', ' ', $literal);
        $literal = preg_replace('/<!DOCTYPE[^>]*>/i', ' ', $literal);

        $found = [];

        // User-visible attributes, before tags are stripped: a hardcoded
        // title= or placeholder= is invisible to a text-node scan and is read
        // aloud by a screen reader.
        // Unbound only. `:placeholder="presets[type]?.protein"` is an Alpine
        // expression, not copy, and the leading colon is the whole difference.
        preg_match_all(
            '/(?<=\s)(?:title|placeholder|alt|aria-label)="([^"]*[A-Za-z]{2}[^"]*)"/',
            $literal,
            $attributes,
        );

        foreach ($attributes[1] as $value) {
            if ($this->isSentence($value)) {
                $found[] = trim($value);
            }
        }

        foreach (preg_split('/\R/', preg_replace(self::TAG, "\n", $literal)) as $line) {
            $line = trim(html_entity_decode($line, ENT_QUOTES | ENT_HTML5));

            if ($this->isSentence($line)) {
                $found[] = $line;
            }
        }

        return array_values(array_unique($found));
    }

    /**
     * Two or more real words in a row, none of them on the allowlist.
     *
     * One word is a unit, a label fragment or an acronym; two in a row is
     * someone writing prose.
     */
    private function isSentence(string $text): bool
    {
        foreach (self::ALLOWED as $allowed) {
            $text = str_ireplace($allowed, ' ', $text);
        }

        preg_match_all('/\b[A-Za-z][A-Za-z\'’-]{2,}\b/u', $text, $words);

        return count($words[0]) >= 2;
    }
}
