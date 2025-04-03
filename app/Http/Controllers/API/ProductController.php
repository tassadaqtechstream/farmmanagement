<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductVolumePricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Constructor with middleware
     */
    public function __construct()
    {
        // $this->middleware(['auth:api', 'role:admin|b2b_admin']);
    }

    /**
     * Get paginated list of products
     */
    public function index(Request $request)
    {
        $query = Product::query();

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_b2b_available')) {
            $query->where('is_b2b_available', $request->is_b2b_available);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortField = $request->sort_by ?? 'created_at';
        $sortDirection = $request->sort_direction ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        // Get paginated results
        $perPage = $request->per_page ?? 15;
        $page = $request->page ?? 1;
        $products = $query->with(['category', 'images'])->paginate($perPage);

        // Fetch all categories for the dropdown
        $categories = Category::select('id', 'name')->orderBy('name')->get();

        // Structure response to match frontend expectations
        return response()->json([
            'data' => $products->items(),
            'total' => $products->total(),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'last_page' => $products->lastPage(),
            'categories' => $categories
        ]);
    }

    /**
     * Store a new product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // Existing validations...
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'required|string|max:100|unique:products',
            'barcode' => 'nullable|string|max:100|unique:products',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'compare_at_price' => 'nullable|numeric|min:0',
            'b2b_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'stock_status' => 'nullable|string|in:in_stock,out_of_stock,backorder',
            'weight' => 'nullable|numeric|min:0',
            'length' => 'nullable|numeric|min:0',
            'width' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'is_b2b_available' => 'boolean',
            'b2b_min_quantity' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'brand' => 'nullable|string|max:100',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'categories' => 'nullable|array',
            'categories.*' => 'exists:categories,id',
            'attributes' => 'nullable|array',
            'volume_pricing' => 'nullable|array',
            'volume_pricing.*.min_quantity' => 'required|integer|min:1',
            'volume_pricing.*.max_quantity' => 'nullable|integer|min:1',
            'volume_pricing.*.price' => 'required|numeric|min:0',

            // Add missing validations
            'sale_price' => 'nullable|numeric|min:0',
            'track_inventory' => 'boolean',
            'package_dimensions' => 'nullable|string|max:255',
            'taxable' => 'boolean',
            'canonical_url' => 'nullable|string|max:255',
            'b2b_discount' => 'nullable|numeric|min:0|max:100',
            'b2b_terms' => 'nullable|string',
            'variants' => 'nullable|string', // JSON string of variants
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Generate slug if not provided
            if (!$request->filled('slug')) {
                $slug = Str::slug($request->name);
                $originalSlug = $slug;
                $count = 1;

                while (Product::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
            } else {
                $slug = $request->slug;
            }

            // Create product with additional fields
            $product = Product::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'short_description' => $request->short_description,
                'category_id' => $request->category_id,
                'sku' => $request->sku,
                'barcode' => $request->barcode,
                'brand' => $request->brand,
                'price' => $request->price,
                'cost_price' => $request->cost_price,
                'compare_at_price' => $request->compare_at_price,
                'b2b_price' => $request->b2b_price,
                'stock' => $request->stock,
                'stock_status' => $request->stock_status ?? 'in_stock',
                'weight' => $request->weight,
                'length' => $request->length,
                'width' => $request->width,
                'height' => $request->height,
                'is_b2b_available' => $request->is_b2b_available ?? false,
                'b2b_min_quantity' => $request->b2b_min_quantity ?? 1,
                'is_bulk_pricing_eligible' => $request->has('volume_pricing'),
                'is_active' => $request->is_active ?? true,
                'is_featured' => $request->is_featured ?? false,
                'meta_title' => $request->meta_title,
                'meta_description' => $request->meta_description,
                'meta_keywords' => $request->meta_keywords,
                'published_at' => $request->is_active ? now() : null,

                // Add missing fields
                'sale_price' => $request->sale_price,
                'track_inventory' => $request->track_inventory ?? true,
                'package_dimensions' => $request->package_dimensions,
                'taxable' => $request->taxable ?? true,
                'canonical_url' => $request->canonical_url,
                'b2b_discount' => $request->b2b_discount,
                'b2b_terms' => $request->b2b_terms,
            ]);

            // Rest of the function stays the same...
            // Handle additional categories if provided
            if ($request->has('categories') && is_array($request->categories)) {
                $product->categories()->sync($request->categories);
            } elseif ($request->category_id) {
                $product->categories()->sync([$request->category_id]);
            }

            // Handle product attributes if provided
            if ($request->has('attributes') && is_array($request->attributes)) {
                foreach ($request->attributes as $attrName => $attrValue) {
                    // Find or create attribute
                    $attribute = ProductAttribute::firstOrCreate(
                        ['slug' => Str::slug($attrName)],
                        ['name' => $attrName]
                    );

                    // Find or create attribute value
                    $attributeValue = ProductAttributeValue::firstOrCreate(
                        [
                            'attribute_id' => $attribute->id,
                            'slug' => Str::slug($attrValue)
                        ],
                        ['value' => $attrValue]
                    );

                    // Attach to product
                    $product->productAttributes()->attach($attribute->id, ['attribute_value_id' => $attributeValue->id]);
                }
            }

            // Handle volume pricing if provided
            if ($request->has('volume_pricing') && is_array($request->volume_pricing)) {
                foreach ($request->volume_pricing as $pricing) {
                    ProductVolumePricing::create([
                        'product_id' => $product->id,
                        'min_quantity' => $pricing['min_quantity'],
                        'max_quantity' => $pricing['max_quantity'] ?? null,
                        'price' => $pricing['price'],
                        'discount_percentage' => isset($pricing['discount_percentage']) ? $pricing['discount_percentage']
                            : round(($product->price - $pricing['price']) / $product->price * 100, 2),
                    ]);
                }
            }

            // Handle variants if provided
            if ($request->filled('variants')) {
                $variants = json_decode($request->variants, true);

                if (is_array($variants) && count($variants) > 0) {
                    foreach ($variants as $variant) {
                        ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $variant['sku'] ?? '',
                            'barcode' => $variant['barcode'] ?? null,
                            'price' => $variant['price'] ?? $product->price,
                            'b2b_price' => $variant['b2b_price'] ?? null,
                            'stock' => $variant['stock'] ?? 0,
                            'stock_status' => $variant['stock_status'] ?? 'in_stock',
                            'weight' => $variant['weight'] ?? null,
                            'length' => $variant['length'] ?? null,
                            'width' => $variant['width'] ?? null,
                            'height' => $variant['height'] ?? null,
                            'image' => null, // You'll need to handle variant image uploads separately
                            'is_active' => $variant['is_active'] ?? true,
                            'is_default' => $variant['is_default'] ?? false,
                            'attributes' => json_encode($variant['attributes'] ?? []),
                        ]);
                    }

                }
            }
            // Handle images if uploaded
            if ($request->hasFile('images')) {
                $sortOrder = 1;
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'alt_text' => $product->name,
                        'sort_order' => $sortOrder++,
                    ]);

                    // Set the first image as featured image
                    if ($sortOrder == 2) {
                        $product->update(['featured_image' => $path]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product created successfully',
                'product' => $product->load(['category', 'images', 'categories', 'volumePricing', 'variants'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific product
     */
    public function show($id)
    {
        $product = Product::with([
            'category',
            'categories',
            'images',
            'productAttributes.attribute',
            'productAttributes.attributeValue',
            'volumePricing',
            'variants',
            'businessPricing'
        ])->findOrFail($id);

        return response()->json([
            'product' => $product
        ]);
    }

    /**
     * Update a product
     */
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:products,slug,' . $id,
            'description' => 'sometimes|nullable|string',
            'short_description' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'sku' => 'sometimes|required|string|max:100|unique:products,sku,' . $id,
            'barcode' => 'sometimes|nullable|string|max:100|unique:products,barcode,' . $id,
            'price' => 'sometimes|required|numeric|min:0',
            'cost_price' => 'sometimes|nullable|numeric|min:0',
            'compare_at_price' => 'sometimes|nullable|numeric|min:0',
            'b2b_price' => 'sometimes|nullable|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
            'stock_status' => 'sometimes|nullable|string|in:in_stock,out_of_stock,backorder',
            'weight' => 'sometimes|nullable|numeric|min:0',
            'length' => 'sometimes|nullable|numeric|min:0',
            'width' => 'sometimes|nullable|numeric|min:0',
            'height' => 'sometimes|nullable|numeric|min:0',
            'is_b2b_available' => 'sometimes|boolean',
            'b2b_min_quantity' => 'sometimes|nullable|integer|min:1',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'brand' => 'sometimes|nullable|string|max:100',
            'meta_title' => 'sometimes|nullable|string|max:255',
            'meta_description' => 'sometimes|nullable|string',
            'meta_keywords' => 'sometimes|nullable|string|max:255',
            'images' => 'sometimes|nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'categories' => 'sometimes|nullable|array',
            'categories.*' => 'exists:categories,id',
            'attributes' => 'sometimes|nullable|array',
            'volume_pricing' => 'sometimes|nullable|array',
            'volume_pricing.*.id' => 'nullable|exists:product_volume_pricing,id',
            'volume_pricing.*.min_quantity' => 'required|integer|min:1',
            'volume_pricing.*.max_quantity' => 'nullable|integer|min:1',
            'volume_pricing.*.price' => 'required|numeric|min:0',
            'delete_images' => 'sometimes|array',
            'delete_images.*' => 'exists:product_images,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            // Update slug if name changed and slug not provided
            if ($request->has('name') && !$request->filled('slug') && $request->name != $product->name) {
                $slug = Str::slug($request->name);
                $originalSlug = $slug;
                $count = 1;

                while (Product::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }

                $request->merge(['slug' => $slug]);
            }

            // Update product
            $product->update($request->only([
                'name', 'slug', 'description', 'short_description', 'category_id',
                'sku', 'barcode', 'brand', 'price', 'cost_price', 'compare_at_price',
                'b2b_price', 'stock', 'stock_status', 'weight', 'length', 'width', 'height',
                'is_b2b_available', 'b2b_min_quantity', 'is_active', 'is_featured',
                'meta_title', 'meta_description', 'meta_keywords'
            ]));

            // Update published_at based on active status
            if ($request->has('is_active')) {
                $product->published_at = $request->is_active ? now() : null;
                $product->save();
            }

            // Update bulk pricing eligibility
            if ($request->has('volume_pricing')) {
                $product->is_bulk_pricing_eligible = !empty($request->volume_pricing);
                $product->save();
            }

            // Update categories if provided
            if ($request->has('categories')) {
                $product->categories()->sync($request->categories);
            }

            // Update product attributes if provided
            if ($request->has('attributes')) {
                // First detach all existing attributes
                $product->productAttributes()->detach();

                foreach ($request->attributes as $attrName => $attrValue) {
                    // Find or create attribute
                    $attribute = ProductAttribute::firstOrCreate(
                        ['slug' => Str::slug($attrName)],
                        ['name' => $attrName]
                    );

                    // Find or create attribute value
                    $attributeValue = ProductAttributeValue::firstOrCreate(
                        [
                            'attribute_id' => $attribute->id,
                            'slug' => Str::slug($attrValue)
                        ],
                        ['value' => $attrValue]
                    );

                    // Attach to product
                    $product->productAttributes()->attach($attribute->id, ['attribute_value_id' => $attributeValue->id]);
                }
            }

            // Update volume pricing if provided
            if ($request->has('volume_pricing')) {
                // Get existing pricing IDs
                $existingIds = $product->volumePricing->pluck('id')->toArray();
                $updateIds = [];

                foreach ($request->volume_pricing as $pricing) {
                    if (isset($pricing['id']) && in_array($pricing['id'], $existingIds)) {
                        // Update existing
                        ProductVolumePricing::where('id', $pricing['id'])->update([
                            'min_quantity' => $pricing['min_quantity'],
                            'max_quantity' => $pricing['max_quantity'] ?? null,
                            'price' => $pricing['price'],
                            'discount_percentage' => isset($pricing['discount_percentage']) ? $pricing['discount_percentage']
                                : round(($product->price - $pricing['price']) / $product->price * 100, 2),
                        ]);
                        $updateIds[] = $pricing['id'];
                    } else {
                        // Create new
                        ProductVolumePricing::create([
                            'product_id' => $product->id,
                            'min_quantity' => $pricing['min_quantity'],
                            'max_quantity' => $pricing['max_quantity'] ?? null,
                            'price' => $pricing['price'],
                            'discount_percentage' => isset($pricing['discount_percentage']) ? $pricing['discount_percentage']
                                : round(($product->price - $pricing['price']) / $product->price * 100, 2),
                        ]);
                    }
                }

                // Delete pricing not in the update
                $deleteIds = array_diff($existingIds, $updateIds);
                if (!empty($deleteIds)) {
                    ProductVolumePricing::whereIn('id', $deleteIds)->delete();
                }
            }

            // Delete images if requested
            if ($request->has('delete_images') && is_array($request->delete_images)) {
                $imagesToDelete = ProductImage::whereIn('id', $request->delete_images)
                    ->where('product_id', $product->id)
                    ->get();

                foreach ($imagesToDelete as $image) {
                    // Delete from storage
                    Storage::disk('public')->delete($image->image_path);

                    // If this was the featured image, clear it
                    if ($product->featured_image == $image->image_path) {
                        $product->featured_image = null;
                        $product->save();
                    }

                    // Delete record
                    $image->delete();
                }
            }

            // Handle new images if uploaded
            if ($request->hasFile('images')) {
                $sortOrder = $product->images()->max('sort_order') + 1;
                foreach ($request->file('images') as $image) {
                    $path = $image->store('products', 'public');

                    $newImage = ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'alt_text' => $product->name,
                        'sort_order' => $sortOrder++,
                    ]);

                    // Set as featured image if none exists
                    if (empty($product->featured_image)) {
                        $product->featured_image = $path;
                        $product->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Product updated successfully',
                'product' => $product->fresh(['category', 'images', 'categories', 'volumePricing'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a product
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete related images from storage
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            // Delete the product (using soft delete)
            $product->delete();

            DB::commit();

            return response()->json([
                'message' => 'Product deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted product
     */
    public function restore($id)
    {
        $product = Product::withTrashed()->findOrFail($id);
        $product->restore();

        return response()->json([
            'message' => 'Product restored successfully',
            'product' => $product
        ]);
    }

    /**
     * Permanently delete a product
     */
    public function forceDelete($id)
    {
        $product = Product::withTrashed()->findOrFail($id);

        DB::beginTransaction();

        try {
            // Delete related images from storage
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            // Force delete the product and related data
            $product->images()->delete();
            $product->volumePricing()->delete();
            $product->businessPricing()->delete();
            $product->productAttributes()->detach();
            $product->categories()->detach();
            $product->forceDelete();

            DB::commit();

            return response()->json([
                'message' => 'Product permanently deleted'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to permanently delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product stock
     */
    public function updateStock(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:0',
            'stock_status' => 'sometimes|string|in:in_stock,out_of_stock,backorder',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($id);

        $product->stock = $request->stock;

        if ($request->has('stock_status')) {
            $product->stock_status = $request->stock_status;
        } else {
            // Automatically set stock status based on stock level
            $product->stock_status = $request->stock > 0 ? 'in_stock' : 'out_of_stock';
        }

        $product->save();

        return response()->json([
            'message' => 'Product stock updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Update B2B settings for a product
     */
    public function updateB2BSettings(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_b2b_available' => 'required|boolean',
            'b2b_price' => 'required_if:is_b2b_available,true|nullable|numeric|min:0',
            'b2b_min_quantity' => 'sometimes|integer|min:1',
            'volume_pricing' => 'sometimes|array',
            'volume_pricing.*.min_quantity' => 'required|integer|min:1',
            'volume_pricing.*.max_quantity' => 'nullable|integer|min:1',
            'volume_pricing.*.price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = Product::findOrFail($id);

        DB::beginTransaction();

        try {
            $product->is_b2b_available = $request->is_b2b_available;

            if ($request->has('b2b_price')) {
                $product->b2b_price = $request->b2b_price;
            }

            if ($request->has('b2b_min_quantity')) {
                $product->b2b_min_quantity = $request->b2b_min_quantity;
            }

            // Update bulk pricing eligibility
            if ($request->has('volume_pricing')) {
                $product->is_bulk_pricing_eligible = !empty($request->volume_pricing);

                // Delete existing volume pricing
                $product->volumePricing()->delete();

                // Create new volume pricing
                foreach ($request->volume_pricing as $pricing) {
                    ProductVolumePricing::create([
                        'product_id' => $product->id,
                        'min_quantity' => $pricing['min_quantity'],
                        'max_quantity' => $pricing['max_quantity'] ?? null,
                        'price' => $pricing['price'],
                        'discount_percentage' => isset($pricing['discount_percentage']) ? $pricing['discount_percentage']
                            : round(($product->price - $pricing['price']) / $product->price * 100, 2),
                    ]);
                }
            }

            $product->save();

            DB::commit();

            return response()->json([
                'message' => 'Product B2B settings updated successfully',
                'product' => $product->fresh(['volumePricing'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update B2B settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Bulk update products
     */
    public function bulkUpdate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Validate update data
        $dataValidator = Validator::make($request->data, [
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_b2b_available' => 'sometimes|boolean',
            'category_id' => 'sometimes|nullable|exists:categories,id',
            'b2b_price' => 'sometimes|nullable|numeric|min:0',
            'b2b_min_quantity' => 'sometimes|nullable|integer|min:1',
            'stock_status' => 'sometimes|string|in:in_stock,out_of_stock,backorder',
            'price_adjustment' => 'sometimes|array',
            'price_adjustment.type' => 'required_with:price_adjustment|string|in:percentage,fixed',
            'price_adjustment.value' => 'required_with:price_adjustment|numeric',
            'price_adjustment.operation' => 'required_with:price_adjustment|string|in:increase,decrease',
        ]);

        if ($dataValidator->fails()) {
            return response()->json(['errors' => $dataValidator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $productsToUpdate = Product::whereIn('id', $request->product_ids);
            $updateData = [];

            // Direct field updates
            foreach (['is_active', 'is_featured', 'is_b2b_available', 'category_id', 'b2b_price', 'b2b_min_quantity', 'stock_status'] as $field) {
                if (array_key_exists($field, $request->data)) {
                    $updateData[$field] = $request->data[$field];
                }
            }

            // Handle price adjustment if specified
            if (isset($request->data['price_adjustment'])) {
                $adjustment = $request->data['price_adjustment'];

                // For percentage adjustments, we need to update each product individually
                if ($adjustment['type'] === 'percentage') {
                    foreach ($productsToUpdate->get() as $product) {
                        if ($adjustment['operation'] === 'increase') {
                            $newPrice = $product->price * (1 + ($adjustment['value'] / 100));
                        } else {
                            $newPrice = $product->price * (1 - ($adjustment['value'] / 100));
                        }

                        $product->price = round($newPrice, 2);
                        $product->save();
                    }
                } // For fixed amount adjustments
                else {
                    $products = $productsToUpdate->get();
                    foreach ($products as $product) {
                        if ($adjustment['operation'] === 'increase') {
                            $newPrice = $product->price + $adjustment['value'];
                        } else {
                            $newPrice = max(0, $product->price - $adjustment['value']);
                        }

                        $product->price = $newPrice;
                        $product->save();
                    }
                }
            }

            // Apply direct updates if there are any
            if (!empty($updateData)) {
                $productsToUpdate->update($updateData);
            }

            // If updating categories, handle the many-to-many relationship
            if (isset($request->data['categories']) && is_array($request->data['categories'])) {
                foreach ($request->product_ids as $productId) {
                    $product = Product::find($productId);
                    if ($product) {
                        $product->categories()->sync($request->data['categories']);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => count($request->product_ids) . ' products updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete products
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'permanent' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            $isPermanent = $request->permanent ?? false;
            $count = count($request->product_ids);

            if ($isPermanent) {
                // Get all products to delete their images
                $products = Product::whereIn('id', $request->product_ids)->get();

                foreach ($products as $product) {
                    // Delete product images from storage
                    foreach ($product->images as $image) {
                        Storage::disk('public')->delete($image->image_path);
                    }

                    // Remove relationships
                    $product->images()->delete();
                    $product->volumePricing()->delete();
                    $product->businessPricing()->delete();
                    $product->productAttributes()->detach();
                    $product->categories()->detach();
                }

                // Force delete the products
                Product::whereIn('id', $request->product_ids)->forceDelete();
            } else {
                // Soft delete
                Product::whereIn('id', $request->product_ids)->delete();
            }

            DB::commit();

            return response()->json([
                'message' => $count . ' products ' . ($isPermanent ? 'permanently deleted' : 'moved to trash')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all product attributes
     */
    public function getAttributes()
    {
        $attributes = ProductAttribute::with('values')->get();

        return response()->json([
            'attributes' => $attributes
        ]);
    }

    /**
     * Import products from CSV/Excel
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'update_existing' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Store the uploaded file
        $path = $request->file('file')->store('imports');

        // Queue the import job
        // This should be implemented as a separate job class
        // For example: ImportProducts::dispatch($path, $request->update_existing ?? false);

        return response()->json([
            'message' => 'Product import has been queued',
            'job_id' => 'import_' . time() // This would normally be a real job ID
        ]);
    }

    /**
     * Export products to CSV/Excel
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'filter' => 'sometimes|array',
            'format' => 'sometimes|string|in:csv,xlsx',
            'columns' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Build query based on filters
        $query = Product::query();

        if ($request->has('filter')) {
            $filter = $request->filter;

            if (isset($filter['category_id'])) {
                $query->where('category_id', $filter['category_id']);
            }

            if (isset($filter['is_b2b_available'])) {
                $query->where('is_b2b_available', $filter['is_b2b_available']);
            }

            if (isset($filter['is_active'])) {
                $query->where('is_active', $filter['is_active']);
            }

            if (isset($filter['search'])) {
                $search = $filter['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }
        }

        // Get all products matching the filters
        $products = $query->with(['category', 'categories'])->get();

        // Generate a unique filename
        $filename = 'products_export_' . date('Y-m-d_His') . '.' . ($request->format ?? 'csv');

        // This would normally generate and return the file
        // Here we're just returning a success message

        return response()->json([
            'message' => 'Product export has been generated',
            'file' => $filename,
            'count' => $products->count(),
            'download_url' => url('api/admin/downloads/' . $filename)
        ]);
    }
}
