<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

// Opens every built sidebar page as each role and checks nothing crashes
class NavSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_every_sidebar_page_renders_for_each_role(): void
    {
        foreach (User::all()->unique('role') as $user) {
            foreach (config('csa_nav') as $items) {
                foreach ($items as $item) {
                    if (! Route::has($item[0])) {
                        continue;
                    }
                    $status = $this->actingAs($user)->get(route($item[0]))->status();
                    $this->assertContains($status, [200, 403], "{$item[0]} as {$user->role} returned {$status}");
                }
            }
        }

    }

    public function test_attention_and_contact_centre_are_hidden_from_sidebar(): void
    {
        $navigation = config('csa_nav');
        $routes = collect($navigation)->flatten(1)->pluck(0);

        $this->assertNotContains('attention', $routes);
        $this->assertArrayNotHasKey('Contact Centre', $navigation);
        $this->assertTrue(Route::has('attention'));
        $this->assertTrue(Route::has('cc.dashboard'));

        $this->actingAs(User::first())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Attention Centre')
            ->assertDontSee('Contact Centre');
    }
}
