<?php

namespace Tests\Feature;

use App\Models\IntakeLog;
use App\Models\User;
use App\Services\FatSecret\FatSecretSync;
use App\Services\FatSecret\OAuth1;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The FatSecret link: OAuth 1.0 signing pinned against a published external
 * vector, the three-legged dance with the HTTP layer faked, and the sync's
 * contract that it only ever touches nutrition fields.
 */
class FatSecretTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.fatsecret.consumer_key' => 'test-key',
            'services.fatsecret.consumer_secret' => 'test-secret',
        ]);
    }

    private function linked(): User
    {
        $user = User::factory()->create();
        $user->forceFill([
            'fatsecret_token' => 'at',
            'fatsecret_secret' => 'ats',
            'fatsecret_linked_at' => now(),
        ])->save();

        return $user;
    }

    /**
     * The canonical OAuth 1.0a worked example (the oauth.net "photos"
     * vector): a signer that reproduces a published signature is right for
     * reasons stronger than its own tests.
     */
    public function test_the_signature_matches_the_published_oauth_vector(): void
    {
        $oauth = new OAuth1('dpf43f3p2l4k3l03', 'kd94hf93k423kf44');

        $signed = $oauth->sign(
            'GET',
            'http://photos.example.net/photos',
            ['file' => 'vacation.jpg', 'size' => 'original'],
            token: 'nnch734d00sl2jdk',
            tokenSecret: 'pfkkdhi9sl3r4s00',
            nonce: 'kllo9940pd9333jh',
            timestamp: 1191242096,
        );

        $this->assertSame('tR3+Ty81lMeYAr/Fid0kMTYa/WM=', $signed['oauth_signature']);
    }

    public function test_connect_redirects_to_the_authorize_page(): void
    {
        Http::fake([
            'authentication.fatsecret.com/oauth/request_token' => Http::response('oauth_token=rt&oauth_token_secret=rts'),
        ]);

        $response = $this->actingAs(User::factory()->create())
            ->post('/integrations/fatsecret/connect');

        $response->assertRedirect('https://authentication.fatsecret.com/oauth/authorize?oauth_token=rt');
        $this->assertSame('rts', session('fatsecret.request_secret'));
    }

    public function test_the_callback_stores_the_token_pair_encrypted(): void
    {
        Http::fake([
            'authentication.fatsecret.com/oauth/access_token' => Http::response('oauth_token=at&oauth_token_secret=ats'),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['fatsecret.request_secret' => 'rts'])
            ->get('/integrations/fatsecret/callback?oauth_token=rt&oauth_verifier=v')
            ->assertRedirect(route('profile.edit'));

        $fresh = $user->fresh();
        $this->assertSame('at', $fresh->fatsecret_token);
        $this->assertNotNull($fresh->fatsecret_linked_at);
        // Encrypted at rest: the raw column must not hold the plaintext.
        $this->assertNotSame('at', \DB::table('users')->where('id', $user->id)->value('fatsecret_token'));
    }

    public function test_the_sync_sums_entries_and_handles_the_single_object_quirk(): void
    {
        $user = $this->linked();

        Http::fakeSequence()
            // Day 1: a list of two entries.
            ->push(['food_entries' => ['food_entry' => [
                ['calories' => '600', 'protein' => '30', 'fat' => '20', 'carbohydrate' => '70'],
                ['calories' => '900', 'protein' => '45.4', 'fat' => '30', 'carbohydrate' => '100'],
            ]]])
            // Day 2: FatSecret collapses a single entry into an object.
            ->push(['food_entries' => ['food_entry' => ['calories' => '500', 'protein' => '25', 'fat' => '15', 'carbohydrate' => '60']]]);

        $written = (new FatSecretSync)->run($user, days: 2);

        $this->assertSame(2, $written);

        $today = $user->intakeLogs()->whereDate('date', now($user->resolvedTimezone())->toDateString())->firstOrFail();
        $this->assertSame(1500, (int) $today->calories);
        $this->assertSame(75, (int) $today->protein_g);

        $this->assertNotNull($user->fresh()->fatsecret_synced_at);
    }

    public function test_the_sync_never_touches_a_logged_weight(): void
    {
        $user = $this->linked();
        $today = now($user->resolvedTimezone())->toDateString();
        $user->intakeLogs()->create(['date' => $today, 'weight_kg' => 82.5]);

        Http::fake([
            'platform.fatsecret.com/*' => Http::response(['food_entries' => ['food_entry' => [
                ['calories' => '900', 'protein' => '45', 'fat' => '30', 'carbohydrate' => '100'],
            ]]]),
        ]);

        (new FatSecretSync)->run($user, days: 1);

        $log = $user->intakeLogs()->whereDate('date', $today)->firstOrFail();
        $this->assertEqualsWithDelta(82.5, (float) $log->weight_kg, 0.01);
        $this->assertSame(900, (int) $log->calories);
    }

    public function test_disconnect_clears_the_link(): void
    {
        $user = $this->linked();

        $this->actingAs($user)->post('/integrations/fatsecret/disconnect')->assertRedirect();

        $fresh = $user->fresh();
        $this->assertNull($fresh->fatsecret_token);
        $this->assertNull($fresh->fatsecret_linked_at);
    }

    public function test_the_command_syncs_only_linked_non_demo_accounts(): void
    {
        $linked = $this->linked();
        User::factory()->create(); // unlinked bystander

        Http::fake([
            'platform.fatsecret.com/*' => Http::response(['food_entries' => ['food_entry' => [
                ['calories' => '700', 'protein' => '35', 'fat' => '20', 'carbohydrate' => '80'],
            ]]]),
        ]);

        $this->artisan('fatsecret:sync --days=1')->assertSuccessful();

        $this->assertSame(1, $linked->intakeLogs()->count());
        $this->assertSame(1, IntakeLog::count());
    }

    public function test_connect_is_a_404_when_unconfigured(): void
    {
        config(['services.fatsecret.consumer_key' => null]);

        $this->actingAs(User::factory()->create())
            ->post('/integrations/fatsecret/connect')
            ->assertNotFound();
    }
}
