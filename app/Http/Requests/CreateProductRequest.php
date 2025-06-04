<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $productId = $this->route('product') ? $this->route('product') : null;

        return [
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
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'name.max' => 'Product name cannot exceed 255 characters',
            'description.required' => 'Product description is required',
            'description.max' => 'Description cannot exceed 5000 characters',
            'category_id.required' => 'Please select a category',
            'category_id.exists' => 'Selected category is invalid',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a valid number',
            'price.min' => 'Price must be greater than 0',
            'price.max' => 'Price cannot exceed 999,999.99',
            'stock.required' => 'Stock amount is required',
            'stock.integer' => 'Stock must be a whole number',
            'stock.min' => 'Stock cannot be negative',
            'unit.required' => 'Please select a unit',
            'unit.in' => 'Selected unit is invalid',
            'currency.required' => 'Please select a currency',
            'currency.in' => 'Selected currency is invalid',
            'weight.numeric' => 'Weight must be a valid number',
            'weight.min' => 'Weight cannot be negative',
            'brand.max' => 'Brand name cannot exceed 100 characters',
            'model.max' => 'Model name cannot exceed 100 characters',
        ];
    }
}
