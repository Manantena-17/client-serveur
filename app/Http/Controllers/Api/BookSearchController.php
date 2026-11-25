<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SmartSearchRequest;
use App\Services\BookSearchService;
use Illuminate\Http\JsonResponse;

class BookSearchController extends Controller
{
    public function __invoke(SmartSearchRequest $request, BookSearchService $service): JsonResponse
    {
        $description = $request->validated('description');
        $limit       = (int) ($request->validated('limit') ?? 10);

        $result = $service->search($description, $limit);

        return response()->json([
            'success' => true,
            'data'    => [
                'query'    => $description,
                'source'   => $result['source'],
                'keywords' => $result['keywords'],
                'count'    => count($result['results']),
                'results'  => $result['results'],
            ],
        ]);
    }
}
