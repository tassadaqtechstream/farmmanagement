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
        // Update users table to add seller functionality
        Schema::table('users', function (Blueprint $table) {
             $table->boolean('is_seller_verified')->default(false);
            $table->enum('seller_status', ['inactive', 'active', 'suspended'])->default('inactive');
            $table->decimal('seller_commission_rate', 5, 2)->default(5.00);
            $table->string('store_name')->nullable();
            $table->string('store_slug')->nullable()->unique();
            $table->text('store_description')->nullable();
            $table->decimal('seller_rating', 3, 2)->default(0.00);
            $table->integer('seller_reviews_count')->default(0);
        });

        // Update products table to add seller information
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->constrained('users'); // seller is a user
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved');
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('wishlist_count')->default(0);
            $table->integer('purchase_count')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0.00);
            $table->integer('total_reviews')->default(0);
        });

        // Update orders table to include seller information
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->constrained('users');
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('seller_amount', 12, 2)->default(0);
        });

        // Update order_items table to include seller information per item
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->constrained('users');
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('seller_amount', 12, 2)->default(0);
            $table->enum('fulfillment_status', ['pending', 'processing', 'shipped', 'delivered'])->default('pending');
        });

        // Create shopping cart table
        Schema::create('shopping_carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('session_id')->nullable(); // for guest users
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });

        // Create wishlist table
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        // Create seller ratings table
        Schema::create('seller_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('order_id')->constrained();
            $table->integer('rating'); // 1-5 stars
            $table->text('review')->nullable();
            $table->text('seller_response')->nullable();
            $table->timestamps();

            $table->unique(['seller_id', 'buyer_id', 'order_id']);
        });

        // Create product reviews table
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('order_id')->constrained();
            $table->integer('rating'); // 1-5 stars
            $table->text('review');
            $table->text('seller_response')->nullable();
            $table->boolean('is_verified_purchase')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'user_id', 'order_id']);
        });

        // Create seller payouts table
        Schema::create('seller_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users');
            $table->decimal('amount', 12, 2);
            $table->decimal('commission_deducted', 12, 2);
            $table->decimal('net_amount', 12, 2);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->date('payout_date');
            $table->string('payout_method')->default('bank_transfer');
            $table->timestamps();
        });

        // Create notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // order_update, new_review, payout, etc.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        // Create simple coupons table
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('users'); // null for platform-wide
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('type', ['percentage', 'fixed_amount']);
            $table->decimal('value', 8, 2);
            $table->decimal('minimum_amount', 8, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('used_count')->default(0);
            $table->datetime('expires_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('seller_payouts');
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('seller_ratings');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('shopping_carts');

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['seller_id', 'commission_amount', 'seller_amount', 'fulfillment_status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn(['seller_id', 'commission_amount', 'seller_amount']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->dropColumn([
                'seller_id', 'approval_status', 'commission_rate',
                'view_count', 'wishlist_count', 'purchase_count',
                'average_rating', 'total_reviews'
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_type', 'is_seller_verified', 'seller_status',
                'seller_commission_rate', 'store_name', 'store_slug',
                'store_description', 'seller_rating', 'seller_reviews_count'
            ]);
        });
    }
};
