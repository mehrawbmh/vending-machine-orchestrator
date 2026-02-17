<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:products,name'],
            'stock' => ['required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A product with this name already exists.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        // Return 400 when the product name is already taken
        if (isset($validator->failed()['name']['Unique'])) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'A product with this name already exists.',
                    'errors' => $validator->errors()->toArray(),
                ], 400)
            );
        }

        parent::failedValidation($validator);
    }
}
