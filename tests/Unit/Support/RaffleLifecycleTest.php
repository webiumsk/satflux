<?php

namespace Tests\Unit\Support;

use App\Support\RaffleLifecycle;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RaffleLifecycleTest extends TestCase
{
    #[DataProvider('statusActionsProvider')]
    public function test_allowed_actions_for_status(string $status, array $expected): void
    {
        $this->assertSame($expected, RaffleLifecycle::allowedActions($status));
    }

    public static function statusActionsProvider(): array
    {
        return [
            'draft' => [RaffleLifecycle::STATUS_DRAFT, ['open']],
            'open' => [RaffleLifecycle::STATUS_OPEN, ['close']],
            'closed' => [RaffleLifecycle::STATUS_CLOSED, ['draw', 'complete']],
            'drawing' => [RaffleLifecycle::STATUS_DRAWING, ['draw', 'complete']],
            'completed' => [RaffleLifecycle::STATUS_COMPLETED, []],
            'unknown' => ['Unknown', []],
        ];
    }

    public function test_shows_public_link(): void
    {
        $this->assertFalse(RaffleLifecycle::showsPublicLink(RaffleLifecycle::STATUS_DRAFT));
        $this->assertTrue(RaffleLifecycle::showsPublicLink(RaffleLifecycle::STATUS_OPEN));
        $this->assertTrue(RaffleLifecycle::showsPublicLink(RaffleLifecycle::STATUS_COMPLETED));
    }
}
