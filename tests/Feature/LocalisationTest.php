<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Locales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Half-finished localisation reads worse than none at all, so these tests are
 * mostly about catching drift rather than checking any particular sentence.
 */
class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Data providers run before the Laravel container is booted, so config()
     * is unavailable here — read the shipped language directories off disk.
     *
     * @return array<string, array{0: string}>
     */
    public static function locales(): array
    {
        $dirs = glob(dirname(__DIR__, 2).'/lang/*', GLOB_ONLYDIR) ?: [];

        $cases = [];
        foreach ($dirs as $dir) {
            $code = basename($dir);
            $cases[$code] = [$code];
        }

        return $cases;
    }

    /**
     * The one that matters: a new string added to English must be added
     * everywhere, or that language quietly falls back and nobody notices.
     */
    #[DataProvider('locales')]
    public function test_every_language_defines_the_same_keys_as_english(string $locale): void
    {
        foreach ($this->files() as $file) {
            $english = $this->flatten(require lang_path("en/{$file}"));
            $translated = $this->flatten(require lang_path("{$locale}/{$file}"));

            $missing = array_diff_key($english, $translated);
            $extra = array_diff_key($translated, $english);

            $this->assertSame([], array_keys($missing), "Missing in [{$locale}/{$file}]: ".implode(', ', array_keys($missing)));
            $this->assertSame([], array_keys($extra), "Not in English, so unreachable in [{$locale}/{$file}]: ".implode(', ', array_keys($extra)));
        }
    }

    #[DataProvider('locales')]
    public function test_every_language_ships_the_same_files(string $locale): void
    {
        foreach ($this->files() as $file) {
            $this->assertFileExists(
                lang_path("{$locale}/{$file}"),
                "[{$locale}] is missing {$file}, so those strings silently fall back to English.",
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function files(): array
    {
        return array_map('basename', glob(lang_path('en/*.php')) ?: []);
    }

    /**
     * A translated string that still reads exactly like the English one is
     * usually an untranslated placeholder. Proper nouns and shared technical
     * terms are legitimately identical, so this only fails when almost
     * everything matches.
     */
    #[DataProvider('locales')]
    public function test_a_non_english_language_is_actually_translated(string $locale): void
    {
        if ($locale === 'en') {
            $this->assertTrue(true);

            return;
        }

        $english = $this->flatten(require lang_path('en/app.php'));
        $translated = $this->flatten(require lang_path("{$locale}/app.php"));

        $identical = count(array_intersect_assoc($english, $translated));
        $ratio = $identical / max(1, count($english));

        $this->assertLessThan(
            0.5,
            $ratio,
            sprintf('[%s] is %d%% identical to English — looks like a copied placeholder.', $locale, $ratio * 100),
        );
    }

    #[DataProvider('locales')]
    public function test_placeholders_survive_translation(string $locale): void
    {
        $english = [];
        $translated = [];
        foreach ($this->files() as $file) {
            $english += $this->flatten(require lang_path("en/{$file}"), $file);
            $translated += $this->flatten(require lang_path("{$locale}/{$file}"), $file);
        }

        foreach ($english as $key => $source) {
            // Compare the SET of placeholders, not how many times each appears:
            // a translation may legitimately mention :attribute once where the
            // English says it twice. What must never happen is a placeholder
            // going missing entirely — the user then reads a sentence with a
            // hole in it — or a new one appearing, which renders literally.
            $expected = $this->placeholders($source);
            $actual = $this->placeholders($translated[$key] ?? '');

            $this->assertSame($expected, $actual, "Placeholder mismatch in [{$locale}] at [{$key}]");
        }
    }

    /**
     * A key that does not exist renders as its own dotted path — "guide.volume.mv"
     * appears in the page, in every language, and nothing errors. It is the
     * failure mode of a rename, and it is invisible unless something looks.
     *
     * @param  string  $locale
     */
    #[DataProvider('locales')]
    public function test_no_page_leaks_a_raw_translation_key(string $locale): void
    {
        $user = User::factory()->create([
            'locale' => $locale,
            'hevy_api_key' => '00000000-0000-0000-0000-000000000000',
            'sex' => 'male',
            'age' => 30,
            'height_cm' => 178,
            'activity_level' => 1.55,
        ]);

        $user->goals()->create(['type' => 'lean_bulk', 'is_active' => true, 'started_at' => now()]);
        $user->routines()->create(['hevy_id' => 'r1', 'title' => 'Push'])
            ->exercises()->create([
                'index' => 0,
                'title' => 'Bench Press (Barbell)',
                'exercise_template_hevy_id' => 'BENCH',
                'sets' => [['index' => 0, 'type' => 'normal', 'weight_kg' => 60, 'reps' => 8]],
            ]);

        // The prefixes are the shipped language FILES: anything that reaches the
        // page as "<file>.<something>" was a lookup that found nothing.
        $prefixes = array_map(fn ($f) => preg_quote(basename($f, '.php'), '/'), $this->files());
        $pattern = '/\b(?:'.implode('|', $prefixes).')\.[a-z0-9_]+(?:\.[a-z0-9_]+)*/i';

        $paths = [
            '/dashboard', '/performance', '/strength-levels', '/muscle', '/body',
            '/photos', '/guide', '/nutrition', '/projections', '/routines', '/routines/r1',
            '/routines/r1/edit', '/goals', '/ai', '/write-operations', '/profile', '/billing',
        ];

        $leaks = [];

        // The landing page first, while still signed out: / redirects to the
        // dashboard for anyone authenticated, and actingAs() persists for the
        // rest of the test.
        $this->post("/locale/{$locale}");
        $guest = $this->strip($this->get('/')->assertOk()->getContent());

        if (preg_match_all($pattern, $guest, $m)) {
            $leaks['/'] = array_values(array_unique($m[0]));
        }

        foreach ($paths as $path) {
            $response = $this->actingAs($user)->get($path);

            $this->assertSame(200, $response->status(), "{$path} did not render");

            $html = $response->getContent();

            if (preg_match_all($pattern, $this->strip($html), $m)) {
                $leaks[$path] = array_values(array_unique($m[0]));
            }
        }

        $this->assertSame([], $leaks, sprintf(
            "[%s] pages rendered raw translation keys:\n%s",
            $locale,
            json_encode($leaks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ));
    }

    public function test_a_signed_in_users_choice_wins(): void
    {
        $user = User::factory()->create(['locale' => 'pt']);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertSame('pt', App::getLocale());
    }

    public function test_a_guest_gets_the_language_their_browser_asked_for(): void
    {
        $this->get('/login', ['Accept-Language' => 'pt-PT,pt;q=0.9,en;q=0.8'])->assertOk();

        $this->assertSame('pt', App::getLocale());
    }

    public function test_quality_weights_are_respected_not_just_the_first_tag(): void
    {
        // German first but lower weighted; Portuguese is what they actually want.
        $this->get('/login', ['Accept-Language' => 'de;q=0.5,pt;q=0.9']);

        $this->assertSame('pt', App::getLocale());
    }

    public function test_an_unsupported_language_falls_back_rather_than_breaking(): void
    {
        $this->get('/login', ['Accept-Language' => 'is-IS,is;q=0.9'])->assertOk();

        $this->assertSame(Locales::fallback(), App::getLocale());
    }

    public function test_a_guest_can_switch_language(): void
    {
        $this->from('/login')->post('/locale/pt')->assertRedirect('/login');

        $this->get('/login');
        $this->assertSame('pt', App::getLocale());
    }

    public function test_switching_to_an_unsupported_language_is_rejected(): void
    {
        $this->post('/locale/xx')->assertNotFound();
    }

    public function test_switching_while_signed_in_persists_to_the_profile(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)->from('/dashboard')->post('/locale/pt');

        $this->assertSame('pt', $user->fresh()->locale);
    }

    /**
     * The prose of a page: scripts, styles and tags removed.
     *
     * Asset URLs and inline JS are full of dotted identifiers that look exactly
     * like a translation key, so scanning the raw HTML is all false positives.
     */
    private function strip(string $html): string
    {
        return preg_replace('/<[^>]*>/s', ' ', preg_replace('/<(script|style)\b.*?<\/\1>/s', '', $html));
    }

    /** @return array<int, string> */
    private function placeholders(string $text): array
    {
        preg_match_all('/:([a-z_]+)/', $text, $matches);
        $found = array_values(array_unique($matches[1]));
        sort($found);

        return $found;
    }

    /** @return array<string, string> */
    private function flatten(array $items, string $prefix = ''): array
    {
        $flat = [];
        foreach ($items as $key => $value) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $flat += $this->flatten($value, $path);
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }
}
