<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The information architecture: four sections whose names answer questions,
 * the data doors gathered in one menu, and a thumb-reachable bar on phones.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_four_sections_and_their_homes(): void
    {
        $sections = Navigation::sections();

        $this->assertSame(['today', 'training', 'body', 'nutrition'], array_column($sections, 'key'));

        $body = collect($sections)->firstWhere('key', 'body');
        $this->assertContains('projections', array_column($body['children'], 'key'));
        $this->assertNotContains('nutrition', array_column($body['children'], 'key'));
    }

    public function test_the_menus_gather_the_data_doors(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

        $response->assertSee(__('app.nav.my_data'));
        $response->assertSee(route('convert'));
        $response->assertSee(route('import'));
        $response->assertSee(route('settings.export'));
    }

    public function test_the_mobile_bottom_bar_carries_the_sections(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk();

        $response->assertSee(__('app.sections.nutrition'));
        $response->assertSee('bottom-0', false);
        $response->assertSee(route('nutrition'));
    }
}
