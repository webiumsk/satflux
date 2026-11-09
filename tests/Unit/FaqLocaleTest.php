<?php

namespace Tests\Unit;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use Tests\TestCase;

class FaqLocaleTest extends TestCase
{
    public function test_canonical_czech_wins_and_empty_canonical_uses_preserved_legacy(): void
    {
        app()->setLocale('cs');
        $item = new FaqItem(['question' => ['en' => 'English', 'cs' => 'Česky', 'cz' => 'Legacy'],
            'answer' => ['en' => 'English answer', 'cs' => '', 'cz' => 'Legacy answer']]);
        $category = new FaqCategory(['name' => ['en' => 'English', 'cs' => ' ', 'cz' => 'Legacy name'],
            'description' => ['en' => 'English description', 'cs' => 'Český popis', 'cz' => 'Legacy description']]);
        $this->assertSame('Česky', $item->getLocalizedQuestionAttribute());
        $this->assertSame('Legacy answer', $item->getLocalizedAnswerAttribute());
        $this->assertSame('Legacy name', $category->getLocalizedNameAttribute());
        $this->assertSame('Český popis', $category->getLocalizedDescriptionAttribute());
        $this->assertSame('Legacy', $item->question['cz']);
        app()->setLocale('de');
        $this->assertSame('English', $item->getLocalizedQuestionAttribute());
        $this->assertSame('English', $category->getLocalizedNameAttribute());
    }
}
