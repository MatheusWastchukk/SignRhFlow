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
        // Evita a view welcome (Vite/manifest) no CI; liveness é suficiente aqui.
        $response = $this->get('/up');

        $response->assertOk();
    }
}
