<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Static guard on the design-token system.
 *
 * The browser suite (tests/Browser/theme.spec.js) is the real check — it
 * measures what actually gets painted. This one exists because it runs in a
 * second without a browser, and because it can name the offending line, which a
 * pixel-level check cannot. It catches the mistake at the moment it is written
 * rather than at the moment someone opens the page in dark mode.
 */
class DesignTokensTest extends TestCase
{
    /**
     * A raw palette colour cannot respond to the theme: bg-white stays white on
     * a dark canvas. Every colour in a view has to go through a semantic token.
     */
    #[DataProvider('viewFiles')]
    public function test_view_uses_semantic_tokens_rather_than_raw_palette_colours(string $file): void
    {
        $palette = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal'
            .'|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';
        $property = 'bg|text|border|ring|from|to|via|divide|placeholder|decoration|outline|accent|fill|stroke|shadow';

        $offenders = $this->matchesIn($file, "/\b(?:{$property})-(?:{$palette})-(?:50|[1-9]00|950)\b/");

        $this->assertSame([], $offenders, sprintf(
            "%s uses raw palette colours that cannot flip with the theme:\n  %s\n".
            'Use a semantic token instead (bg-surface, text-muted, border-line, text-good …).',
            $file,
            implode("\n  ", $offenders),
        ));
    }

    /**
     * text-white is only correct over something that is white-relative in BOTH
     * themes. Over a token fill it is not: --brand is dark in light mode and
     * light in dark mode, so text-white collapsed to 2.98:1 the moment the page
     * went dark. text-on-fill flips with the fill; text-white does not.
     */
    #[DataProvider('viewFiles')]
    public function test_no_white_text_on_a_themed_fill(string $file): void
    {
        $fills = 'brand|bad|good|warn|info|accent|grow|ink';

        $offenders = $this->matchesIn($file, "/\bbg-(?:{$fills})\b[^\"']*?\btext-white\b/");

        $this->assertSame([], $offenders, sprintf(
            "%s puts text-white on a themed fill:\n  %s\n".
            'Use text-on-fill (brand/status fills) or text-canvas (bg-ink).',
            $file,
            implode("\n  ", $offenders),
        ));
    }

    /**
     * Tailwind generates utilities by scanning source text for complete class
     * names. "max-w-{$width}" is not a class name it can see, so the utility is
     * never emitted and the element silently renders with no constraint at all
     * — no build warning, no runtime error, just a page that is the wrong width.
     *
     * A whole class name held in a variable ({{ $tone }}) is fine and common.
     * What breaks is a literal prefix glued to an interpolated tail.
     */
    #[DataProvider('viewFiles')]
    public function test_no_class_name_is_built_by_string_interpolation(string $file): void
    {
        // A Tailwind-ish prefix, then '-', then interpolation: bg-{$x}, max-w-{{ $y }}.
        $pattern = '/[a-z][a-z0-9]*(?:-[a-z0-9]+)*-(?:\{\{\s*\$|\{\$)/';

        $offenders = [];
        foreach (file($file) as $i => $line) {
            if (! preg_match('/\bclass\s*=|@apply|\'class\'\s*=>/', $line)) {
                continue;
            }
            if (preg_match($pattern, $line)) {
                $offenders[] = 'line '.($i + 1).': '.trim($line);
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "%s builds a class name by interpolation, so Tailwind never emits it:\n  %s\n".
            'Map the value to complete literal class names instead.',
            $file,
            implode("\n  ", $offenders),
        ));
    }

    /**
     * Every token referenced by @theme inline has to exist in both :root and
     * .dark, or the utility silently resolves to nothing in one of the themes —
     * which reads as "inherited colour", not as an error.
     */
    public function test_every_theme_token_is_defined_in_both_light_and_dark(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertSame(1, preg_match('/^:root \{(.+?)^\}/ms', $css, $light));
        $this->assertSame(1, preg_match('/^\.dark \{(.+?)^\}/ms', $css, $dark));
        $this->assertSame(1, preg_match('/^@theme inline \{(.+?)^\}/ms', $css, $theme));

        $names = fn (string $block) => array_values(array_unique(
            preg_match_all('/^\s*(--[a-z0-9-]+):/m', $block, $m) ? $m[1] : []
        ));

        $lightTokens = $names($light[1]);
        $darkTokens = $names($dark[1]);

        // Referenced by @theme inline as var(--x), so --x must flip.
        preg_match_all('/var\((--[a-z0-9-]+)\)/', $theme[1], $refs);
        $referenced = array_values(array_unique($refs[1]));

        $this->assertSame([], array_values(array_diff($referenced, $lightTokens)),
            '@theme inline references tokens that :root does not define.');
        $this->assertSame([], array_values(array_diff($referenced, $darkTokens)),
            '@theme inline references tokens that .dark does not override, so they will not flip.');

        // And nothing should be declared light-only or dark-only.
        $this->assertSame([], array_values(array_diff($lightTokens, $darkTokens)),
            'Tokens defined in :root but never overridden in .dark.');
        $this->assertSame([], array_values(array_diff($darkTokens, $lightTokens)),
            'Tokens defined in .dark with no light-mode default.');
    }

    /**
     * Every Blade view, as [path => [path]] so failures name the file.
     *
     * Mail templates are excluded, and it is not a shortcut. They are not
     * Tailwind and never reach the browser: Laravel inlines their CSS at send
     * time, because email clients support neither stylesheets nor custom
     * properties — so the design tokens this class exists to enforce are
     * precisely what an email cannot use. Their colours are hex by necessity,
     * and their class names are resolved by the CSS inliner rather than by
     * Tailwind's scanner. Judging them by these rules would fail them for
     * being correct.
     */
    public static function viewFiles(): array
    {
        $root = dirname(__DIR__, 2).'/resources/views';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        $cases = [];
        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $relative = 'resources/views'.substr($file->getPathname(), strlen($root));

            if (str_starts_with($relative, 'resources/views/vendor/mail/')) {
                continue;
            }

            $cases[$relative] = [$file->getPathname()];
        }

        ksort($cases);

        return $cases;
    }

    /**
     * Matching lines, with the two photo-overlay labels excluded: those sit on a
     * bg-black/60 scrim over a photograph, which is black in either theme, so
     * white really is the right colour there.
     */
    private function matchesIn(string $file, string $pattern): array
    {
        $out = [];
        foreach (file($file) as $i => $line) {
            if (str_contains($line, 'bg-black/60')) {
                continue;
            }
            if (preg_match($pattern, $line)) {
                $out[] = 'line '.($i + 1).': '.trim($line);
            }
        }

        return $out;
    }
}
