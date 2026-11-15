<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $books = Book::query()
            ->with('category')
            ->when($request->query('category_id'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->query('search'), function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%")
                        ->orWhere('isbn', 'like', "%{$search}%");
                });
            })
            ->when($request->boolean('available'), fn ($q) => $q->where('available_copies', '>', 0))
            ->orderByDesc('id')
            ->paginate(15);

        return BookResource::collection($books);
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['available_copies'] = $data['total_copies'];

        $book = Book::query()->create($data);

        return (new BookResource($book->load('category')))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Book $book): BookResource
    {
        return new BookResource($book->load('category'));
    }

    public function update(UpdateBookRequest $request, Book $book): BookResource|JsonResponse
    {
        $data = $request->validated();

        // If total_copies changes, keep available_copies consistent
        if (isset($data['total_copies'])) {
            $borrowed = $book->total_copies - $book->available_copies;

            if ($data['total_copies'] < $borrowed) {
                return response()->json([
                    'message' => "Cannot set total copies below the number currently borrowed ({$borrowed}).",
                ], HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $data['available_copies'] = $data['total_copies'] - $borrowed;
        }

        $book->update($data);

        return new BookResource($book->refresh()->load('category'));
    }

    public function destroy(Book $book): Response|JsonResponse
    {
        // Business rule: cannot delete a book with active loans
        if ($book->activeLoans()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this book: it has active loans.',
            ], HttpResponse::HTTP_CONFLICT);
        }

        // Loans history also blocks deletion (restrictOnDelete on the foreign key)
        if ($book->loans()->exists()) {
            return response()->json([
                'message' => 'Cannot delete this book: it has loan history.',
            ], HttpResponse::HTTP_CONFLICT);
        }

        $book->delete();

        return response()->noContent();
    }
}
