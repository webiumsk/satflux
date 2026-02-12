<?php

namespace Tests\Feature;

use App\Models\FaqCategory;
use App\Models\FaqItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaqTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    /** @test */
    public function index_returns_empty_data_when_no_published_items(): void
    {
        $response = $this->getJson('/api/faq');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('categories', []);
    }

    /** @test */
    public function index_returns_only_published_items(): void
    {
        FaqItem::create([
            'slug' => 'published-one',
            'question' => ['en' => 'Published question?'],
            'answer' => ['en' => 'Yes.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);
        FaqItem::create([
            'slug' => 'draft-one',
            'question' => ['en' => 'Draft question?'],
            'answer' => ['en' => 'No.'],
            'category_id' => null,
            'order' => 1,
            'is_published' => false,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('published-one', $data[0]['slug']);
    }

    /** @test */
    public function index_includes_categories(): void
    {
        $category = FaqCategory::create([
            'slug' => 'general',
            'name' => ['en' => 'General'],
            'description' => ['en' => 'General questions'],
            'order' => 0,
            'is_active' => true,
        ]);
        FaqItem::create([
            'slug' => 'first',
            'question' => ['en' => 'First?'],
            'answer' => ['en' => 'First.'],
            'category_id' => $category->id,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.category.slug', 'general')
            ->assertJsonPath('data.0.category.name', 'General');
        $categories = $response->json('categories');
        $this->assertCount(1, $categories);
        $this->assertSame('general', $categories[0]['slug']);
    }

    /** @test */
    public function index_can_filter_by_category_id(): void
    {
        $cat1 = FaqCategory::create([
            'slug' => 'cat1',
            'name' => ['en' => 'Cat 1'],
            'order' => 0,
            'is_active' => true,
        ]);
        $cat2 = FaqCategory::create([
            'slug' => 'cat2',
            'name' => ['en' => 'Cat 2'],
            'order' => 1,
            'is_active' => true,
        ]);
        FaqItem::create([
            'slug' => 'in-cat1',
            'question' => ['en' => 'In cat1?'],
            'answer' => ['en' => 'Yes.'],
            'category_id' => $cat1->id,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);
        FaqItem::create([
            'slug' => 'in-cat2',
            'question' => ['en' => 'In cat2?'],
            'answer' => ['en' => 'Yes.'],
            'category_id' => $cat2->id,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq?category_id=' . $cat1->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('in-cat1', $data[0]['slug']);
    }

    /** @test */
    public function index_search_matches_question_and_answer(): void
    {
        FaqItem::create([
            'slug' => 'has-foo',
            'question' => ['en' => 'Does this contain foo?'],
            'answer' => ['en' => 'Yes, foo is here.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);
        FaqItem::create([
            'slug' => 'no-foo',
            'question' => ['en' => 'Something else?'],
            'answer' => ['en' => 'No.'],
            'category_id' => null,
            'order' => 1,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq?search=foo');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('has-foo', $data[0]['slug']);
    }

    /** @test */
    public function show_returns_item_by_slug_and_increments_view_count(): void
    {
        $item = FaqItem::create([
            'slug' => 'my-faq',
            'question' => ['en' => 'My question?'],
            'answer' => ['en' => 'My answer.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'view_count' => 0,
            'helpful_count' => 0,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq/my-faq');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'my-faq')
            ->assertJsonPath('data.question', 'My question?')
            ->assertJsonPath('data.answer', 'My answer.')
            ->assertJsonPath('data.view_count', 1);
        $item->refresh();
        $this->assertSame(1, $item->view_count);
    }

    /** @test */
    public function show_returns_404_for_unpublished_item(): void
    {
        FaqItem::create([
            'slug' => 'draft',
            'question' => ['en' => 'Draft?'],
            'answer' => ['en' => 'No.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => false,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq/draft');

        $response->assertStatus(404);
    }

    /** @test */
    public function show_returns_404_for_missing_slug(): void
    {
        $response = $this->getJson('/api/faq/non-existent-slug');

        $response->assertStatus(404);
    }

    /** @test */
    public function mark_helpful_increments_helpful_count(): void
    {
        $item = FaqItem::create([
            'slug' => 'helpful-faq',
            'question' => ['en' => 'Helpful?'],
            'answer' => ['en' => 'Yes.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'helpful_count' => 2,
            'created_by' => $this->author->id,
        ]);

        $response = $this->postJson('/api/faq/helpful-faq/helpful');

        $response->assertStatus(200)
            ->assertJsonPath('helpful_count', 3);
        $item->refresh();
        $this->assertSame(3, $item->helpful_count);
    }

    /** @test */
    public function mark_helpful_returns_404_for_unpublished_item(): void
    {
        FaqItem::create([
            'slug' => 'draft-helpful',
            'question' => ['en' => 'Draft?'],
            'answer' => ['en' => 'No.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => false,
            'created_by' => $this->author->id,
        ]);

        $response = $this->postJson('/api/faq/draft-helpful/helpful');

        $response->assertStatus(404);
    }

    /** @test */
    public function locale_fallback_returns_localized_content(): void
    {
        app()->setLocale('sk');
        FaqItem::create([
            'slug' => 'localized',
            'question' => ['en' => 'English?', 'sk' => 'Slovensky?'],
            'answer' => ['en' => 'English.', 'sk' => 'Slovensky.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/faq');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Slovensky?', $data[0]['question']);
        $this->assertSame('Slovensky.', $data[0]['answer']);
    }
}
