<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')
                ->constrained('members')
                ->restrictOnDelete();   // blocks deleting a member with loans
            $table->foreignId('book_id')
                ->constrained('books')
                ->restrictOnDelete();   // blocks deleting a book with loans
            $table->date('borrowed_at');
            $table->date('due_at');
            $table->date('returned_at')->nullable();   // null = still borrowed
            $table->timestamps();

            $table->index(['member_id', 'returned_at']);
            $table->index(['book_id', 'returned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
