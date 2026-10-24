<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChoralaTransportTest extends TestCase
{
    public function test_shell_uses_cached_configuration_and_explicit_direct_transport(): void
    {
        config([
            'services.chorala.project_key' => 'pk_synthetic',
            'services.chorala.widget_url' => 'https://feedback.example.test',
            'services.chorala.use_proxy' => false,
        ]);

        $previous = getenv('CHORALA_USE_PROXY');
        putenv('CHORALA_USE_PROXY=true');
        try {
            $html = view('partials.spa-head')->render();
            $this->assertStringContainsString('name="satflux-chorala-use-proxy" content="false"', $html);
            $this->assertStringContainsString('name="satflux-chorala-widget-url" content="https://feedback.example.test"', $html);
            $this->assertStringNotContainsString('name="satflux-chorala-use-proxy" content="true"', $html);
        } finally {
            putenv($previous === false ? 'CHORALA_USE_PROXY' : 'CHORALA_USE_PROXY='.$previous);
        }
    }

    public function test_legacy_proxy_environment_setting_cannot_reenable_sdk_proxy(): void
    {
        $previous = getenv('CHORALA_USE_PROXY');
        putenv('CHORALA_USE_PROXY=true');
        try {
            $services = require config_path('services.php');
            $this->assertFalse($services['chorala']['use_proxy']);
        } finally {
            putenv($previous === false ? 'CHORALA_USE_PROXY' : 'CHORALA_USE_PROXY='.$previous);
        }
    }
}
