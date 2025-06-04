<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class SellerProductController extends Controller
{
    /**
     * Constructor with middleware
     */
    public function __construct()
    {

    }

    /**
     * Get categories for product creation (reuse your existing method)
     */
    public function getCategories(): JsonResponse
    {
        try {
            // Use your existing getAllCategories structure
            $categories = Category::whereNull('parent_id')
                ->orderBy('name')
                ->get();

            $formattedCategories = $categories->map(function($category) {
                $subcategories = Category::where('parent_id', $category->id)
                    ->orderBy('name')
                    ->get();

                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'image_url' => $category->image,
                    'products' => $subcategories->map(function($subcategory) {
                        return [
                            'id' => $subcategory->id,
                            'name' => $subcategory->name,
                            'slug' => $subcategory->slug,
                            'commodity_id' => $subcategory->parent_id
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedCategories
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories'
            ], 500);
        }
    }

    /**
     * Store a newly created seller product with images in custom folder
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Custom validation for seller products
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'required|string|max:5000',
                'short_description' => 'nullable|string|max:300',
                'category_id' => 'required|exists:categories,id',
                'price' => 'required|numeric|min:0.01|max:999999.99',
                'stock' => 'required|integer|min:0',
                'unit' => 'required|string|in:kg,ton,piece,liter,gram',
                'currency' => 'required|string|in:USD,SAR,AED,PKR',
                'weight' => 'nullable|numeric|min:0',
                'brand' => 'nullable|string|max:100',
                'model' => 'nullable|string|max:100',

                // Image upload validation
                'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
                'images' => 'nullable|array|max:10', // Maximum 10 images
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB per image
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $user = Auth::user();

            Log::info('user data ',['data' => $user]);

            // Generate SKU using your existing method
            $sku = $this->generateSKU($request->name);

            // Handle featured image upload to custom folder
            $featuredImagePath = null;
            if ($request->hasFile('featured_image')) {
                $featuredImagePath = $this->uploadToCustomFolder(
                    $request->file('featured_image'),
                    'seller_products/featured',
                    $user->id
                );
            }

            // Create product using your existing structure but with seller modifications
            $product = Product::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
                'short_description' => $request->short_description ?: Str::limit($request->description, 150),
                'category_id' => $request->category_id,
                'seller_id' => $user->id,
                'sku' => $sku,
                'price' => $request->price,
                'stock' => $request->stock,
                'stock_status' => $request->stock > 0 ? 'in_stock' : 'out_of_stock',
                'weight' => $request->weight,
                'brand' => $request->brand,
                'model' => $request->model,
                'featured_image' => $featuredImagePath,

                // Seller-specific settings
                'approval_status' => 'pending', // Always pending for sellers
                'commission_rate' => $user->seller_commission_rate ?? 5.00,
                'is_active' => true,
                'track_inventory' => true,
                'low_stock_threshold' => 5,
                'is_b2b_available' => true, // Enable for B2B marketplace
                'b2b_min_quantity' => 1,
                'published_at' => null, // Will be set when approved

                // Metadata
                'meta_data' => [
                    'unit' => $request->unit,
                    'currency' => $request->currency,
                    'created_via' => 'seller_api',
                    'seller_created' => true
                ]
            ]);

            // Handle additional product images in custom folder
            if ($request->hasFile('images')) {
                $this->handleAdditionalImages($product, $request->file('images'), $user->id);
            }

            DB::commit();

            // Load relationships
            $product->load(['category', 'images']);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully and submitted for approval',
                'data' => [
                    'product' => new ProductResource($product),
                    'approval_status' => $product->approval_status
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating seller product: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => config('app.debug') ? $e->getMessage() : 'Something went wrong'
            ], 500);
        }
    }

    /**
     * Get seller's products with filters
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $query = Product::where('seller_id', $user->id)
                ->with(['category', 'images'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status') && $request->status !== '') {
                $query->where('approval_status', $request->status);
            }

            if ($request->has('category_id') && $request->category_id !== '') {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('search') && $request->search !== '') {
                $query->where(function($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('sku', 'like', '%' . $request->search . '%')
                        ->orWhere('description', 'like', '%' . $request->search . '%');
                });
            }

            $products = $query->paginate($request->get('per_page', 15));

            // Transform products to include proper image URLs from custom folder
            $transformedProducts = collect($products->items())->map(function($product) {
                $productData = $product->toArray();

                // Add image URLs from custom folder
                $productData['images'] = collect($product->images)->map(function($image) {
                    return [
                        'id' => $image->id,
                        'image_path' => $image->image_path,
                        'alt_text' => $image->alt_text,
                        'sort_order' => $image->sort_order,
                        'image_url' => $this->getCustomImageUrl($image->image_path)
                    ];
                })->toArray();

                // Add featured image URL from custom folder
                $productData['featured_image_url'] = $product->featured_image ?
                    $this->getCustomImageUrl($product->featured_image) : null;

                return $productData;
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'data' => $transformedProducts,
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching seller products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch products'
            ], 500);
        }
    }

    /**
     * Get specific seller product
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();

            $product = Product::where('id', $id)
                ->where('seller_id', $user->id)
                ->with(['category', 'images'])
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Transform images to include custom URLs
            $product->images->transform(function($image) {
                $image->image_url = $this->getCustomImageUrl($image->image_path);
                return $image;
            });

            if ($product->featured_image) {
                $product->featured_image_url = $this->getCustomImageUrl($product->featured_image);
            }

            return response()->json([
                'success' => true,
                'data' => new ProductResource($product)
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product'
            ], 500);
        }
    }

    /**
     * Update seller product
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();

            $product = Product::where('id', $id)
                ->where('seller_id', $user->id)
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Validation
            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string|max:5000',
                'short_description' => 'sometimes|nullable|string|max:300',
                'category_id' => 'sometimes|required|exists:categories,id',
                'price' => 'sometimes|required|numeric|min:0.01|max:999999.99',
                'stock' => 'sometimes|required|integer|min:0',
                'unit' => 'sometimes|required|string|in:kg,ton,piece,liter,gram',
                'currency' => 'sometimes|required|string|in:USD,SAR,AED,PKR',
                'weight' => 'sometimes|nullable|numeric|min:0',
                'brand' => 'sometimes|nullable|string|max:100',
                'model' => 'sometimes|nullable|string|max:100',

                // Image uploads
                'featured_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'images' => 'sometimes|nullable|array|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120',
                'delete_images' => 'sometimes|array',
                'delete_images.*' => 'exists:product_images,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Handle featured image update
            $featuredImagePath = $product->featured_image;
            if ($request->hasFile('featured_image')) {
                // Delete old featured image
                if ($featuredImagePath) {
                    $this->deleteCustomImage($featuredImagePath);
                }
                $featuredImagePath = $this->uploadToCustomFolder(
                    $request->file('featured_image'),
                    'seller_products/featured',
                    $user->id
                );
            }

            // If product was approved, set it back to pending for re-approval
            $approvalStatus = $product->approval_status;
            if ($product->approval_status === 'approved') {
                $approvalStatus = 'pending';
            }

            // Update product data
            $updateData = array_filter([
                'name' => $request->name,
                'slug' => $request->name ? Str::slug($request->name) : null,
                'description' => $request->description,
                'short_description' => $request->short_description ?: ($request->description ? Str::limit($request->description, 150) : null),
                'category_id' => $request->category_id,
                'price' => $request->price,
                'stock' => $request->stock,
                'stock_status' => $request->stock !== null ? ($request->stock > 0 ? 'in_stock' : 'out_of_stock') : null,
                'weight' => $request->weight,
                'brand' => $request->brand,
                'model' => $request->model,
                'featured_image' => $featuredImagePath,
                'approval_status' => $approvalStatus,
            ], function($value) {
                return $value !== null;
            });

            // Update meta_data
            if ($request->has('unit') || $request->has('currency')) {
                $metaData = $product->meta_data ?? [];
                if ($request->has('unit')) $metaData['unit'] = $request->unit;
                if ($request->has('currency')) $metaData['currency'] = $request->currency;
                $metaData['updated_via'] = 'seller_api';
                $updateData['meta_data'] = $metaData;
            }

            $product->update($updateData);

            // Handle image deletions
            if ($request->has('delete_images') && is_array($request->delete_images)) {
                $this->deleteProductImages($product, $request->delete_images);
            }

            // Handle new additional images
            if ($request->hasFile('images')) {
                $this->handleAdditionalImages($product, $request->file('images'), $user->id);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => new ProductResource($product->load(['category', 'images']))
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating seller product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product'
            ], 500);
        }
    }

    /**
     * Delete seller product
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();

            $product = Product::where('id', $id)
                ->where('seller_id', $user->id)
                ->with('images')
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            DB::beginTransaction();

            // Delete featured image from custom folder
            if ($product->featured_image) {
                $this->deleteCustomImage($product->featured_image);
            }

            // Delete additional images from custom folder
            foreach ($product->images as $image) {
                $this->deleteCustomImage($image->image_path);
            }

            // Soft delete product
            $product->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting seller product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product'
            ], 500);
        }
    }

    /**
     * Get seller dashboard statistics
     */
    public function getDashboardStats(): JsonResponse
    {
        try {
            $user = Auth::user();

            $stats = [
                'total_products' => Product::where('seller_id', $user->id)->count(),
                'active_products' => Product::where('seller_id', $user->id)
                    ->where('approval_status', 'approved')
                    ->where('is_active', true)
                    ->count(),
                'pending_approval' => Product::where('seller_id', $user->id)
                    ->where('approval_status', 'pending')
                    ->count(),
                'out_of_stock' => Product::where('seller_id', $user->id)
                    ->where('stock_status', 'out_of_stock')
                    ->count(),
                'low_stock' => Product::where('seller_id', $user->id)
                    ->whereRaw('stock <= low_stock_threshold')
                    ->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching dashboard stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard statistics'
            ], 500);
        }
    }

    /**
     * Upload image to custom folder structure
     */
    private function uploadToCustomFolder($file, $directory, $sellerId): string
    {
        // Create custom directory structure: public/seller_products/{seller_id}/{directory}
        $customPath = "seller_products/{$sellerId}/{$directory}";

        // Create directory if it doesn't exist
        $fullPath = public_path($customPath);
        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Generate unique filename
        $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

        // Move file to custom directory
        $file->move($fullPath, $filename);

        return $customPath . '/' . $filename;
    }

    /**
     * Handle additional product images
     */
    private function handleAdditionalImages(Product $product, array $images, $sellerId): void
    {
        $sortOrder = $product->images()->max('sort_order') ?? 0;

        foreach ($images as $index => $image) {
            $imagePath = $this->uploadToCustomFolder($image, 'gallery', $sellerId);

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'alt_text' => $product->name . ' - Image ' . ($sortOrder + $index + 1),
                'sort_order' => $sortOrder + $index + 1
            ]);
        }
    }

    /**
     * Delete images from custom folder
     */
    private function deleteCustomImage(string $path): void
    {
        $fullPath = public_path($path);
        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }
    }

    /**
     * Delete specific product images
     */
    private function deleteProductImages(Product $product, array $imageIds): void
    {
        $imagesToDelete = ProductImage::whereIn('id', $imageIds)
            ->where('product_id', $product->id)
            ->get();

        foreach ($imagesToDelete as $image) {
            // Delete file from custom folder
            $this->deleteCustomImage($image->image_path);

            // If this was the featured image, clear it
            if ($product->featured_image == $image->image_path) {
                $product->update(['featured_image' => null]);
            }

            // Delete record
            $image->delete();
        }
    }

    /**
     * Get custom image URL
     */
    private function getCustomImageUrl(string $path): string
    {
        return url($path);
    }

    /**
     * Generate unique SKU (reuse from your existing controller)
     */
    private function generateSKU(string $productName): string
    {
        $baseSlug = Str::slug($productName);
        $baseSku = strtoupper(substr($baseSlug, 0, 8));

        $counter = 1;
        $sku = $baseSku . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);

        while (Product::where('sku', $sku)->exists()) {
            $counter++;
            $sku = $baseSku . '-' . str_pad($counter, 4, '0', STR_PAD_LEFT);
        }

        return $sku;
    }

     public function getProducts(Request $request): JsonResponse
    {
        $seller = Auth::user();

        $query = Product::where('seller_id', $seller->id)
            ->with(['category']);

        // Apply filters
        if ($request->has('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('sku', 'like', '%' . $request->search . '%');
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 20);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $products->items(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }


}
