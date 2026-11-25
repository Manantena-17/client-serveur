<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->unique()->after('subscription_status');
            $table->timestamp('premium_until')->nullable()->after('stripe_customer_id');
        });

        // One row per Stripe payment: history + protection against duplicate webhooks
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stripe_payment_intent_id')->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status');            // succeeded | failed
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['stripe_customer_id', 'premium_until']);
        });
    }
};
