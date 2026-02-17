<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChooseProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'machine_id' => ['required', 'integer', 'exists:vending_machines,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'count' => ['required', 'integer', 'min:1'],
            'coins' => ['required', 'integer', 'min:1'],
        ];
    }
}
