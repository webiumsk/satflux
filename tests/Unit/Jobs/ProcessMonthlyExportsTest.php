<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessMonthlyExports;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProcessMonthlyExportsTest extends TestCase
{
    #[Test]
    public function it_uses_the_exports_queue(): void
    {
        $job = new ProcessMonthlyExports;

        $this->assertSame('exports', $job->queue);
    }
}
