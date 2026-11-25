<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Loan;
use App\Models\Member;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $recordCount = 15;

        // Known records first: they get id 1, handy for Postman tests
        $informatique = Category::create([
            'name'        => 'Informatique',
            'description' => 'Programming books',
        ]);

        $jean = Author::create([
            'first_name'  => 'Jean',
            'last_name'   => 'Rakoto',
            'nationality' => 'Malagasy',
        ]);

        $categories = Category::factory()->count($recordCount - 1)->create()->push($informatique);
        $authors    = Author::factory()->count($recordCount - 1)->create()->push($jean);

        $books = Book::factory()
            ->count($recordCount)
            ->state(fn () => [
                'category_id' => $categories->random()->id,
                'author_id'   => $authors->random()->id,
            ])
            ->create();

        $members = Member::factory()->count($recordCount)->create();

        $members->zip($books)->each(function ($pair): void {
            [$member, $book] = $pair;
            $borrowedAt = now();

            Loan::factory()->create([
                'member_id'   => $member->id,
                'book_id'     => $book->id,
                'borrowed_at' => $borrowedAt->toDateString(),
                'due_at'      => $borrowedAt
                    ->copy()
                    ->addDays($member->type === 'teacher' ? 30 : 14)
                    ->toDateString(),
            ]);

            $book->decrement('available_copies');
        });
    }
}
