<?php

namespace App\Services;

use App\Models\Book;

class BookImportService
{
    /**
     * Upsert a book from Open Library data
     */
    public function upsertFromOpenLibrary(array $bookData, ?BookSearchService $searchService = null): Book
    {
        // Find existing book by title and author
        $existing = Book::where('title', $bookData['title'])
            ->where('author', $bookData['author'])
            ->first();

        // Fetch additional work details if we have a work key and search service
        $workDetails = [];
        if ($searchService && isset($bookData['ol_work_key'])) {
            $workDetails = $searchService->fetchWorkDetails($bookData['ol_work_key']) ?? [];
        }

        // Merge work details with book data
        $mergedData = array_merge($bookData, $workDetails);

        if ($existing) {
            // Update only metadata fields
            $existing->update([
                'description' => $mergedData['description'] ?? $existing->description,
                'isbn' => $mergedData['isbn'] ?? $existing->isbn,
                'published_year' => $mergedData['published_year'] ?? $existing->published_year,
                'first_publish_date' => $mergedData['first_publish_date'] ?? $existing->first_publish_date,
                'publisher' => $mergedData['publisher'] ?? $existing->publisher,
                'ol_work_key' => $mergedData['ol_work_key'] ?? $existing->ol_work_key,
                'ol_cover_id' => $mergedData['ol_cover_id'] ?? $existing->ol_cover_id,
                'external_id' => $mergedData['external_id'] ?? $existing->external_id,
                'cover_url' => $mergedData['cover_url'] ?? $existing->cover_url,
                'subtitle' => $mergedData['subtitle'] ?? $existing->subtitle,
                'subjects' => $mergedData['subjects'] ?? $existing->subjects,
                'excerpt' => $mergedData['excerpt'] ?? $existing->excerpt,
                'links' => $mergedData['links'] ?? $existing->links,
                'number_of_pages' => $mergedData['number_of_pages'] ?? $existing->number_of_pages,
                'languages' => $mergedData['languages'] ?? $existing->languages,
                'edition_count' => $mergedData['edition_count'] ?? $existing->edition_count,
                'ratings_average' => $mergedData['ratings_average'] ?? $existing->ratings_average,
                'ratings_count' => $mergedData['ratings_count'] ?? $existing->ratings_count,
                'last_synced_at' => now(),
            ]);

            return $existing;
        }

        // Create new book
        return Book::create([
            'title' => $mergedData['title'],
            'subtitle' => $mergedData['subtitle'] ?? null,
            'author' => $mergedData['author'],
            'description' => $mergedData['description'] ?? null,
            'isbn' => $mergedData['isbn'] ?? null,
            'published_year' => $mergedData['published_year'] ?? null,
            'first_publish_date' => $mergedData['first_publish_date'] ?? null,
            'publisher' => $mergedData['publisher'] ?? null,
            'external_id' => $mergedData['external_id'] ?? null,
            'cover_url' => $mergedData['cover_url'] ?? null,
            'ol_work_key' => $mergedData['ol_work_key'] ?? null,
            'ol_cover_id' => $mergedData['ol_cover_id'] ?? null,
            'subjects' => $mergedData['subjects'] ?? null,
            'excerpt' => $mergedData['excerpt'] ?? null,
            'links' => $mergedData['links'] ?? null,
            'number_of_pages' => $mergedData['number_of_pages'] ?? null,
            'languages' => $mergedData['languages'] ?? null,
            'edition_count' => $mergedData['edition_count'] ?? 1,
            'ratings_average' => $mergedData['ratings_average'] ?? null,
            'ratings_count' => $mergedData['ratings_count'] ?? 0,
            'cover_stored_locally' => false,
            'discovered_via_search' => false,
            'first_discovered_at' => now(),
            'last_synced_at' => now(),
            'is_user_created' => false,
            'search_count' => 0,
        ]);
    }

    /**
     * Format Open Library data to our schema
     */
    public function formatOpenLibraryData(array $data): array
    {
        return [
            'title' => $this->deduplicateTitle($data['title'] ?? ''),
            'author' => $this->extractAuthor($data),
            'description' => $data['description'] ?? null,
            'published_year' => $data['first_publish_year'] ?? null,
            'isbn' => $data['isbn'][0] ?? null,
            'publisher' => $data['publisher'][0] ?? null,
            'external_id' => $data['key'] ?? null,
            'cover_url' => isset($data['cover_i']) ? "https://covers.openlibrary.org/b/id/{$data['cover_i']}-M.jpg" : null,
            'ol_cover_id' => $data['cover_i'] ?? null,
            'ol_work_key' => $data['key'] ?? null,
            'edition_count' => $data['edition_count'] ?? 1,
            'languages' => $data['language'] ?? null,
            'number_of_pages' => $data['number_of_pages_median'] ?? null,
            'ratings_average' => $data['ratings_average'] ?? null,
            'ratings_count' => $data['ratings_count'] ?? 0,
        ];
    }

    /**
     * Extract the primary author from Open Library data
     */
    public function extractAuthor(array $data): string
    {
        if (isset($data['author_name']) && is_array($data['author_name']) && count($data['author_name']) > 0) {
            return $data['author_name'][0];
        }

        if (isset($data['authors']) && is_array($data['authors']) && count($data['authors']) > 0) {
            return $data['authors'][0]['name'] ?? '';
        }

        return '';
    }

    /**
     * Deduplicate and clean titles
     */
    public function deduplicateTitle(string $title): string
    {
        // Remove common Wikipedia suffixes with various dash types
        // Matches: — (em dash), – (en dash), - (hyphen)
        $title = preg_replace('/\s*(?:—|–|-)\s*Wikipedia\s*$/i', '', $title);

        // Remove extra whitespace
        $title = trim(preg_replace('/\s+/', ' ', $title));

        return $title;
    }
}
