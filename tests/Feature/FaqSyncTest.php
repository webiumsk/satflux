<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\FaqCategoryController;
use App\Http\Requests\FaqItemRequest;
use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Services\Faq\FaqCatalog;
use App\Services\Faq\FaqSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FaqSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // This suite uses only a disposable in-memory database and its three minimal tables.
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('role');
        });
        (require database_path('migrations/2026_01_24_123843_create_faq_categories_table.php'))->up();
        (require database_path('migrations/2026_01_24_123854_create_faq_items_table.php'))->up();
        (require database_path('migrations/2026_11_08_120000_add_content_sync_metadata_to_faq_tables.php'))->up();
        DB::table('users')->insert([['id' => 1, 'role' => 'support'], ['id' => 2, 'role' => 'free'], ['id' => 3, 'role' => 'admin']]);
        $catalog = Mockery::mock(FaqCatalog::class);
        $catalog->shouldReceive('load')->andReturn($this->catalog());
        $this->app->instance(FaqCatalog::class, $catalog);
    }

    public function test_preview_and_offline_check_do_not_write(): void
    {
        $this->artisan('faq:sync', ['--check' => true])->assertSuccessful();
        $this->artisan('faq:sync')->expectsOutputToContain('Preview only')->assertSuccessful();
        $this->assertDatabaseCount('faq_items', 0);
        $this->assertDatabaseCount('faq_categories', 0);
    }

    public function test_offline_check_succeeds_with_an_unavailable_database_connection(): void
    {
        config(['database.default' => 'unavailable-faq-check-proof']);
        $this->artisan('faq:sync', ['--check' => true])->assertSuccessful();
    }

    public function test_apply_requires_explicit_existing_staff_actor(): void
    {
        foreach ([[], ['--user' => '2'], ['--user' => '999'], ['--user' => '0'], ['--user' => '1.0']] as $options) {
            $this->artisan('faq:sync', array_merge(['--apply' => true], $options))->assertFailed();
        }
        $this->assertDatabaseCount('faq_items', 0);
        $this->artisan('faq:sync', ['--apply' => true, '--user' => '3'])->assertSuccessful();
        $this->assertSame(3, FaqItem::firstOrFail()->created_by);
    }

    public function test_import_creates_drafts_and_repeated_import_is_noop(): void
    {
        $sync = app(FaqSynchronizer::class);
        $sync->apply($this->catalog(), 1);
        $item = FaqItem::firstOrFail();
        $category = FaqCategory::firstOrFail();
        $this->assertFalse($item->is_published);
        $this->assertSame(1, $item->created_by);
        $this->assertArrayNotHasKey('content_sync_hash', $item->toArray());
        $this->assertArrayNotHasKey('content_sync_key', $category->toArray());
        $item->forceFill(['is_published' => true, 'view_count' => 5, 'helpful_count' => 4,
            'question' => $item->question + ['cz' => 'Old Czech', 'fr' => 'French']])->save();
        $category->forceFill(['is_active' => false])->save();
        $before = $item->fresh()->getRawOriginal();
        $plan = $sync->apply($this->catalog(), 1);
        $this->assertSame(['unchanged', 'unchanged'], array_column($plan, 'action'));
        $this->assertSame($before, $item->fresh()->getRawOriginal());
        $this->assertFalse($category->fresh()->is_active);
        $this->assertDatabaseCount('faq_items', 1);
        $this->assertDatabaseCount('faq_categories', 1);
    }

    public function test_reviewed_update_preserves_publication_counters_and_extra_locales(): void
    {
        $sync = app(FaqSynchronizer::class);
        $sync->apply($this->catalog(), 1);
        $item = FaqItem::firstOrFail();
        $item->forceFill(['is_published' => true, 'view_count' => 9,
            'answer' => $item->answer + ['cz' => 'Legacy', 'fr' => 'French']])->save();
        $content = $this->catalog();
        $content['items'][0]['answer']['sk'] = 'Reviewed Slovak';
        $plan = $sync->apply($content, 3);
        $this->assertSame('update', $plan[1]['action']);
        $item->refresh();
        $this->assertSame('Reviewed Slovak', $item->answer['sk']);
        $this->assertSame('Legacy', $item->answer['cz']);
        $this->assertSame('French', $item->answer['fr']);
        $this->assertTrue($item->is_published);
        $this->assertSame(9, $item->view_count);
        $this->assertSame(3, $item->updated_by);
    }

    public function test_manual_item_edit_aborts_entire_batch_before_category_changes(): void
    {
        $sync = app(FaqSynchronizer::class);
        $sync->apply($this->catalog(), 1);
        $item = FaqItem::firstOrFail();
        $item->update(['question' => array_replace($item->question, ['en' => 'Manually edited'])]);
        $content = $this->catalog();
        $content['categories'][0]['name']['en'] = 'Changed category';
        $this->assertSame('conflict', $sync->preview($content)[1]['action']);
        try {
            $sync->apply($content, 1);
            $this->fail('Expected a manual-edit conflict.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('conflicts detected', $exception->getMessage());
        }
        $this->assertSame('Category en', FaqCategory::firstOrFail()->name['en']);
        $this->assertSame('Manually edited', $item->fresh()->question['en']);
    }

    public function test_manual_category_edit_and_renamed_managed_rows_are_conflicts(): void
    {
        $sync = app(FaqSynchronizer::class);
        $sync->apply($this->catalog(), 1);
        $category = FaqCategory::firstOrFail();
        $category->update(['slug' => 'renamed-category']);
        FaqItem::firstOrFail()->update(['slug' => 'renamed-item']);
        $this->assertSame(['conflict', 'conflict'], array_column($sync->preview($this->catalog()), 'action'));
        $this->artisan('faq:sync', ['--apply' => true, '--user' => '1'])->assertFailed();
        $this->assertDatabaseCount('faq_items', 1);
        $this->assertDatabaseCount('faq_categories', 1);
    }

    public function test_existing_unmanaged_slugs_are_never_adopted_or_overwritten(): void
    {
        FaqCategory::create(['slug' => 'start', 'name' => ['en' => 'Existing'], 'order' => 0]);
        $this->artisan('faq:sync')->expectsOutputToContain('conflict')->assertFailed();
        $this->artisan('faq:sync', ['--apply' => true, '--user' => '1'])->assertFailed();
        $this->assertDatabaseCount('faq_items', 0);
        $this->assertSame('Existing', FaqCategory::firstOrFail()->name['en']);
    }

    public function test_late_database_failure_rolls_back_categories_and_all_prior_items(): void
    {
        $content = $this->catalog();
        $second = $content['items'][0];
        $second['slug'] = 'second';
        $content['items'][] = $second;
        FaqItem::creating(function (FaqItem $item) {
            if ($item->slug === 'second') {
                throw new RuntimeException('Synthetic late failure');
            }
        });
        try {
            app(FaqSynchronizer::class)->apply($content, 1);
            $this->fail('Expected late failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic late failure', $exception->getMessage());
        } finally {
            FaqItem::flushEventListeners();
        }
        $this->assertDatabaseCount('faq_items', 0);
        $this->assertDatabaseCount('faq_categories', 0);
    }

    public function test_conflicting_managed_and_unmanaged_same_slug_fails_closed(): void
    {
        $sync = app(FaqSynchronizer::class);
        $sync->apply($this->catalog(), 1);
        FaqCategory::firstOrFail()->update(['slug' => 'renamed']);
        FaqCategory::create(['slug' => 'start', 'name' => ['en' => 'Collision']]);
        $this->artisan('faq:sync', ['--apply' => true, '--user' => '1'])->assertFailed();
        $this->assertSame('Collision', FaqCategory::where('slug', 'start')->firstOrFail()->name['en']);
    }

    public function test_apply_replans_after_a_stale_preview_and_preserves_new_unmanaged_content(): void
    {
        $sync = app(FaqSynchronizer::class);
        $this->assertSame('create', $sync->preview($this->catalog())[0]['action']);
        FaqCategory::create(['slug' => 'start', 'name' => ['en' => 'Concurrent editor']]);
        $this->artisan('faq:sync', ['--apply' => true, '--user' => '1'])->assertFailed();
        $this->assertSame('Concurrent editor', FaqCategory::firstOrFail()->name['en']);
        $this->assertDatabaseCount('faq_items', 0);
    }

    public function test_admin_request_saves_canonical_czech_without_discarding_stored_legacy_text(): void
    {
        app(FaqSynchronizer::class)->apply($this->catalog(), 1);
        $item = FaqItem::firstOrFail();
        $item->update(['question' => $item->question + ['cz' => 'Legacy question', 'fr' => 'French question'],
            'answer' => $item->answer + ['cz' => 'Legacy answer', 'fr' => 'French answer']]);
        $this->app['router']->put('/faq-locale-test/{item}', function (FaqItemRequest $request, FaqItem $item) {
            $item->update($request->validated());

            return response()->json($item);
        })->middleware(SubstituteBindings::class);
        $this->putJson('/faq-locale-test/'.$item->id, ['slug' => 'first',
            'question' => ['cs' => 'Nová otázka'], 'answer' => ['cs' => 'Nová odpověď']])->assertOk();
        $item->refresh();
        $this->assertSame('Nová otázka', $item->question['cs']);
        $this->assertSame('Legacy question', $item->question['cz']);
        $this->assertSame('French answer', $item->answer['fr']);
        $this->assertSame('Answer en', $item->answer['en']);
    }

    public function test_category_save_keeps_omitted_legacy_and_extra_languages(): void
    {
        $category = FaqCategory::create(['slug' => 'manual', 'name' => ['en' => 'English', 'cz' => 'Legacy', 'fr' => 'French'],
            'description' => ['en' => 'English description', 'cz' => 'Legacy description']]);
        $request = Request::create('/category', 'PUT', ['name' => ['cs' => 'Český název'], 'description' => ['cs' => 'Český popis']]);
        app(FaqCategoryController::class)->update($request, $category);
        $category->refresh();
        $this->assertSame('Český název', $category->name['cs']);
        $this->assertSame('Legacy', $category->name['cz']);
        $this->assertSame('French', $category->name['fr']);
        $this->assertSame('Legacy description', $category->description['cz']);
    }

    public function test_unsupported_combined_command_options_are_rejected(): void
    {
        $this->artisan('faq:sync', ['--check' => true, '--apply' => true])->assertFailed();
        $this->artisan('faq:sync', ['--user' => '1'])->assertFailed();
        $this->assertDatabaseCount('faq_items', 0);
    }

    private function catalog(): array
    {
        $names = $questions = $answers = [];
        foreach (FaqCatalog::LOCALES as $locale) {
            $names[$locale] = 'Category '.$locale;
            $questions[$locale] = 'Question '.$locale;
            $answers[$locale] = 'Answer '.$locale;
        }

        return ['version' => 1,
            'categories' => [['slug' => 'start', 'order' => 1, 'name' => $names, 'description' => $names]],
            'items' => [['slug' => 'first', 'category' => 'start', 'order' => 1, 'question' => $questions, 'answer' => $answers]],
        ];
    }
}
