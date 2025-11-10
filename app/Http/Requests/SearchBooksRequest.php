<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['nullable', 'string', 'min:2', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Search query is required.', // Keeping message though query now nullable
            'query.min' => 'Search query must be at least 2 characters.',
        ];
    }
}
