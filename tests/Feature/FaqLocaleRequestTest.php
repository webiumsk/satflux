<?php

namespace Tests\Feature;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaqLocaleRequestTest extends TestCase
{
    private FaqCategory $category;

    /** Prepare only synthetic translated FAQ content in an isolated in-memory SQLite database. */
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        (require database_path('migrations/2026_01_24_123843_create_faq_categories_table.php'))->up();
        (require database_path('migrations/2026_01_24_123854_create_faq_items_table.php'))->up();
        DB::table('users')->insert(['id' => 1]);
        $names = $descriptions = $questions = $answers = [];
        foreach (['en', 'sk', 'es', 'de', 'cs'] as $locale) {
            $names[$locale] = 'Category '.$locale;
            $descriptions[$locale] = 'Description '.$locale;
            $questions[$locale] = 'Question '.$locale;
            $answers[$locale] = 'Answer '.$locale;
        }
        $this->category = FaqCategory::create(['slug' => 'translated', 'name' => $names, 'description' => $descriptions]);
        FaqItem::create(['slug' => 'translated', 'question' => $questions, 'answer' => $answers,
            'category_id' => $this->category->id, 'created_by' => 1, 'is_published' => true]);
    }

    /** Public list/detail routes honor all five explicit locales, including category labels and descriptions. */
    public function test_explicit_supported_query_locales_localize_public_list_and_detail_routes(): void
    {
        app()->setLocale('en');
        foreach (['en', 'sk', 'es', 'de', 'cs'] as $locale) {
            $this->getJson('/api/faq?locale='.$locale, ['Accept-Language' => 'fr'])
                ->assertOk()->assertJsonPath('data.0.question', 'Question '.$locale)
                ->assertJsonPath('data.0.answer', 'Answer '.$locale)
                ->assertJsonPath('data.0.category.name', 'Category '.$locale)
                ->assertJsonPath('categories.0.name', 'Category '.$locale)
                ->assertJsonPath('categories.0.description', 'Description '.$locale);
            $this->getJson('/api/faq/translated?locale='.$locale)
                ->assertOk()->assertJsonPath('data.question', 'Question '.$locale)
                ->assertJsonPath('data.answer', 'Answer '.$locale)
                ->assertJsonPath('data.category.name', 'Category '.$locale);
            $this->assertSame('en', app()->getLocale());
        }
    }

    /** Malformed/unsupported query values use supported application locale or English without leaking state. */
    public function test_invalid_query_locales_fall_back_safely_for_both_public_routes(): void
    {
        foreach (['de' => 'de', 'unsupported' => 'en'] as $appLocale => $expected) {
            foreach ([null, '', 'fr', 'cz', 'SK', '1', "sk' OR 1=1 --", ['sk'], ['nested' => ['sk']]] as $value) {
                app()->setLocale($appLocale);
                $query = http_build_query(['locale' => $value]);
                $this->getJson('/api/faq?'.$query)->assertOk()
                    ->assertJsonPath('data.0.question', 'Question '.$expected)
                    ->assertJsonPath('categories.0.description', 'Description '.$expected);
                $this->getJson('/api/faq/translated?'.$query)->assertOk()
                    ->assertJsonPath('data.answer', 'Answer '.$expected)
                    ->assertJsonPath('data.category.name', 'Category '.$expected);
                $this->assertSame($appLocale, app()->getLocale());
            }
        }
    }

    /** A valid query wins even over an unsupported app locale, and missing translations retain English fallback. */
    public function test_query_locale_precedence_and_english_fallback_are_preserved(): void
    {
        app()->setLocale('unsupported');
        $this->getJson('/api/faq?locale=sk')->assertOk()->assertJsonPath('data.0.question', 'Question sk');
        $item = FaqItem::where('slug', 'translated')->firstOrFail();
        $item->update(['question' => ['en' => 'English only'], 'answer' => ['en' => 'English answer']]);
        $this->category->update(['name' => ['en' => 'English category'], 'description' => ['en' => 'English description']]);
        $this->getJson('/api/faq?locale=sk')->assertOk()->assertJsonPath('data.0.question', 'English only')
            ->assertJsonPath('categories.0.description', 'English description');
        $this->getJson('/api/faq/translated?locale=sk')->assertOk()->assertJsonPath('data.answer', 'English answer')
            ->assertJsonPath('data.category.name', 'English category');
        $this->assertSame('unsupported', app()->getLocale());
    }

    /** Canonical Czech wins when present; legacy cz text remains the fallback for omitted or blank cs. */
    public function test_czech_query_uses_canonical_and_legacy_fallbacks_on_actual_routes(): void
    {
        $item = FaqItem::where('slug', 'translated')->firstOrFail();
        $item->update(['question' => ['en' => 'English', 'cs' => '', 'cz' => 'Legacy question'],
            'answer' => ['en' => 'English answer', 'cs' => 'Canonical answer', 'cz' => 'Legacy answer']]);
        $this->category->update(['name' => ['en' => 'English category', 'cz' => 'Legacy category'],
            'description' => ['en' => 'English description', 'cs' => '', 'cz' => 'Legacy description']]);
        $this->getJson('/api/faq?locale=cs')->assertOk()->assertJsonPath('data.0.question', 'Legacy question')
            ->assertJsonPath('data.0.answer', 'Canonical answer')->assertJsonPath('data.0.category.name', 'Legacy category')
            ->assertJsonPath('categories.0.description', 'Legacy description');
        $this->getJson('/api/faq/translated?locale=cs')->assertOk()->assertJsonPath('data.question', 'Legacy question')
            ->assertJsonPath('data.answer', 'Canonical answer')->assertJsonPath('data.category.name', 'Legacy category');
    }

    /** Locale selection cannot publish drafts, bypass category filters or change unknown-slug 404 behavior. */
    public function test_locale_requests_preserve_publication_category_filters_and_not_found_behavior(): void
    {
        FaqItem::create(['slug' => 'draft', 'question' => ['sk' => 'Draft'], 'answer' => ['sk' => 'Draft answer'],
            'created_by' => 1, 'is_published' => false]);
        $other = FaqCategory::create(['slug' => 'other', 'name' => ['en' => 'Other']]);
        $this->getJson('/api/faq?'.http_build_query(['locale' => 'sk', 'category_id' => $this->category->id]))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.question', 'Question sk');
        $this->getJson('/api/faq?'.http_build_query(['locale' => 'sk', 'category_id' => $other->id]))
            ->assertOk()->assertJsonCount(0, 'data');
        app()->setLocale('de');
        $this->getJson('/api/faq/draft?locale=sk')->assertNotFound();
        $this->assertSame('de', app()->getLocale());
        $this->getJson('/api/faq/not-found?locale=cs')->assertNotFound();
        $this->assertSame('de', app()->getLocale());
    }
}
