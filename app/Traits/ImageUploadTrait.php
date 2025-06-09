<?php

namespace App\Traits;

use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ImageUploadTrait
{
    /**
     * Upload image to custom folder structure
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param int|null $sellerId
     * @param string $disk
     * @return string
     */
    protected function uploadToCustomFolder(
        UploadedFile $file,
        string $directory,
        ?int $sellerId = null,
        string $disk = 'public'
    ): string {
        // Determine path based on whether it's a seller upload or admin upload
        if ($sellerId) {
            // Seller uploads go to: seller_products/{seller_id}/{directory}
            $customPath = "seller_products/{$sellerId}/{$directory}";
            $fullPath = public_path($customPath);
        } else {
            // Admin uploads go to: products/{directory}
            $customPath = "products/{$directory}";
            $fullPath = public_path($customPath);
        }

        // Create directory if it doesn't exist
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
     * Upload image using Laravel Storage (fallback method)
     *
     * @param UploadedFile $file
     * @param string $directory
     * @param string $disk
     * @return string
     */
    protected function uploadToStorage(UploadedFile $file, string $directory = 'products', string $disk = 'public'): string
    {
        return $file->store($directory, $disk);
    }

    /**
     * Handle additional product images
     *
     * @param \App\Models\Product $product
     * @param array $images
     * @param int|null $sellerId
     * @param string $directory
     * @return void
     */
    protected function handleAdditionalImages(
        $product,
        array $images,
        ?int $sellerId = null,
        string $directory = 'gallery'
    ): void {
        $sortOrder = $product->images()->max('sort_order') ?? 0;

        foreach ($images as $index => $image) {
            $imagePath = $this->uploadToCustomFolder($image, $directory, $sellerId);

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $imagePath,
                'alt_text' => $product->name . ' - Image ' . ($sortOrder + $index + 1),
                'sort_order' => $sortOrder + $index + 1
            ]);
        }
    }

    /**
     * Handle featured image upload
     *
     * @param UploadedFile $file
     * @param int|null $sellerId
     * @param string $directory
     * @return string
     */
    protected function handleFeaturedImage(
        UploadedFile $file,
        ?int $sellerId = null,
        string $directory = 'featured'
    ): string {
        if ($sellerId) {
            return $this->uploadToCustomFolder($file, $directory, $sellerId);
        } else {
            return $this->uploadToCustomFolder($file, $directory);
        }
    }

    /**
     * Delete image from custom folder or storage
     *
     * @param string $path
     * @param string $disk
     * @return void
     */
    protected function deleteImage(string $path, string $disk = 'public'): void
    {
        // Check if it's a custom path (starts with seller_products or products)
        if (str_starts_with($path, 'seller_products/') || str_starts_with($path, 'products/')) {
            // Delete from public directory
            $fullPath = public_path($path);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        } else {
            // Delete from Laravel storage
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * Delete specific product images
     *
     * @param \App\Models\Product $product
     * @param array $imageIds
     * @return void
     */
    protected function deleteProductImages($product, array $imageIds): void
    {
        $imagesToDelete = ProductImage::whereIn('id', $imageIds)
            ->where('product_id', $product->id)
            ->get();

        foreach ($imagesToDelete as $image) {
            // Delete file
            $this->deleteImage($image->image_path);

            // If this was the featured image, clear it
            if ($product->featured_image == $image->image_path) {
                $product->update(['featured_image' => null]);
            }

            // Delete record
            $image->delete();
        }
    }

    /**
     * Get image URL (works for both custom and storage paths)
     *
     * @param string $path
     * @param string $disk
     * @return string
     */
    protected function getImageUrl(string $path, string $disk = 'public'): string
    {
        // Check if it's a custom path
        if (str_starts_with($path, 'seller_products/') || str_starts_with($path, 'products/')) {
            return url($path);
        } else {
            // Laravel storage path
            return url('storage/' . $path);
        }
    }

    /**
     * Transform product images to include proper URLs
     *
     * @param \App\Models\Product $product
     * @return array
     */
    protected function transformProductImages($product): array
    {
        $productData = $product->toArray();

        // Transform additional images
        $productData['images'] = collect($product->images)->map(function($image) {
            return [
                'id' => $image->id,
                'image_path' => $image->image_path,
                'alt_text' => $image->alt_text,
                'sort_order' => $image->sort_order,
                'image_url' => $this->getImageUrl($image->image_path)
            ];
        })->toArray();

        // Transform featured image
        $productData['featured_image_url'] = $product->featured_image ?
            $this->getImageUrl($product->featured_image) : null;

        return $productData;
    }

    /**
     * Validate image upload request
     *
     * @param array $rules
     * @return array
     */
    protected function getImageValidationRules(array $customRules = []): array
    {
        $defaultRules = [
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB max
            'images' => 'nullable|array|max:10', // Maximum 10 images
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // 5MB per image
            'delete_images' => 'sometimes|array',
            'delete_images.*' => 'exists:product_images,id',
        ];

        return array_merge($defaultRules, $customRules);
    }

    /**
     * Handle image uploads for product creation
     *
     * @param \App\Models\Product $product
     * @param \Illuminate\Http\Request $request
     * @param int|null $sellerId
     * @return void
     */
    protected function processProductImages($product, $request, ?int $sellerId = null): void
    {
        // Handle featured image
        if ($request->hasFile('featured_image')) {
            $featuredImagePath = $this->handleFeaturedImage(
                $request->file('featured_image'),
                $sellerId
            );
            $product->update(['featured_image' => $featuredImagePath]);
        }

        // Handle additional images
        if ($request->hasFile('images')) {
            $this->handleAdditionalImages($product, $request->file('images'), $sellerId);
        }
    }

    /**
     * Handle image updates for existing products
     *
     * @param \App\Models\Product $product
     * @param \Illuminate\Http\Request $request
     * @param int|null $sellerId
     * @return string|null
     */
    protected function updateProductImages($product, $request, ?int $sellerId = null): ?string
    {
        $featuredImagePath = $product->featured_image;

        // Handle featured image update
        if ($request->hasFile('featured_image')) {
            // Delete old featured image
            if ($featuredImagePath) {
                $this->deleteImage($featuredImagePath);
            }
            $featuredImagePath = $this->handleFeaturedImage(
                $request->file('featured_image'),
                $sellerId
            );
        }

        // Handle image deletions
        if ($request->has('delete_images') && is_array($request->delete_images)) {
            $this->deleteProductImages($product, $request->delete_images);
        }

        // Handle new additional images
        if ($request->hasFile('images')) {
            $this->handleAdditionalImages($product, $request->file('images'), $sellerId);
        }

        return $featuredImagePath;
    }

    /**
     * Clean up all product images when deleting a product
     *
     * @param \App\Models\Product $product
     * @return void
     */
    protected function cleanupProductImages($product): void
    {
        // Delete featured image
        if ($product->featured_image) {
            $this->deleteImage($product->featured_image);
        }

        // Delete additional images
        foreach ($product->images as $image) {
            $this->deleteImage($image->image_path);
        }
    }
}
