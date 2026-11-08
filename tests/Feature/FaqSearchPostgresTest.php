<?php

namespace Tests\Feature;

use App\Http\Controllers\FaqController;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaqSearchPostgresTest extends TestCase
{
    private bool $transactionStarted = false;

    /** Opt into a disposable PostgreSQL database and roll back the minimal FAQ schema after each case. */
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('FAQ_POSTGRES_TESTS') !== '1' || config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Actual FAQ ILIKE tests require an explicitly enabled disposable PostgreSQL target.');
        }
        $this->assertStringStartsWith('satflux_faq_', (string) config('database.connections.pgsql.database'));
        DB::beginTransaction();
        $this->transactionStarted = true;
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        (require database_path('migrations/2026_01_24_123843_create_faq_categories_table.php'))->up();
        (require database_path('migrations/2026_01_24_123854_create_faq_items_table.php'))->up();
        DB::table('users')->insert(['id' => 1]);
    }

    /** Leave no schema or content behind in the disposable database, including after assertion failure. */
    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    /** Czech searches match legacy question and answer fields using actual PostgreSQL JSON extraction/ILIKE. */
    public function test_czech_search_matches_legacy_question_and_answer_case_insensitively(): void
    {
        $question = $this->item('legacy-question', ['en' => 'English', 'cz' => 'Legacy Question Needle'], ['en' => 'Answer']);
        $answer = $this->item('legacy-answer', ['en' => 'English'], ['en' => 'Answer', 'cz' => 'Legacy Answer Needle']);
        $this->item('draft', ['cz' => 'Legacy Question Needle'], ['cz' => 'Legacy Answer Needle'], false);
        $this->assertSame([$question->id], array_column($this->search('cs', 'question needle'), 'id'));
        $this->assertSame([$answer->id], array_column($this->search('cs', 'answer needle'), 'id'));
        $this->assertSame('Legacy Question Needle', $this->search('cs', 'question needle')[0]['question']);
        $this->assertSame('Legacy Answer Needle', $this->search('cs', 'answer needle')[0]['answer']);
    }

    /** Legacy Czech must not widen searches performed in another locale or the English fallback. */
    public function test_non_czech_search_does_not_match_legacy_czech_text(): void
    {
        $this->item('legacy', ['en' => 'English', 'cz' => 'LegacyOnlyNeedle'], ['en' => 'Answer', 'cz' => 'LegacyOnlyAnswer']);
        foreach (['en', 'sk', 'de', 'es'] as $locale) {
            $this->assertSame([], $this->search($locale, 'LegacyOnlyNeedle'));
            $this->assertSame([], $this->search($locale, 'LegacyOnlyAnswer'));
        }
    }

    /** Canonical Czech and English fallback predicates continue to work alongside legacy compatibility. */
    public function test_canonical_and_english_fallback_search_behaviors_are_preserved(): void
    {
        $canonical = $this->item('canonical', ['cs' => 'CanonicalQuestionNeedle', 'cz' => 'Older question'], ['cs' => 'CanonicalAnswerNeedle']);
        $english = $this->item('english', ['en' => 'FallbackQuestionNeedle'], ['en' => 'FallbackAnswerNeedle']);
        $this->assertSame([$canonical->id], array_column($this->search('cs', 'canonicalquestionneedle'), 'id'));
        $this->assertSame([$canonical->id], array_column($this->search('cs', 'canonicalanswerneedle'), 'id'));
        foreach (['cs', 'de'] as $locale) {
            $this->assertSame([$english->id], array_column($this->search($locale, 'fallbackquestionneedle'), 'id'));
            $this->assertSame([$english->id], array_column($this->search($locale, 'fallbackanswerneedle'), 'id'));
        }
    }

    /** Legacy matches stay inside the publication/category filters and cannot escape through OR predicates. */
    public function test_czech_legacy_matches_respect_category_and_publication_constraints(): void
    {
        $firstCategory = FaqCategory::create(['slug' => 'first', 'name' => ['en' => 'First']]);
        $otherCategory = FaqCategory::create(['slug' => 'other', 'name' => ['en' => 'Other']]);
        $first = $this->item('first-item', ['cz' => 'ScopedNeedle'], ['en' => 'Answer']);
        $first->update(['category_id' => $firstCategory->id]);
        $other = $this->item('other-item', ['cz' => 'ScopedNeedle'], ['en' => 'Answer']);
        $other->update(['category_id' => $otherCategory->id]);
        $draft = $this->item('draft-item', ['cz' => 'ScopedNeedle'], ['en' => 'Answer'], false);
        $draft->update(['category_id' => $firstCategory->id]);
        $this->assertSame([$first->id], array_column($this->search('cs', 'ScopedNeedle', $firstCategory->id), 'id'));
    }

    /** Create synthetic FAQ content with no merchant identity or external payment dependencies. */
    private function item(string $slug, array $question, array $answer, bool $published = true): FaqItem
    {
        return FaqItem::create(['slug' => $slug, 'question' => $question, 'answer' => $answer,
            'created_by' => 1, 'is_published' => $published]);
    }

    /** Execute the real public controller against the selected locale and optional category filter. */
    private function search(string $locale, string $term, ?string $categoryId = null): array
    {
        app()->setLocale($locale);
        $request = Request::create('/api/faq', 'GET', ['search' => $term, 'category_id' => $categoryId]);

        return app(FaqController::class)->index($request)->getData(true)['data'];
    }
}
