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
            $table->string('summary_generated_by')->nullable()->after('summary');
            $table->timestamp('summary_generated_at')->nullable()->after('summary_generated_by');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['summary_generated_by', 'summary_generated_at']);
        });
    }
};
