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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('company_name');
            $table->string('tax_id')->unique();
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('zip');
            $table->string('phone');
            $table->string('email')->unique();
            $table->string('contact_name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->string('payment_terms')->default('net_30');
            $table->string('discount_tier')->nullable();
            $table->foreignId('account_manager_id')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Update users table to add business ID
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->constrained('businesses');
            $table->string('phone')->nullable();
        });



        // Business-specific product pricing
        Schema::create('business_product_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->decimal('price', 12, 2);
            $table->integer('min_quantity')->default(1);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_until')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'product_id']);
        });

        // Orders table
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->decimal('total', 12, 2);
            $table->string('status');
            $table->string('purchase_order_number');
            $table->text('shipping_address');
            $table->text('billing_address');
            $table->string('payment_method');
            $table->string('shipping_method');
            $table->string('tracking_number')->nullable();
            $table->string('payment_terms');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Order items table
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        // Order status history
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->string('status');
            $table->text('comment')->nullable();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();
        });

        // Order documents
        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('type', ['invoice', 'packing_slip', 'shipping_label', 'other']);
            $table->string('file_path');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Quotes table
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->decimal('total', 12, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'converted'])->default('pending');
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Quote items table
        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('business_product_pricing');



        // Remove business fields from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropColumn(['business_id', 'role', 'job_title', 'department', 'phone']);
        });

        Schema::dropIfExists('businesses');
    }
};
