<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ProcessMonthlyExports;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ProcessMonthlyExportsTest extends TestCase
{
    #[Test]
    public function it_uses_the_exports_queue(): void
    {
        $job = new ProcessMonthlyExports();

        $this->assertSame('exports', $job->queue);
    }
}

