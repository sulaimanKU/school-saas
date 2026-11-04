<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_root_is_unavailable_outside_local_preview(): void
    {
        $response = $this->get('/');

        $response->assertNotFound();
    }
}
