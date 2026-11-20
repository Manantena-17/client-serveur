<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Loan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_creates_fifteen_records_for_each_library_entity(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('categories', 15);
        $this->assertDatabaseCount('books', 15);
        $this->assertDatabaseCount('members', 15);
        $this->assertDatabaseCount('loans', 15);

        $this->assertSame(15, Loan::query()->whereNull('returned_at')->distinct()->count('member_id'));
        $this->assertSame(15, Loan::query()->whereNull('returned_at')->distinct()->count('book_id'));
        $this->assertSame(15, Book::query()->whereColumn('available_copies', '<', 'total_copies')->count());
    }
}
