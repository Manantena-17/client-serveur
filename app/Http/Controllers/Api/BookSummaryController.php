<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateBookSummaryRequest;
use App\Http\Resources\BookSummaryResource;
use App\Models\Book;
use App\Services\BookSummaryService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BookSummaryController extends Controller
{
    // GET /api/v1/books/{book}/summary  (public)
    public function show(Book $book): JsonResponse
    {
        if (! $book->summary) {
            return response()->json([
                'success' => false,
                'message' => "Ce livre n'a pas encore de résumé.",
            ], HttpResponse::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data'    => (new BookSummaryResource($book))->resolve(),
        ]);
    }

    // POST /api/v1/books/{book}/summary  (token required)
    public function store(
        GenerateBookSummaryRequest $request,
        Book $book,
        BookSummaryService $service,
    ): JsonResponse {
        // A summary already exists: return it without calling the AI
        if ($book->summary && ! $request->boolean('force')) {
            return response()->json([
                'success' => true,
                'data'    => (new BookSummaryResource($book))->resolve(),
                'message' => 'Résumé existant retourné (pas de nouvel appel API)',
            ]);
        }

        try {
            $summary = $service->generate($book);
        } catch (ConnectionException $e) {
            Log::warning('Summary service unreachable: ' . $e->getMessage(), ['book_id' => $book->id]);

            return $this->unavailable();
        } catch (RuntimeException $e) {
            Log::warning('Summary generation failed: ' . $e->getMessage(), ['book_id' => $book->id]);

            return $this->failure($e);
        }

        $book->update([
            'summary'              => $summary,
            'summary_generated_by' => config('services.openrouter.model'),
            'summary_generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => (new BookSummaryResource($book->refresh()))->resolve(),
        ]);
    }

    // GET /api/v1/books/{book}/summary/stream  (token required, SSE)
    public function stream(
        GenerateBookSummaryRequest $request,
        Book $book,
        BookSummaryService $service,
    ): StreamedResponse|JsonResponse {
        // A summary already exists: a single "done" event, no AI call
        if ($book->summary && ! $request->boolean('force')) {
            return $this->sse(function () use ($book) {
                $this->emit('done', [
                    'success' => true,
                    'data'    => (new BookSummaryResource($book))->resolve(),
                    'message' => 'Résumé existant retourné (pas de nouvel appel API)',
                ]);
            });
        }

        // Opening the connection BEFORE the SSE response lets us answer a clean 503/502
        try {
            $body = $service->openStream($book);
        } catch (ConnectionException $e) {
            Log::warning('Summary stream unreachable: ' . $e->getMessage(), ['book_id' => $book->id]);

            return $this->unavailable();
        } catch (RuntimeException $e) {
            Log::warning('Summary stream failed: ' . $e->getMessage(), ['book_id' => $book->id]);

            return $this->failure($e);
        }

        return $this->sse(function () use ($service, $body, $book) {
            $full = '';

            try {
                foreach ($service->chunks($body) as $piece) {
                    $full .= $piece;
                    $this->emit('delta', ['text' => $piece]);

                    if (connection_aborted()) {
                        return;   // the client left: nothing is saved
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Summary stream interrupted: ' . $e->getMessage(), ['book_id' => $book->id]);
                $this->emit('error', ['success' => false, 'message' => 'Le flux a été interrompu.']);

                return;
            }

            $full = trim($full);

            if ($full === '') {
                $this->emit('error', ['success' => false, 'message' => 'Le service IA a renvoyé un résumé vide.']);

                return;
            }

            $book->update([
                'summary'              => $full,
                'summary_generated_by' => config('services.openrouter.model'),
                'summary_generated_at' => now(),
            ]);

            $this->emit('done', [
                'success' => true,
                'data'    => (new BookSummaryResource($book->refresh()))->resolve(),
            ]);
        });
    }

    private function unavailable(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Le service IA est injoignable. Réessayez dans quelques instants.',
        ], HttpResponse::HTTP_SERVICE_UNAVAILABLE);
    }

    private function failure(RuntimeException $e): JsonResponse
    {
        // 429 = the free model is rate-limited: temporary, so 503
        $busy = $e->getCode() === 429;

        return response()->json([
            'success' => false,
            'message' => $busy
                ? 'Le service IA est momentanément saturé. Réessayez dans une minute.'
                : "Le résumé n'a pas pu être généré. Réessayez plus tard.",
        ], $busy ? HttpResponse::HTTP_SERVICE_UNAVAILABLE : HttpResponse::HTTP_BAD_GATEWAY);
    }

    private function sse(callable $callback): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function emit(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
