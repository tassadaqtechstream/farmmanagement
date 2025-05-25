<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\Http;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Make sure the product images directory exists
        Storage::disk('public')->makeDirectory('products');

        // Define categories for products
        $categories = [1, 2, 3, 4, 5]; // Assuming you have category IDs

        // Define brands
        $brands = ['Apple', 'Samsung', 'Sony', 'LG', 'Dell', 'HP', 'Asus', 'Acer', 'Lenovo', 'Microsoft'];

        // Define stock statuses
        $stockStatuses = ['in_stock', 'out_of_stock', 'backorder'];

        // Define product types for better placeholder images
        $productTypes = ['electronics', 'furniture', 'fashion', 'home', 'tech'];

        echo "Starting to seed products...\n";

        // Sample product data
        for ($i = 1; $i <= 20; $i++) {
            $name = "Product " . $i;
            $slug = Str::slug($name);
            $price = rand(999, 9999) / 100; // Random price between 9.99 and 99.99
            $stock = rand(0, 100);
            $isActive = rand(0, 1);
            $isFeatured = rand(0, 10) > 8 ? 1 : 0; // 20% chance of being featured
            $now = Carbon::now();
            $productType = $productTypes[array_rand($productTypes)];

            echo "Creating product $i: $name\n";

            // Create the product first
            $product = Product::create([
                'name' => $name,
                'slug' => $slug,
                'short_description' => "Short description for $name",
                'description' => "This is a detailed description for $name. It includes all the features and benefits of the product.",
                'category_id' => $categories[array_rand($categories)],
                'sku' => 'SKU' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'barcode' => mt_rand(1000000000000, 9999999999999),
                'brand' => $brands[array_rand($brands)],
                'model' => 'Model-' . rand(100, 999),
                'price' => $price,
                'cost_price' => $price * 0.7, // 70% of selling price
                'compare_at_price' => $price * 1.2, // 20% higher than selling price
                'b2b_price' => $price * 0.8, // 80% of selling price
                'stock' => $stock,
                'stock_status' => $stockStatuses[array_rand($stockStatuses)],
                'track_inventory' => rand(0, 1),
                'low_stock_threshold' => rand(5, 20),
                'weight' => rand(100, 5000) / 100, // Weight in kg
                'length' => rand(10, 100),
                'width' => rand(10, 100),
                'height' => rand(10, 100),
                'is_b2b_available' => rand(0, 1),
                'b2b_min_quantity' => rand(5, 50),
                'is_bulk_pricing_eligible' => rand(0, 1),
                'featured_image' => null, // Will be updated after image upload
                'meta_title' => $name . " | Your Store",
                'meta_description' => "Buy $name at the best price. Fast delivery and best quality guaranteed.",
                'meta_keywords' => "$name, buy $name, best $name",
                'is_active' => $isActive,
                'is_featured' => $isFeatured,
                'published_at' => $isActive ? $now : null,
                'notes' => "Internal notes for $name",
                'attributes' => json_encode([
                    'color' => ['Red', 'Blue', 'Black'][array_rand(['Red', 'Blue', 'Black'])],
                    'size' => ['Small', 'Medium', 'Large'][array_rand(['Small', 'Medium', 'Large'])],
                    'material' => ['Plastic', 'Metal', 'Wood'][array_rand(['Plastic', 'Metal', 'Wood'])],
                ]),
                'meta_data' => json_encode([
                    'warranty' => rand(1, 5) . ' years',
                    'origin' => ['USA', 'China', 'Japan', 'Germany'][array_rand(['USA', 'China', 'Japan', 'Germany'])],
                ]),
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ]);

            // Now handle the images (3-5 images per product)
            $this->createProductImages($product, $productType);

            echo "Product $i created successfully with images\n";
        }

        echo "Product seeding completed!\n";
    }

    /**
     * Create product images for a product
     *
     * @param Product $product
     * @param string $productType
     * @return void
     */
    private function createProductImages($product, $productType)
    {
        // Number of images to create (3 to 5)
        $numberOfImages = rand(3, 5);

        $sortOrder = 1;
        $featuredImageSet = false;

        for ($i = 1; $i <= $numberOfImages; $i++) {
            // Generate a unique filename
            $filename = 'product_' . $product->id . '_image_' . $i . '_' . time() . '.jpg';
            $filePath = 'products/' . $filename;

            // Download a placeholder image - using placeholders from different services
            // depending on the product type to get more relevant images
            $width = rand(800, 1200);
            $height = rand(800, 1200);

            // Using Picsum for generic placeholders - in a real scenario, you'd use
            // images specific to each product
            $imageUrl = "https://picsum.photos/$width/$height";

            try {
                $response = Http::get($imageUrl);

                if ($response->successful()) {
                    // Store the image
                    Storage::disk('public')->put($filePath, $response->body());

                    // Create the image record
                    $productImage = ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $filePath,
                        'alt_text' => $product->name . ' - Image ' . $i,
                        'sort_order' => $sortOrder++,
                    ]);

                    // Set first image as featured image
                    if (!$featuredImageSet) {
                        $product->update(['featured_image' => $filePath]);
                        $featuredImageSet = true;
                    }

                    echo "  - Added image $i for product {$product->id}\n";
                } else {
                    echo "  - Failed to download image $i for product {$product->id}\n";
                }
            } catch (\Exception $e) {
                echo "  - Error downloading image $i for product {$product->id}: " . $e->getMessage() . "\n";
            }
        }
    }
}
