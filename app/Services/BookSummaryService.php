<?php

namespace App\Services;

use App\Models\Book;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BookSummaryService
{
    public function generate(Book $book): string
    {
        $key = config('services.openai.key');

        if (! $key) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $book->loadMissing('author');
        $author = $book->author?->full_name ?? 'unknown';

        $payload = [
            'model'    => config('services.openai.model'),   // "inclusionai/ling-3.2-flash:free"
            'messages' => [
                [
                    'role'    => 'system',
                    'content' => 'You write short, neutral book summaries for a library catalogue. '
                        . 'If you do not know the book, say you cannot give a reliable summary. '
                        . 'Never invent plot details.',
                ],
                [
                    'role'    => 'user',
                    'content' => "Title: {$book->title}\nAuthor: {$author}\nYear: {$book->publication_year}\n\n"
                        . 'Write a summary of 3 to 4 sentences, in French.',
                ],
            ],
        ];

        $maxAttempts = 3;
        $attempt = 0;

        while (true) {
            $response = Http::withToken($key)
                ->timeout(120)
                // 👇👇👇 LA SEULE LIGNE QUI CHANGE 👇👇👇
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if ($response->successful()) {
                break;
            }

            \Log::warning('OpenRouter request failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),   // ← lire pour debug
                'book_id' => $book->id,
            ]);

            $code = $response->json('error.code');
            $isRateLimit = $response->status() === 429;

            if ($isRateLimit && $attempt < $maxAttempts) {
                $delay = (int) ($response->header('Retry-After') ?: (2 ** $attempt));
                sleep($delay);
                $attempt++;
                continue;
            }

            throw new RuntimeException(
                'OpenRouter request failed with status ' . $response->status()
                . ($code ? " ({$code})" : '')
            );
        }

        $text = trim((string) $response->json('choices.0.message.content'));

        if ($text === '') {
            throw new RuntimeException('OpenRouter returned an empty summary.');
        }

        return $text;
    }
}
