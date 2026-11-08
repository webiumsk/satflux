<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\FaqCategoryController;
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

    /** Nonblank canonical Czech text hides superseded legacy question and answer search terms. */
    public function test_nonblank_canonical_czech_suppresses_legacy_question_and_answer_search_matches(): void
    {
        $this->item('canonical-precedence', ['cs' => 'Canonical question', 'cz' => 'HiddenQuestionNeedle'],
            ['cs' => 'Canonical answer', 'cz' => 'HiddenAnswerNeedle']);
        $this->assertSame([], $this->search('cs', 'HiddenQuestionNeedle'));
        $this->assertSame([], $this->search('cs', 'HiddenAnswerNeedle'));
    }

    /** Canonical availability is checked per field, so one translated field cannot suppress the other's fallback. */
    public function test_czech_legacy_question_and_answer_fallbacks_are_independent(): void
    {
        $question = $this->item('canonical-question-only', ['cs' => 'Canonical question', 'cz' => 'HiddenQuestionNeedle'],
            ['cs' => " \t", 'cz' => 'AnswerFallbackNeedle']);
        $answer = $this->item('canonical-answer-only', ['cs' => '', 'cz' => 'QuestionFallbackNeedle'],
            ['cs' => 'Canonical answer', 'cz' => 'HiddenAnswerNeedle']);
        $this->assertSame([], $this->search('cs', 'HiddenQuestionNeedle'));
        $this->assertSame([], $this->search('cs', 'HiddenAnswerNeedle'));
        $this->assertSame([$question->id], array_column($this->search('cs', 'AnswerFallbackNeedle'), 'id'));
        $this->assertSame([$answer->id], array_column($this->search('cs', 'QuestionFallbackNeedle'), 'id'));
    }

    /** Missing/null/empty canonical values and exactly PHP-trim whitespace allow legacy search and display. */
    public function test_czech_legacy_search_matches_missing_null_empty_and_php_trim_blank_canonical_fields(): void
    {
        $variants = [[], ['cs' => null], ['cs' => ''], ['cs' => ' '], ['cs' => "\t"], ['cs' => "\n"],
            ['cs' => "\r"], ['cs' => "\x0B"], ['cs' => " \t\n\r\x0B"]];
        foreach ($variants as $index => $canonical) {
            $questionNeedle = 'QuestionBlank'.$index.'Needle';
            $answerNeedle = 'AnswerBlank'.$index.'Needle';
            $item = $this->item('blank-variant-'.$index, $canonical + ['cz' => $questionNeedle], $canonical + ['cz' => $answerNeedle]);
            $questionResults = $this->search('cs', $questionNeedle);
            $answerResults = $this->search('cs', $answerNeedle);
            $this->assertSame([$item->id], array_column($questionResults, 'id'));
            $this->assertSame([$item->id], array_column($answerResults, 'id'));
            $this->assertSame($questionNeedle, $questionResults[0]['question']);
            $this->assertSame($answerNeedle, $answerResults[0]['answer']);
        }
    }

    /** Formfeed and nonbreaking spaces remain nonblank because PHP trim does not remove them. */
    public function test_non_php_trim_whitespace_does_not_enable_hidden_legacy_search(): void
    {
        foreach (["\x0C", "\u{00A0}", " \tCanonical text\r\n"] as $index => $canonical) {
            $this->item('nonblank-variant-'.$index, ['cs' => $canonical, 'cz' => 'HiddenQuestion'.$index.'Needle'],
                ['cs' => $canonical, 'cz' => 'HiddenAnswer'.$index.'Needle']);
            $this->assertSame([], $this->search('cs', 'HiddenQuestion'.$index.'Needle'));
            $this->assertSame([], $this->search('cs', 'HiddenAnswer'.$index.'Needle'));
        }
    }

    /** The existing English predicates remain searchable even when both Czech canonical fields are present. */
    public function test_english_search_remains_available_with_nonblank_canonical_czech_translations(): void
    {
        $item = $this->item('english-with-canonical', ['en' => 'EnglishQuestionNeedle', 'cs' => 'Canonical question'],
            ['en' => 'EnglishAnswerNeedle', 'cs' => 'Canonical answer']);
        $this->assertSame([$item->id], array_column($this->search('cs', 'EnglishQuestionNeedle'), 'id'));
        $this->assertSame([$item->id], array_column($this->search('cs', 'EnglishAnswerNeedle'), 'id'));
        $this->assertSame('Canonical question', $this->search('cs', 'EnglishQuestionNeedle')[0]['question']);
        $this->assertSame('Canonical answer', $this->search('cs', 'EnglishAnswerNeedle')[0]['answer']);
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

    /** Actual public search requests use the same allowlisted query locale for SQL predicates and response text. */
    public function test_public_route_search_uses_explicit_locale_and_rejects_malformed_locale_sql(): void
    {
        $slovak = $this->item('slovak', ['en' => 'English neutral', 'sk' => 'SlovakNeedle'], ['en' => 'Answer', 'sk' => 'Slovak answer']);
        $legacy = $this->item('legacy-query', ['en' => 'English neutral', 'cz' => 'LegacyNeedle'], ['en' => 'Answer']);
        app()->setLocale('en');
        $this->getJson('/api/faq?'.http_build_query(['locale' => 'sk', 'search' => 'slovakneedle']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $slovak->id)
            ->assertJsonPath('data.0.question', 'SlovakNeedle');
        $this->getJson('/api/faq?'.http_build_query(['locale' => 'cs', 'search' => 'legacyneedle']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $legacy->id);
        foreach (["sk' OR 1=1 --", ['sk'], 'unsupported'] as $locale) {
            $this->getJson('/api/faq?'.http_build_query(['locale' => $locale, 'search' => 'slovakneedle']))
                ->assertOk()->assertJsonCount(0, 'data');
        }
        $this->assertSame('en', app()->getLocale());
    }

    /** PostgreSQL sorts JSON category names by canonical text after primary order, with stable ties and counts. */
    public function test_admin_category_index_orders_json_names_as_text_and_preserves_item_counts(): void
    {
        $first = FaqCategory::create(['slug' => 'primary-first', 'order' => 0, 'name' => ['en' => 'Zebra']]);
        $alpha = FaqCategory::create(['slug' => 'alpha', 'order' => 1, 'name' => ['en' => 'Alpha']]);
        $tied = FaqCategory::create(['slug' => 'alpha-tie', 'order' => 1, 'name' => ['en' => 'Alpha']]);
        $zulu = FaqCategory::create(['slug' => 'zulu', 'order' => 1, 'name' => ['en' => 'Zulu']]);
        $missing = FaqCategory::create(['slug' => 'missing-english', 'order' => 1, 'name' => ['sk' => 'Slovensky']]);
        $published = $this->item('count-published', ['en' => 'Question'], ['en' => 'Answer']);
        $draft = $this->item('count-draft', ['en' => 'Draft'], ['en' => 'Draft answer'], false);
        $published->update(['category_id' => $alpha->id]);
        $draft->update(['category_id' => $alpha->id]);
        $data = app(FaqCategoryController::class)->index(Request::create('/api/admin/faq/categories'))->getData(true)['data'];
        $tieIds = [$alpha->id, $tied->id];
        sort($tieIds, SORT_STRING);
        $this->assertSame([$first->id, $missing->id, ...$tieIds, $zulu->id], array_column($data, 'id'));
        $counts = array_column($data, 'items_count', 'id');
        $this->assertSame(2, $counts[$alpha->id]);
        $this->assertSame(0, $counts[$missing->id]);
        $this->assertSame('Alpha', collect($data)->firstWhere('id', $alpha->id)['name']['en']);
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
