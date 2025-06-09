<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Traits\ImageUploadTrait; // Add this line
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SellerProductController extends Controller
{
    use ImageUploadTrait; // Add this line

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
     * Store a newly created seller product
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Get image validation rules from trait and merge with custom validation
            $imageRules = $this->getImageValidationRules();

            $validator = Validator::make($request->all(), array_merge([
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
            ], $imageRules));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $user = Auth::user();

            Log::info('user data ', ['data' => $user]);

            // Generate SKU using existing method
            $sku = $this->generateSKU($request->name);

            // Create product using existing structure but with seller modifications
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

            // Handle images using the trait (Seller upload - with seller_id)
            $this->processProductImages($product, $request, $user->id);

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

            // Transform products using the trait
            $transformedProducts = collect($products->items())->map(function($product) {
                return $this->transformProductImages($product);
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

            // Transform images using the trait
            $productData = $this->transformProductImages($product);

            return response()->json([
                'success' => true,
                'data' => $productData
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

            // Get image validation rules from trait
            $imageRules = $this->getImageValidationRules();

            $validator = Validator::make($request->all(), array_merge([
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
            ], $imageRules));

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

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

            // Handle image updates using the trait (Seller update - with seller_id)
            $featuredImagePath = $this->updateProductImages($product, $request, $user->id);

            // Update featured image path if changed
            if ($featuredImagePath !== $product->featured_image) {
                $product->update(['featured_image' => $featuredImagePath]);
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

            // Clean up images using the trait
            $this->cleanupProductImages($product);

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
