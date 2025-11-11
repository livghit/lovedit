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
        // API endpoint (/api/books/search) requires q parameter
        // Web endpoint (/books/search) makes q optional
        $isApiRequest = $this->routeIs('api.books.search');

        return [
            'q' => $isApiRequest ? ['required', 'string', 'min:2', 'max:255'] : ['nullable', 'string', 'min:2', 'max:255'],
            'query' => ['nullable', 'string', 'min:2', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'online' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Search query is required.',
            'q.min' => 'Search query must be at least 2 characters.',
            'query.min' => 'Search query must be at least 2 characters.',
        ];
    }
}
