<?php

namespace Tests\Unit\Support;

use App\Support\Spreadsheet\NoFormulaValueBinder;
use App\Support\Spreadsheet\SpreadsheetCell;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

class SpreadsheetSafetyTest extends TestCase
{
    public function test_csv_cells_starting_with_formula_characters_are_neutralized(): void
    {
        $this->assertSame(
            ["'=HYPERLINK(\"https://evil.example\")", "'+cmd|' /C calc'!A0", "'-2+3", "'@SUM(A1)", "'\tx", 'plain', '-12.5', '42', 42, null],
            SpreadsheetCell::csvRow(['=HYPERLINK("https://evil.example")', "+cmd|' /C calc'!A0", '-2+3', '@SUM(A1)', "\tx", 'plain', '-12.5', '42', 42, null]),
        );
    }

    public function test_xlsx_cells_never_become_formulas(): void
    {
        $this->assertInstanceOf(NoFormulaValueBinder::class, Cell::getValueBinder());

        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->fromArray([['=HYPERLINK("https://evil.example","x")', '12.50', 7, 'text']], null, 'A1');

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('A1')->getDataType());
        $this->assertSame('=HYPERLINK("https://evil.example","x")', $sheet->getCell('A1')->getValue());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('B1')->getDataType());
        $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('C1')->getDataType());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('D1')->getDataType());
    }
}
