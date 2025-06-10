<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pre_harvest_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_farm_id')->nullable()->constrained('user_farms')->onDelete('set null');
            $table->foreignId('cron_type_id')->nullable()->constrained('cron_types')->onDelete('set null');
            $table->foreignId('seed_variety_id')->nullable()->constrained('seed_varities')->onDelete('set null');
            $table->string('title');
            $table->string('location');
            $table->decimal('estimated_yield', 10, 2);
            $table->decimal('price_per_kg', 8, 2);
            $table->date('harvest_date');
            $table->enum('quality_grade', ['premium', 'grade-a', 'grade-b', 'standard']);
            $table->integer('minimum_order');
            $table->boolean('organic_certified')->default(false);
            $table->text('description');
            $table->text('terms_conditions')->nullable();
            $table->enum('status', ['available', 'reserved', 'harvested', 'cancelled'])->default('available');
            $table->decimal('reserved_quantity', 10, 2)->default(0);
            $table->json('images')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->timestamps();

            $table->index(['cron_type_id', 'status']);
            $table->index(['harvest_date', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_harvest_listings');
    }
};
