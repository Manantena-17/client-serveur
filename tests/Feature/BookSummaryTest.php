<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_and_stores_a_summary(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['content' => 'Un resume de test.']]],
            ]),
        ]);

        $book = Book::factory()->create(['summary' => null]);

        $this->postJson("/api/books/{$book->id}/generate-summary")
            ->assertOk()
            ->assertJsonPath('data.summary', 'Un resume de test.');

        $this->assertSame('Un resume de test.', $book->fresh()->summary);
    }

    public function test_it_refuses_to_overwrite_without_force(): void
    {
        $book = Book::factory()->create(['summary' => 'Deja la.']);

        $this->postJson("/api/books/{$book->id}/generate-summary")
            ->assertStatus(409);
    }

    public function test_it_returns_502_when_openai_fails(): void
    {
        config(['services.openai.key' => 'test-key']);
        Http::fake(['api.openai.com/*' => Http::response([], 500)]);

        $book = Book::factory()->create(['summary' => null]);

        $this->postJson("/api/books/{$book->id}/generate-summary")
            ->assertStatus(502);
    }
}
