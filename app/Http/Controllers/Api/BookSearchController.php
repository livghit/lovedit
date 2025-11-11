<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SearchBooksRequest;
use App\Services\BookSearchService;
use Illuminate\Http\JsonResponse;

class BookSearchController extends Controller
{
    public function __construct(private BookSearchService $searchService) {}

    /**
     * Search for books (hybrid local/online)
     */
    public function search(SearchBooksRequest $request): JsonResponse
    {
        $query = $request->input('q');
        $forceOnline = $request->boolean('online', false);

        $result = $this->searchService->search($query, $forceOnline);

        return response()->json($result->toArray());
    }
}
