<?php

namespace Tests\Feature;

use App\Models\DocumentationArticle;
use App\Models\DocumentationCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected User $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->author = User::factory()->create();
    }

    /** @test */
    public function index_returns_empty_data_when_no_published_articles(): void
    {
        $response = $this->getJson('/api/documentation');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('categories', []);
    }

    /** @test */
    public function index_returns_only_published_articles(): void
    {
        DocumentationArticle::create([
            'slug' => 'published-art',
            'title' => ['en' => 'Published title'],
            'content' => ['en' => 'Published content'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);
        DocumentationArticle::create([
            'slug' => 'draft-art',
            'title' => ['en' => 'Draft title'],
            'content' => ['en' => 'Draft content'],
            'category_id' => null,
            'order' => 1,
            'is_published' => false,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('published-art', $data[0]['slug']);
    }

    /** @test */
    public function index_includes_categories(): void
    {
        $category = DocumentationCategory::create([
            'slug' => 'guides',
            'name' => ['en' => 'Guides'],
            'description' => ['en' => 'Documentation guides'],
            'order' => 0,
            'is_active' => true,
        ]);
        DocumentationArticle::create([
            'slug' => 'first-art',
            'title' => ['en' => 'First article'],
            'content' => ['en' => 'Content here.'],
            'category_id' => $category->id,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.category.slug', 'guides')
            ->assertJsonPath('data.0.category.name', 'Guides');
        $categories = $response->json('categories');
        $this->assertCount(1, $categories);
        $this->assertSame('guides', $categories[0]['slug']);
    }

    /** @test */
    public function index_can_filter_by_category_id(): void
    {
        $cat1 = DocumentationCategory::create([
            'slug' => 'cat-a',
            'name' => ['en' => 'Cat A'],
            'order' => 0,
            'is_active' => true,
        ]);
        $cat2 = DocumentationCategory::create([
            'slug' => 'cat-b',
            'name' => ['en' => 'Cat B'],
            'order' => 1,
            'is_active' => true,
        ]);
        DocumentationArticle::create([
            'slug' => 'in-cat-a',
            'title' => ['en' => 'In cat A'],
            'content' => ['en' => 'Content A.'],
            'category_id' => $cat1->id,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);
        DocumentationArticle::create([
            'slug' => 'in-cat-b',
            'title' => ['en' => 'In cat B'],
            'content' => ['en' => 'Content B.'],
            'category_id' => $cat2->id,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation?category_id=' . $cat1->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('in-cat-a', $data[0]['slug']);
    }

    /** @test */
    public function index_search_matches_title_and_content(): void
    {
        DocumentationArticle::create([
            'slug' => 'has-bar',
            'title' => ['en' => 'Article containing bar'],
            'content' => ['en' => 'Some content with bar inside.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);
        DocumentationArticle::create([
            'slug' => 'no-bar',
            'title' => ['en' => 'Other title'],
            'content' => ['en' => 'Other content.'],
            'category_id' => null,
            'order' => 1,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation?search=bar');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('has-bar', $data[0]['slug']);
    }

    /** @test */
    public function show_returns_article_by_slug(): void
    {
        DocumentationArticle::create([
            'slug' => 'my-doc',
            'title' => ['en' => 'My doc title'],
            'content' => ['en' => 'My doc content.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation/my-doc');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'my-doc')
            ->assertJsonPath('data.title', 'My doc title')
            ->assertJsonPath('data.content', 'My doc content.');
    }

    /** @test */
    public function show_returns_404_for_unpublished_article(): void
    {
        DocumentationArticle::create([
            'slug' => 'draft-doc',
            'title' => ['en' => 'Draft'],
            'content' => ['en' => 'Draft content.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => false,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation/draft-doc');

        $response->assertStatus(404);
    }

    /** @test */
    public function show_returns_404_for_missing_slug(): void
    {
        $response = $this->getJson('/api/documentation/non-existent');

        $response->assertStatus(404);
    }

    /** @test */
    public function locale_fallback_returns_localized_content(): void
    {
        app()->setLocale('sk');
        DocumentationArticle::create([
            'slug' => 'localized-doc',
            'title' => ['en' => 'English title', 'sk' => 'Slovenský nadpis'],
            'content' => ['en' => 'English content.', 'sk' => 'Slovenský obsah.'],
            'category_id' => null,
            'order' => 0,
            'is_published' => true,
            'created_by' => $this->author->id,
        ]);

        $response = $this->getJson('/api/documentation');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Slovenský nadpis', $data[0]['title']);
        $this->assertSame('Slovenský obsah.', $data[0]['content']);
    }
}
