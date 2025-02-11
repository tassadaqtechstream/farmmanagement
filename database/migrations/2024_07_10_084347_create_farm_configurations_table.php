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
        Schema::create('farm_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('farm_id');
            $table->decimal('investment_percentage', 5, 2);
            $table->integer('investment_period');
            $table->decimal('min_investment_amount', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('farm_id')->references('id')->on('user_farms')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('farm_configurations');
    }
};
