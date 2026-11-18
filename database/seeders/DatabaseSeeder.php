<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Category;
use App\Models\Member;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::factory()->count(5)->create();

        // Reuse the 5 categories instead of creating one per book
        Book::factory()->count(30)->make()->each(function ($book) use ($categories) {
            $book->category_id = $categories->random()->id;
            $book->save();
        });

        Member::factory()->student()->count(15)->create();
        Member::factory()->teacher()->count(5)->create();
    }
}
