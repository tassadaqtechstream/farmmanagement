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
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('company');
            $table->string('vatin')->nullable();
            $table->string('phone_number');
            $table->string('fiscal_address');
            $table->string('zip_code');
            $table->string('country');
            $table->unsignedBigInteger('company_activity_id');
            $table->string('preferred_language')->default('en');
            $table->json('preferred_product_ids')->nullable();
            $table->string('other_preferred_products')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
