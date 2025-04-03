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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('set null');
            $table->string('image')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_b2b_visible')->default(true); // Specific to B2B
            $table->json('meta_data')->nullable();
            $table->timestamps();
        });

        // Create products table
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();

            // Pricing
            $table->decimal('price', 12, 2); // Retail price
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable(); // For sale pricing
            $table->decimal('b2b_price', 12, 2)->nullable(); // Default B2B price

            // Inventory
            $table->integer('stock')->default(0);
            $table->string('stock_status')->default('in_stock'); // in_stock, out_of_stock, backorder
            $table->boolean('track_inventory')->default(true);
            $table->integer('low_stock_threshold')->default(5);

            // Shipping
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();

            // B2B specific
            $table->boolean('is_b2b_available')->default(false);
            $table->integer('b2b_min_quantity')->default(1);
            $table->boolean('is_bulk_pricing_eligible')->default(false);

            // Media & SEO
            $table->string('featured_image')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();

            // Status & visibility
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            // Miscellaneous
            $table->text('notes')->nullable();
            $table->json('attributes')->nullable(); // For flexible attributes
            $table->json('meta_data')->nullable(); // For additional data
            $table->timestamps();
            $table->softDeletes(); // For trash functionality
        });

        // Create product images table
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('image_path');
            $table->string('alt_text')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Create product categories pivot table for multiple categories
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'category_id']);
        });

        // Create product attributes table
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        // Create product attribute values table
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
            $table->string('value');
            $table->string('slug')->unique();
            $table->timestamps();

            $table->unique(['attribute_id', 'value']);
        });

        // Create product attributes pivot table
        Schema::create('product_attribute_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
            $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['product_id', 'attribute_id', 'attribute_value_id'], 'product_attribute_value_unique');
        });

        // Create product variants table
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();
            $table->decimal('price', 12, 2);
            $table->decimal('b2b_price', 12, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->string('stock_status')->default('in_stock');
            $table->decimal('weight', 8, 2)->nullable();
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Create product variant attribute values pivot table
        Schema::create('product_variant_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variant_id')->constrained('product_variants')->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained('product_attributes')->onDelete('cascade');
            $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['variant_id', 'attribute_id'], 'variant_attribute_unique');
        });

        // Create product related products table
        Schema::create('product_related', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_id')->constrained('products')->onDelete('cascade');
            $table->string('relation_type')->default('related'); // related, upsell, cross-sell
            $table->timestamps();

            $table->unique(['product_id', 'related_id', 'relation_type']);
        });

        // Create volume pricing for B2B
        Schema::create('product_volume_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('min_quantity');
            $table->integer('max_quantity')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->timestamps();

            // Make sure there's no overlap in quantity ranges
            $table->unique(['product_id', 'variant_id', 'min_quantity'], 'product_volume_pricing_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_volume_pricing');
        Schema::dropIfExists('product_related');
        Schema::dropIfExists('product_variant_attribute_values');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attribute_product');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
