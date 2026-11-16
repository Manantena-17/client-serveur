<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class LoanController extends Controller
{
    public function __construct(private readonly LoanService $loanService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $loans = Loan::query()
            ->with(['member', 'book'])
            ->when($request->query('member_id'), fn ($q, $id) => $q->where('member_id', $id))
            ->when($request->query('book_id'), fn ($q, $id) => $q->where('book_id', $id))
            ->when($request->query('status') === 'active', fn ($q) => $q->whereNull('returned_at'))
            ->when($request->query('status') === 'returned', fn ($q) => $q->whereNotNull('returned_at'))
            ->when($request->query('status') === 'overdue', fn ($q) => $q
                ->whereNull('returned_at')
                ->whereDate('due_at', '<', now()))
            ->orderByDesc('id')
            ->paginate(15);

        return LoanResource::collection($loans);
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $data = $request->validated();

        $loan = $this->loanService->borrow(
            $data['member_id'],
            $data['book_id'],
            $data['due_at'] ?? null,
        );

        return (new LoanResource($loan))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Loan $loan): LoanResource
    {
        return new LoanResource($loan->load(['member', 'book.category']));
    }

    // PATCH /api/loans/{loan}/return
    public function returnBook(Loan $loan): LoanResource
    {
        return new LoanResource($this->loanService->return($loan));
    }
}
