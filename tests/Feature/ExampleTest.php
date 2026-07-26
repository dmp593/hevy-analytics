<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Guests hitting the root are redirected to the login page.
        $this->get('/')->assertRedirect('/login');

        $this->get('/login')->assertStatus(200);
    }
}
