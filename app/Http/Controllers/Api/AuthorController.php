<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAuthorBookRequest;
use App\Http\Requests\StoreAuthorRequest;
use App\Http\Requests\UpdateAuthorRequest;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\BookResource;
use App\Models\Author;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AuthorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $authors = Author::query()
            ->withCount('books')
            ->when($request->query('nationality'), fn ($q, $n) => $q->where('nationality', $n))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('last_name')
            ->paginate(15);

        return AuthorResource::collection($authors);
    }
    public function books(Request $request, Author $author): AnonymousResourceCollection
    {
        $books = $author->books()
            ->with(['category', 'author'])
            ->when($request->boolean('available'), fn ($q) => $q->where('available_copies', '>', 0))
            ->orderByDesc('id')
            ->paginate(15);

        return BookResource::collection($books);
    }

    public function store(StoreAuthorRequest $request): JsonResponse
    {
        $author = Author::query()->create($request->validated());

        return (new AuthorResource($author))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Author $author): AuthorResource
    {
        return new AuthorResource($author->loadCount('books'));
    }

    public function update(UpdateAuthorRequest $request, Author $author): AuthorResource
    {
        $author->update($request->validated());

        return new AuthorResource($author->refresh()->loadCount('books'));
    }

    public function destroy(Author $author): Response|JsonResponse
    {
        if ($author->books()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this author: they still have books.',
            ], HttpResponse::HTTP_CONFLICT);
        }

        $author->delete();

        return response()->noContent();
    }
    public function storeBook(StoreAuthorBookRequest $request, Author $author): JsonResponse
    {
        $data = $request->validated();
        $data['available_copies'] = $data['total_copies'];

        $book = $author->books()->create($data);

        return (new BookResource($book->load(['category', 'author'])))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }
}
