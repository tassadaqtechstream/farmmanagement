<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Constructor with middleware
     */
    public function __construct()
    {
       // $this->middleware(['auth:api', 'role:admin|b2b_admin']);
    }

    /**
     * Get all categories
     */
    public function index(Request $request)
    {
        $query = Category::query();

        // Filter by parent_id (null or specific)
        if ($request->has('parent_id')) {
            if ($request->parent_id === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $request->parent_id);
            }
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        // Filter by is_b2b_visible
        if ($request->has('is_b2b_visible')) {
            $query->where('is_b2b_visible', $request->is_b2b_visible);
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Apply sorting
        $sortField = $request->sort_by ?? 'sort_order';
        $sortDirection = $request->sort_direction ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        // Load relationship counts
        if ($request->has('with_counts') && $request->with_counts) {
            $query->withCount(['products', 'children']);
        }

        // Handle pagination
        if ($request->has('per_page') && $request->per_page !== 'all') {
            $perPage = (int) $request->per_page;
            $categories = $query->paginate($perPage);
            return response()->json([
                'categories' => $categories->items(),
                'total' => $categories->total(),
                'page' => $categories->currentPage(),
                'pageSize' => $categories->perPage(),
                'totalPages' => $categories->lastPage(),
                'data' => $categories->items()
            ]);
        } else {
            $categories = $query->get();
            return response()->json([
                'categories' => $categories,
                'total' => $categories->count(),
                'page' => 1,
                'pageSize' => $categories->count(),
                'totalPages' => 1,
                'data' => $categories
            ]);
        }
    }


    /**
     * Get category tree (hierarchical)
     */
    public function tree()
    {
        // Get all root categories
        $rootCategories = Category::whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        // Recursively load children
        $tree = $this->buildCategoryTree($rootCategories);

        return response()->json([
            'tree' => $tree
        ]);
    }

    /**
     * Store a new category
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:categories',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:categories,id',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'is_b2b_visible' => 'boolean',
            'meta_data' => 'nullable|array',
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

                while (Category::where('slug', $slug)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }
            } else {
                $slug = $request->slug;
            }

            // Upload image if provided
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('categories', 'public');
            }

            // Create category
            $category = Category::create([
                'name' => $request->name,
                'slug' => $slug,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'image' => $imagePath,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->filled('is_active') ? $request->is_active : true,
                'is_featured' => $request->filled('is_featured') ? $request->is_featured : false,
                'is_b2b_visible' => $request->filled('is_b2b_visible') ? $request->is_b2b_visible : true,
                'meta_data' => $request->meta_data,
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Category created successfully',
                'category' => $category
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific category
     */
    public function show($id)
    {
        $category = Category::with(['parent', 'children'])->findOrFail($id);
        $category->loadCount(['products', 'allProducts']);

        return response()->json([
            'category' => $category
        ]);
    }

    /**
     * Update a category
     */
    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|nullable|string|max:255|unique:categories,slug,' . $id,
            'description' => 'sometimes|nullable|string',
            'parent_id' => 'sometimes|nullable|exists:categories,id',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'sort_order' => 'sometimes|nullable|integer|min:0',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_b2b_visible' => 'sometimes|boolean',
            'meta_data' => 'sometimes|nullable|array',
            'delete_image' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Prevent setting parent to self or descendant
        if ($request->has('parent_id') && $request->parent_id) {
            if ($request->parent_id == $id) {
                return response()->json([
                    'message' => 'A category cannot be its own parent',
                    'errors' => ['parent_id' => ['A category cannot be its own parent']]
                ], 422);
            }

            // Check if the new parent is a descendant of this category
            $descendantIds = $this->getAllDescendantIds($id);
            if (in_array($request->parent_id, $descendantIds)) {
                return response()->json([
                    'message' => 'A category cannot have a descendant as its parent',
                    'errors' => ['parent_id' => ['A category cannot have a descendant as its parent']]
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // Update slug if name changed and slug not provided
            if ($request->has('name') && !$request->filled('slug') && $request->name != $category->name) {
                $slug = Str::slug($request->name);
                $originalSlug = $slug;
                $count = 1;

                while (Category::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                    $slug = $originalSlug . '-' . $count++;
                }

                $request->merge(['slug' => $slug]);
            }

            // Handle image upload
            if ($request->hasFile('image')) {
                // Delete old image if exists
                if ($category->image) {
                    Storage::disk('public')->delete($category->image);
                }

                // Upload new image
                $imagePath = $request->file('image')->store('categories', 'public');
                $request->merge(['image' => $imagePath]);
            }

            // If delete_image flag is set, remove the image
            if ($request->has('delete_image') && $request->delete_image && $category->image) {
                Storage::disk('public')->delete($category->image);
                $request->merge(['image' => null]);
            }

            // Update category
            $category->update($request->only([
                'name', 'slug', 'description', 'parent_id', 'image',
                'sort_order', 'is_active', 'is_featured', 'is_b2b_visible', 'meta_data'
            ]));

            DB::commit();

            return response()->json([
                'message' => 'Category updated successfully',
                'category' => $category->fresh(['parent', 'children'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a category
     */
    public function destroy(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        $forceDelete = $request->input('force_delete', false);

        // Check if category has products
        $productsCount = $category->allProducts()->count();

        // Check if category has children
        $childrenCount = $category->children()->count();

        // If not forcing delete, validate there are no products or children
        if (!$forceDelete) {
            if ($productsCount > 0) {
                return response()->json([
                    'message' => 'Cannot delete category with associated products',
                    'products_count' => $productsCount
                ], 422);
            }

            if ($childrenCount > 0) {
                return response()->json([
                    'message' => 'Cannot delete category with child categories',
                    'children_count' => $childrenCount
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            // If forcing delete, handle products and children
            if ($forceDelete) {
                // Update products to remove this category
                foreach ($category->products as $product) {
                    if ($product->category_id == $category->id) {
                        $product->category_id = null;
                        $product->save();
                    }
                }

                // Detach from pivot table
                $category->allProducts()->detach();

                // If request specifies reassign_to, move children to that category
                if ($request->has('reassign_to') && $request->reassign_to) {
                    $targetCategory = Category::find($request->reassign_to);
                    if ($targetCategory) {
                        $category->children()->update(['parent_id' => $targetCategory->id]);
                    }
                } else {
                    // Otherwise, make children top-level categories
                    $category->children()->update(['parent_id' => null]);
                }
            }

            // Delete image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            // Delete category
            $category->delete();

            DB::commit();

            return response()->json([
                'message' => 'Category deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update category sort order (batch update)
     */
    public function updateSortOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'categories' => 'required|array',
            'categories.*.id' => 'required|exists:categories,id',
            'categories.*.sort_order' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();

        try {
            foreach ($request->categories as $item) {
                Category::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Category sort order updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update category sort order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get products in a category
     */
    public function getCategoryProducts($id, Request $request)
    {
        $category = Category::findOrFail($id);

        $query = Product::where(function($q) use ($id) {
            $q->where('category_id', $id)
                ->orWhereHas('categories', function($q) use ($id) {
                    $q->where('categories.id', $id);
                });
        });

        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('is_b2b_available')) {
            $query->where('is_b2b_available', $request->is_b2b_available);
        }

        // Apply sorting
        $sortField = $request->sort_by ?? 'name';
        $sortDirection = $request->sort_direction ?? 'asc';
        $query->orderBy($sortField, $sortDirection);

        // Get paginated results
        $products = $query->with(['images'])->paginate($request->per_page ?? 15);

        return response()->json([
            'category' => $category,
            'products' => $products
        ]);
    }

    /**
     * Toggle B2B visibility for a category
     */
    public function toggleB2BVisibility($id)
    {
        $category = Category::findOrFail($id);
        $category->is_b2b_visible = !$category->is_b2b_visible;
        $category->save();

        return response()->json([
            'message' => 'Category B2B visibility updated',
            'category' => $category
        ]);
    }

    /**
     * Move products between categories
     */
    public function moveProducts(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'source_category_id' => 'required|exists:categories,id',
            'target_category_id' => 'required|exists:categories,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'move_type' => 'required|in:move,copy',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $sourceCategory = Category::findOrFail($request->source_category_id);
        $targetCategory = Category::findOrFail($request->target_category_id);
        $productIds = $request->product_ids;
        $moveType = $request->move_type;

        DB::beginTransaction();

        try {
            // Get products that belong to the source category
            $products = Product::whereIn('id', $productIds)
                ->where(function($q) use ($sourceCategory) {
                    $q->where('category_id', $sourceCategory->id)
                        ->orWhereHas('categories', function($q) use ($sourceCategory) {
                            $q->where('categories.id', $sourceCategory->id);
                        });
                })->get();

            foreach ($products as $product) {
                if ($moveType === 'move') {
                    // If it's the primary category, update it
                    if ($product->category_id == $sourceCategory->id) {
                        $product->category_id = $targetCategory->id;
                        $product->save();
                    }

                    // Remove from source category in pivot table
                    $product->categories()->detach($sourceCategory->id);
                }

                // Add to target category in pivot table (if not already there)
                if (!$product->categories()->where('category_id', $targetCategory->id)->exists()) {
                    $product->categories()->attach($targetCategory->id);
                }
            }

            DB::commit();

            return response()->json([
                'message' => count($products) . ' products ' .
                    ($moveType === 'move' ? 'moved to' : 'copied to') .
                    ' category ' . $targetCategory->name
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to move products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper method to build category tree recursively
     */
    private function buildCategoryTree($categories)
    {
        $tree = [];

        foreach ($categories as $category) {
            $node = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'is_active' => $category->is_active,
                'is_featured' => $category->is_featured,
                'is_b2b_visible' => $category->is_b2b_visible,
                'sort_order' => $category->sort_order,
                'image' => $category->image,
                'products_count' => $category->products_count ?? $category->products()->count(),
                'children' => []
            ];

            $children = Category::where('parent_id', $category->id)
                ->orderBy('sort_order')
                ->get();

            if ($children->count() > 0) {
                $node['children'] = $this->buildCategoryTree($children);
            }

            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * Helper method to get all descendant category IDs
     */
    private function getAllDescendantIds($categoryId)
    {
        $ids = [];
        $children = Category::where('parent_id', $categoryId)->get();

        foreach ($children as $child) {
            $ids[] = $child->id;
            $childIds = $this->getAllDescendantIds($child->id);
            $ids = array_merge($ids, $childIds);
        }

        return $ids;
    }
}
