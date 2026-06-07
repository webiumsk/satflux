<?php

namespace Tests\Unit;

use App\Services\Compliance\NameNormalizer;
use PHPUnit\Framework\TestCase;

class NameNormalizerTest extends TestCase
{
    public function test_normalizes_case_and_punctuation(): void
    {
        $normalizer = new NameNormalizer;

        $this->assertSame('john smith', $normalizer->normalize('  John   Smith! '));
    }

    public function test_normalizes_email_local_part(): void
    {
        $normalizer = new NameNormalizer;

        $this->assertSame('john smith', $normalizer->normalizeEmailLocalPart('John.Smith@Example.com'));
    }
}
