<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreToReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required_without:title', 'nullable', 'integer', 'exists:books,id'],
            // Allow optional book data for creating books from online search
            'title' => ['required_without:book_id', 'nullable', 'string', 'max:255'],
            'author' => ['required_with:title', 'nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:20'],
            'cover_url' => ['required_with:title', 'nullable', 'url', 'max:500'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'ol_work_key' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publish_date' => ['nullable', 'integer'],
            'published_year' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.required_without' => 'A book must be selected or book details provided.',
            'book_id.exists' => 'The selected book does not exist.',
            'title.required_without' => 'A book title is required when not selecting an existing book.',
            'author.required_with' => 'Author is required when adding a book.',
            'cover_url.required_with' => 'Cover URL is required when adding a book.',
        ];
    }
}
