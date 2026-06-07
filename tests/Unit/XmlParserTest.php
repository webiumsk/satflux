<?php

namespace Tests\Unit;

use App\Services\Compliance\XmlParser;
use PHPUnit\Framework\TestCase;

class XmlParserTest extends TestCase
{
    public function test_loads_valid_xml_with_nonet_flag(): void
    {
        $xml = XmlParser::loadString('<root><item>ok</item></root>', 'test XML');

        $this->assertSame('ok', (string) $xml->item);
    }

    public function test_throws_on_invalid_xml_with_diagnostics(): void
    {
        try {
            XmlParser::loadString('<root><unclosed>', 'test XML');
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('test XML parse failed', $e->getMessage());
            $this->assertMatchesRegularExpression(
                '/Opening and ending tag mismatch|unexpected end of file|Premature end of data/i',
                $e->getMessage(),
            );
        }
    }
}
