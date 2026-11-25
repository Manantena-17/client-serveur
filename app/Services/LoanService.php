<?php

namespace App\Services;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public const DEFAULT_LOAN_DAYS = 14;

    public function borrow(
        int $memberId,
        int $bookId,
        ?string $dueAt = null,
        ?string $paymentStatus = null,
    ): Loan {
        return DB::transaction(function () use ($memberId, $bookId, $dueAt, $paymentStatus) {
            $book   = Book::query()->lockForUpdate()->findOrFail($bookId);
            $member = Member::query()->lockForUpdate()->findOrFail($memberId);

            // Rule 1: at least one copy available
            if (! $book->isAvailable()) {
                throw ValidationException::withMessages([
                    'book_id' => 'No copy of this book is currently available.',
                ]);
            }


            // Rule: free = 2 active loans, premium = unlimited
            if (! $member->canBorrow()) {
                throw ValidationException::withMessages([
                    'member_id' => "Limite de {$member->maxLoans()} emprunts actifs atteinte (abonnement gratuit). "
                        . 'Passez premium pour emprunter sans limite.',
                ]);
            }

            // Same member cannot hold the same book twice
            if ($member->activeLoans()->where('book_id', $book->id)->exists()) {
                throw ValidationException::withMessages([
                    'book_id' => 'This member already has this book on loan.',
                ]);
            }

            $loan = Loan::query()->create([
                'member_id'      => $member->id,
                'book_id'        => $book->id,
                'borrowed_at'    => now()->toDateString(),
                'due_at'         => $dueAt ?? now()->addDays(self::DEFAULT_LOAN_DAYS)->toDateString(),
                // default: premium members borrow for free, others must pay
                'payment_status' => $paymentStatus
                    ?? ($member->isPremium() ? 'free' : 'pending'),
            ]);

            $book->decrement('available_copies');

            return $loan->load(['member', 'book.category', 'book.author']);
        });
    }

    public function return(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan) {
            $loan = Loan::query()->lockForUpdate()->findOrFail($loan->id);

            if ($loan->returned_at !== null) {
                throw ValidationException::withMessages([
                    'loan' => 'This loan has already been returned.',
                ]);
            }

            $loan->update(['returned_at' => now()->toDateString()]);
            $loan->book()->increment('available_copies');

            return $loan->load(['member', 'book.category', 'book.author']);
        });
    }
}
