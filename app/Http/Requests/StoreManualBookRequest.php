<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualBookRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'cover_url' => ['nullable', 'url', 'max:500'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publish_date' => ['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
