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
        Schema::table('orders', function (Blueprint $table) {
            // Make existing columns nullable
            $table->foreignId('business_id')->nullable()->change();
            $table->foreignId('user_id')->nullable()->change();
            $table->string('purchase_order_number')->nullable()->change();
            $table->string('payment_terms')->nullable()->change();

            // Add new columns for guest users
            $table->boolean('is_guest_order')->default(false)->after('user_id');
            $table->string('guest_email')->nullable()->after('is_guest_order');
            $table->string('guest_name')->nullable()->after('guest_email');
            $table->string('guest_phone')->nullable()->after('guest_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Remove the guest columns first
            $table->dropColumn([
                'is_guest_order',
                'guest_email',
                'guest_name',
                'guest_phone'
            ]);

            // Revert nullable changes
            $table->foreignId('business_id')->nullable(false)->change();
            $table->foreignId('user_id')->nullable(false)->change();
            $table->string('purchase_order_number')->nullable(false)->change();
            $table->string('payment_terms')->nullable(false)->change();
        });
    }
};
