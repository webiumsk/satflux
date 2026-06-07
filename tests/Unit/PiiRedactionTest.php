<?php

namespace Tests\Unit;

use App\Support\PiiRedaction;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PiiRedactionTest extends TestCase
{
    #[Test]
    public function email_hash_is_stable_and_non_reversible(): void
    {
        $hash = PiiRedaction::emailHash('User@Example.com');
        $this->assertStringStartsWith('sha256:', (string) $hash);
        $this->assertSame($hash, PiiRedaction::emailHash('user@example.com'));
    }
}
