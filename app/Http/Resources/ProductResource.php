<?php


namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'category_id' => $this->category_id,
            'seller_id' => $this->seller_id,
            'sku' => $this->sku,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'stock_status' => $this->stock_status,
            'approval_status' => $this->approval_status,
            'commission_rate' => (float) $this->commission_rate,
            'is_active' => (bool) $this->is_active,
            'view_count' => $this->view_count,
            'wishlist_count' => $this->wishlist_count,
            'purchase_count' => $this->purchase_count,
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'weight' => $this->weight ? (float) $this->weight : null,
            'brand' => $this->brand,
            'model' => $this->model,
            'meta_data' => $this->meta_data,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'seller' => $this->whenLoaded('seller', function () {
                return [
                    'id' => $this->seller->id,
                    'name' => $this->seller->name,
                    'store_name' => $this->seller->store_name,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
