<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Rule 1: at least one copy available ----------

    public function test_member_can_borrow_an_available_book(): void
    {
        $member = Member::factory()->student()->create();
        $book = Book::factory()->create(['total_copies' => 2, 'available_copies' => 2]);

        $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'book_id' => $book->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'borrowed');

        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_cannot_borrow_a_book_with_no_copy_available(): void
    {
        $member = Member::factory()->student()->create();
        $book = Book::factory()->unavailable()->create();

        $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'book_id' => $book->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book_id']);

        $this->assertDatabaseCount('loans', 0);
    }

    // ---------- Rule 2: 3 students / 6 teachers ----------

    public function test_student_cannot_borrow_more_than_3_books(): void
    {
        $member = Member::factory()->student()->create();

        Loan::factory()->count(3)->create(['member_id' => $member->id]);

        $fourth = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);

        $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'book_id' => $fourth->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member_id']);
    }

    public function test_teacher_can_borrow_6_books_but_not_7(): void
    {
        $member = Member::factory()->teacher()->create();

        Loan::factory()->count(5)->create(['member_id' => $member->id]);

        $sixth = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);
        $seventh = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);

        $this->postJson('/api/loans', ['member_id' => $member->id, 'book_id' => $sixth->id])
            ->assertCreated();

        $this->postJson('/api/loans', ['member_id' => $member->id, 'book_id' => $seventh->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['member_id']);
    }

    public function test_member_cannot_borrow_the_same_book_twice(): void
    {
        $member = Member::factory()->teacher()->create();
        $book = Book::factory()->create(['total_copies' => 5, 'available_copies' => 5]);

        $this->postJson('/api/loans', ['member_id' => $member->id, 'book_id' => $book->id])
            ->assertCreated();

        $this->postJson('/api/loans', ['member_id' => $member->id, 'book_id' => $book->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['book_id']);
    }

    // ---------- Rule 3: automatic overdue calculation ----------

    public function test_overdue_loan_is_detected_automatically(): void
    {
        $loan = Loan::factory()->create([
            'borrowed_at' => now()->subDays(20)->toDateString(),
            'due_at' => now()->subDays(5)->toDateString(),
        ]);

        $this->getJson("/api/loans/{$loan->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', true)
            ->assertJsonPath('data.days_late', 5);
    }

    public function test_loan_not_yet_due_is_not_overdue(): void
    {
        $loan = Loan::factory()->create([
            'borrowed_at' => now()->toDateString(),
            'due_at' => now()->addDays(10)->toDateString(),
        ]);

        $this->getJson("/api/loans/{$loan->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', false)
            ->assertJsonPath('data.days_late', 0);
    }

    public function test_overdue_filter_returns_only_late_active_loans(): void
    {
        Loan::factory()->create(['due_at' => now()->subDays(3)->toDateString()]);
        Loan::factory()->create(['due_at' => now()->addDays(3)->toDateString()]);
        Loan::factory()->create([
            'due_at' => now()->subDays(3)->toDateString(),
            'returned_at' => now()->toDateString(),
        ]);

        $this->getJson('/api/loans?status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ---------- Rule 5: returning a book gives the copy back ----------

    public function test_returning_a_book_restores_the_copy(): void
    {
        $member = Member::factory()->student()->create();
        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);

        $loanId = $this->postJson('/api/loans', [
            'member_id' => $member->id,
            'book_id' => $book->id,
        ])->json('data.id');

        $this->assertSame(0, $book->fresh()->available_copies);

        $this->patchJson("/api/loans/{$loanId}/return")
            ->assertOk()
            ->assertJsonPath('data.status', 'returned');

        $this->assertSame(1, $book->fresh()->available_copies);
    }

    public function test_cannot_return_the_same_loan_twice(): void
    {
        $loan = Loan::factory()->returned()->create();

        $this->patchJson("/api/loans/{$loan->id}/return")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['loan']);
    }

    public function test_after_return_member_can_borrow_again(): void
    {
        $member = Member::factory()->student()->create();

        $loans = Loan::factory()->count(3)->create(['member_id' => $member->id]);

        $this->patchJson("/api/loans/{$loans->first()->id}/return")->assertOk();

        $book = Book::factory()->create(['total_copies' => 1, 'available_copies' => 1]);

        $this->postJson('/api/loans', ['member_id' => $member->id, 'book_id' => $book->id])
            ->assertCreated();
    }

    // ---------- Rule 4: no deletion with active loans ----------

    public function test_cannot_delete_a_book_with_active_loans(): void
    {
        $loan = Loan::factory()->create();

        $this->deleteJson("/api/books/{$loan->book_id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('books', ['id' => $loan->book_id]);
    }

    public function test_cannot_delete_a_member_with_active_loans(): void
    {
        $loan = Loan::factory()->create();

        $this->deleteJson("/api/members/{$loan->member_id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('members', ['id' => $loan->member_id]);
    }

    public function test_can_delete_a_book_without_loans(): void
    {
        $book = Book::factory()->create();

        $this->deleteJson("/api/books/{$book->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_can_delete_a_member_without_loans(): void
    {
        $member = Member::factory()->create();

        $this->deleteJson("/api/members/{$member->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('members', ['id' => $member->id]);
    }
}
