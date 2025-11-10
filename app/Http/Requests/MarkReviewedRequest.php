<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkReviewedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'content' => ['required', 'string', 'min:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'A rating is required.',
            'rating.min' => 'Rating must be at least 1 star.',
            'rating.max' => 'Rating cannot exceed 5 stars.',
            'content.required' => 'Review content is required.',
            'content.min' => 'Review must be at least 10 characters.',
        ];
    }
}
