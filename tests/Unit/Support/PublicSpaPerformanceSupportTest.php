<?php

namespace Tests\Unit\Support;

use App\Support\LandingCopy;
use App\Support\PublicSpaRoutes;
use Tests\TestCase;

class PublicSpaPerformanceSupportTest extends TestCase
{
    public function test_public_marketing_paths(): void
    {
        $this->assertTrue(PublicSpaRoutes::isPublicMarketing(''));
        $this->assertTrue(PublicSpaRoutes::isPublicMarketing('pricing'));
        $this->assertTrue(PublicSpaRoutes::isPublicMarketing('legal/privacy'));
        $this->assertTrue(PublicSpaRoutes::isPublicMarketing('documentation/getting-started'));
        $this->assertFalse(PublicSpaRoutes::isPublicMarketing('dashboard'));
        $this->assertFalse(PublicSpaRoutes::isPublicMarketing('stores/abc'));
    }

    public function test_landing_home_detection(): void
    {
        $this->assertTrue(PublicSpaRoutes::isLandingHome(''));
        $this->assertFalse(PublicSpaRoutes::isLandingHome('pricing'));
    }

    public function test_landing_copy_reads_locale_json(): void
    {
        app()->setLocale('en');
        $this->assertSame(
            'Run Bitcoin checkout for your business.',
            LandingCopy::get('landing.hero_headline'),
        );
    }
}
