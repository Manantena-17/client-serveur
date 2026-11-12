<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_articles_paginated_by_fifteen(): void
    {
        Article::factory()->count(16)->create();

        $this->getJson('/api/articles')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15);
    }

    public function test_show_returns_the_requested_article(): void
    {
        $article = Article::factory()->create();

        $this->getJson("/api/articles/{$article->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonPath('data.title', $article->title)
            ->assertJsonPath('data.content', $article->content);
    }

    public function test_store_creates_an_article(): void
    {
        $payload = [
            'title' => 'A new article',
            'content' => 'Article body',
            'published_at' => '2030-01-02 03:04:05',
        ];

        $this->postJson('/api/articles', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title', $payload['title'])
            ->assertJsonPath('data.content', $payload['content']);

        $this->assertDatabaseHas('articles', $payload);
    }

    public function test_store_rejects_missing_required_fields(): void
    {
        $this->postJson('/api/articles', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_store_rejects_an_invalid_published_at_value(): void
    {
        $this->postJson('/api/articles', [
            'title' => 'A new article',
            'content' => 'Article body',
            'published_at' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['published_at']);
    }

    public function test_update_replaces_all_article_fields(): void
    {
        $article = Article::factory()->create([
            'title' => 'Old title',
            'content' => 'Old body',
            'published_at' => '2025-01-01 00:00:00',
        ]);
        $payload = [
            'title' => 'Updated title',
            'content' => 'Updated body',
            'published_at' => null,
        ];

        $this->putJson("/api/articles/{$article->id}", $payload)
            ->assertOk()
            ->assertJsonPath('data.title', $payload['title'])
            ->assertJsonPath('data.content', $payload['content'])
            ->assertJsonPath('data.published_at', null);

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            ...$payload,
        ]);
    }

    public function test_update_requires_a_complete_article_representation(): void
    {
        $article = Article::factory()->create();

        $this->putJson("/api/articles/{$article->id}", [
            'title' => 'Updated title',
            'content' => 'Updated body',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['published_at']);
    }

    public function test_destroy_deletes_the_article_and_returns_no_content(): void
    {
        $article = Article::factory()->create();

        $this->deleteJson("/api/articles/{$article->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('articles', ['id' => $article->id]);
    }

    public function test_show_returns_not_found_for_a_missing_article(): void
    {
        $this->getJson('/api/articles/999')
            ->assertNotFound();
    }
}
