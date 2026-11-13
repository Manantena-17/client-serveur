<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('author');
            $table->string('isbn', 20)->unique();
            $table->unsignedSmallInteger('publication_year');
            $table->unsignedInteger('total_copies')->default(1);
            $table->unsignedInteger('available_copies')->default(1);
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
