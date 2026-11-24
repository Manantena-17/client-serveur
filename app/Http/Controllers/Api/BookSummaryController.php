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
        // A summary already exists: return it without calling OpenAI
        if ($book->summary && ! $request->boolean('force')) {
            return response()->json([
                'success' => true,
                'data'    => (new BookSummaryResource($book))->resolve(),
                'message' => 'Résumé existant retourné (pas de nouvel appel API)',
            ]);
        }

        try {
            $summary = $service->generate($book);
        } catch (RuntimeException|ConnectionException $e) {
            Log::warning('Summary generation failed: ' . $e->getMessage(), ['book_id' => $book->id]);

            return response()->json([
                'success' => false,
                'message' => "Le résumé n'a pas pu être généré. Réessayez plus tard.",
            ], HttpResponse::HTTP_BAD_GATEWAY);
        }

        $book->update([
            'summary'              => $summary,
            'summary_generated_by' => config('services.openai.model'),
            'summary_generated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'data'    => (new BookSummaryResource($book->refresh()))->resolve(),
        ]);
    }
}
