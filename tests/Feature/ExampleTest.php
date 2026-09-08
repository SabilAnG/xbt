<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Beranda membaca content_blocks, jadi tabelnya harus ada. Tanpa ini test
    // bawaan Laravel gagal di sqlite kosong, bukan karena halamannya rusak.
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
