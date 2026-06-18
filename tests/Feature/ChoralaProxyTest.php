<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChoralaProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_returns_404_when_chorala_not_configured(): void
    {
        config(['services.chorala.project_key' => null]);

        $this->getJson('/api/chorala-proxy/v1/public/boards')->assertStatus(404);
    }

    public function test_proxy_forwards_public_boards_request(): void
    {
        config([
            'services.chorala.project_key' => 'pk_test_123',
            'services.chorala.widget_url' => 'https://chorala.example.com',
        ]);

        Http::fake([
            'https://chorala.example.com/api/v1/public/boards*' => Http::response([
                'boards' => [],
                'posts' => [],
            ], 200),
        ]);

        $this->getJson('/api/chorala-proxy/v1/public/boards', [
            'X-Chorala-Key' => 'pk_test_123',
        ])
            ->assertOk()
            ->assertJsonPath('boards', []);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://chorala.example.com/api/v1/public/boards'
                && $request->header('X-Chorala-Key')[0] === 'pk_test_123';
        });
    }

    public function test_proxy_blocks_non_public_paths(): void
    {
        config(['services.chorala.project_key' => 'pk_test_123']);

        $this->getJson('/api/chorala-proxy/v1/projects')
            ->assertStatus(403);
    }
}
