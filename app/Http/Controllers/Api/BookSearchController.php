<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmartSearchRequest;
use App\Services\BookSearchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BookSearchController extends Controller
{
    public function __invoke(SmartSearchRequest $request, BookSearchService $service): JsonResponse
    {
        $description = $request->validated('description');

        try {
            $result = $service->search($description);
        } catch (RuntimeException|ConnectionException $e) {
            Log::warning('Smart search failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => "La recherche n'a pas pu être effectuée. Réessayez plus tard.",
            ], HttpResponse::HTTP_BAD_GATEWAY);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'query'    => $description,
                'keywords' => $result['keywords'],
                'count'    => count($result['results']),
                'results'  => $result['results'],
            ],
        ]);
    }
}
