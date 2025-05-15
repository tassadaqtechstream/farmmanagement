<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'image' => $this->image_url,

        ];

        // Include subcategories if they've been loaded
        if ($this->relationLoaded('subcategories')) {
            $data['subcategories'] = CategoryResource::collection($this->subcategories);
        }

        // Include parent if it's been loaded
        if ($this->relationLoaded('parent')) {
            $data['parent'] = new CategoryResource($this->parent);
        }

        return $data;
    }
}
