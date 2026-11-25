<?php

namespace App\Services;

use App\Models\Book;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class BookSummaryService
{
    private const URL         = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_RETRIES = 2;   // 3 calls in total
    private const MAX_DELAY   = 8;   // seconds, cap for each wait

    public function generate(Book $book): string
    {
        [$key, $model] = $this->credentials();
        $payload       = $this->payload($book, $model);
        $attempt       = 0;

        while (true) {
            try {
                $response = Http::withToken($key)
                    ->connectTimeout(20)
                    ->timeout(60)
                    ->withOptions(['force_ip_resolve' => 'v4'])
                    ->post(self::URL, $payload);
            } catch (ConnectionException $e) {
                Log::warning('OpenRouter connection failed', [
                    'error'   => $e->getMessage(),
                    'attempt' => $attempt + 1,
                    'book_id' => $book->id,
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    sleep(2);
                    $attempt++;
                    continue;
                }

                throw $e;   // the controller turns it into a 503
            }

            if ($response->successful()) {
                break;
            }

            $status = $response->status();

            Log::warning('OpenRouter request failed', [
                'status'  => $status,
                'model'   => $model,
                'message' => $response->json('error.message'),
                'detail'  => $response->json('error.metadata.raw'),
                'book_id' => $book->id,
            ]);

            if ($status === 429 && $attempt < self::MAX_RETRIES) {
                $retryAfter = (int) $response->header('Retry-After');
                $delay      = min(max($retryAfter, 3 * ($attempt + 1)), self::MAX_DELAY);

                sleep($delay);
                $attempt++;
                continue;
            }

            throw new RuntimeException('OpenRouter request failed with status ' . $status, $status);
        }

        $text = trim((string) $response->json('choices.0.message.content'));

        if ($text === '') {
            throw new RuntimeException('OpenRouter returned an empty summary.');
        }

        return $text;
    }

    /**
     * Opens the streaming connection. Errors (429, 401...) are thrown HERE,
     * before any SSE header is sent, so the controller can answer a normal 503/502.
     */
    public function openStream(Book $book): StreamInterface
    {
        [$key, $model] = $this->credentials();

        $response = Http::withToken($key)
            ->connectTimeout(20)
            ->timeout(90)
            ->withOptions(['stream' => true, 'force_ip_resolve' => 'v4'])
            ->post(self::URL, $this->payload($book, $model) + ['stream' => true]);

        if (! $response->successful()) {
            $status = $response->status();

            Log::warning('OpenRouter stream request failed', [
                'status'  => $status,
                'model'   => $model,
                'message' => $response->json('error.message'),
                'book_id' => $book->id,
            ]);

            throw new RuntimeException('OpenRouter request failed with status ' . $status, $status);
        }

        return $response->toPsrResponse()->getBody();
    }

    /**
     * Reads the SSE stream from OpenRouter and yields each piece of text.
     *
     * @return Generator<int, string>
     */
    public function chunks(StreamInterface $body): Generator
    {
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($pos = strpos($buffer, "\n")) !== false) {
                $line   = trim(substr($buffer, 0, $pos));
                $buffer = substr($buffer, $pos + 1);

                // Skips blank lines and ": OPENROUTER PROCESSING" comments
                if (! str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));

                if ($data === '[DONE]') {
                    return;
                }

                $json = json_decode($data, true);

                if (! is_array($json)) {
                    continue;
                }

                if (isset($json['error'])) {
                    throw new RuntimeException(
                        'OpenRouter stream error: ' . ($json['error']['message'] ?? 'unknown')
                    );
                }

                $delta = $json['choices'][0]['delta']['content'] ?? '';

                if (is_string($delta) && $delta !== '') {
                    yield $delta;
                }
            }
        }
    }

    /** @return array{0: string, 1: string} */
    private function credentials(): array
    {
        $key   = config('services.openrouter.key');
        $model = config('services.openrouter.model');

        if (! $key || ! $model) {
            throw new RuntimeException('OpenRouter is not configured.');
        }

        return [$key, $model];
    }

    /** @return array<string, mixed> */
    private function payload(Book $book, string $model): array
    {
        $book->loadMissing('author');
        $author = $book->author?->full_name ?? 'unknown';

        return [
            'model'    => $model,
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
    }
}
