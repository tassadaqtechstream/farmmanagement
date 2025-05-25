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
        Schema::table('order_status_histories', function (Blueprint $table) {
            // Make user_id nullable
            $table->foreignId('user_id')->nullable()->change();

            // Add column to track who created the status change
            $table->string('created_by_type')->default('user')->after('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_status_histories', function (Blueprint $table) {
            // Remove the new column
            $table->dropColumn('created_by_type');

            // Revert nullable change
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
