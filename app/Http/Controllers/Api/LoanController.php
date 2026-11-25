<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoanRequest;
use App\Http\Requests\UpdateLoanPaymentRequest;
use App\Http\Requests\UpdateLoanRequest;
use App\Http\Resources\LoanResource;
use App\Models\Loan;
use App\Services\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;
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
            ->when($request->query('payment_status'), fn ($q, $s) => $q->where('payment_status', $s))
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
            $data['payment_status'] ?? null,
        );

        return (new LoanResource($loan))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_CREATED);
    }

    public function show(Loan $loan): LoanResource
    {
        return new LoanResource($loan->load(['member', 'book.category', 'book.author']));
    }

    // PATCH /api/loans/{loan}/payment
    public function updatePayment(UpdateLoanPaymentRequest $request, Loan $loan): LoanResource
    {
        if ($loan->payment_status === 'free') {
            throw ValidationException::withMessages([
                'payment_status' => 'This loan is free, its payment status cannot be changed.',
            ]);
        }

        $loan->update($request->validated());

        return new LoanResource($loan->refresh()->load(['member', 'book.category', 'book.author']));
    }

    // PATCH /api/loans/{loan}/return
    public function returnBook(Loan $loan): LoanResource
    {
        return new LoanResource($this->loanService->return($loan));
    }

    // PATCH /api/loans/{loan}  (extend the due date)
    public function update(UpdateLoanRequest $request, Loan $loan): LoanResource
    {
        if ($loan->returned_at !== null) {
            throw ValidationException::withMessages([
                'loan' => 'Cannot extend a loan that has already been returned.',
            ]);
        }

        $newDueAt = $request->validated('due_at');

        if ($loan->due_at->gte($newDueAt)) {
            throw ValidationException::withMessages([
                'due_at' => 'The new due date must be after the current due date.',
            ]);
        }

        $loan->update(['due_at' => $newDueAt]);

        return new LoanResource($loan->refresh()->load(['member', 'book.category', 'book.author']));
    }
}
