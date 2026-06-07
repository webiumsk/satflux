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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test XML parse failed');

        XmlParser::loadString('<root><unclosed>', 'test XML');
    }
}
