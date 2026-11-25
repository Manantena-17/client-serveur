<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('author');
            $table->foreignId('author_id')
                ->after('title')
                ->constrained('authors')
                ->restrictOnDelete();   // an author with books cannot be deleted
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
            $table->string('author')->default('')->after('title');
        });
    }
};
