<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function index_returns_available_locales_and_current(): void
    {
        $response = $this->getJson('/api/locale');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['code', 'name'],
                ],
                'current',
            ]);
        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('en', $codes);
        $this->assertContains('sk', $codes);
        $this->assertContains('es', $codes);
    }

    /** @test */
    public function set_locale_accepts_valid_locale_and_returns_success(): void
    {
        $response = $this->postJson('/api/locale', ['locale' => 'sk']);

        $response->assertStatus(200)
            ->assertJsonPath('locale', 'sk')
            ->assertJsonStructure(['message', 'locale']);
    }

    /** @test */
    public function set_locale_rejects_invalid_locale_with_422(): void
    {
        $response = $this->postJson('/api/locale', ['locale' => 'xx']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }

    /** @test */
    public function set_locale_requires_locale_parameter(): void
    {
        $response = $this->postJson('/api/locale', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);
    }
}
