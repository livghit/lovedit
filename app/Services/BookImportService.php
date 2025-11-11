<?php

namespace App\Services;

use App\Models\Book;

class BookImportService
{
    /**
     * Upsert a book from Open Library data
     */
    public function upsertFromOpenLibrary(array $bookData): Book
    {
        // Find existing book by title and author
        $existing = Book::where('title', $bookData['title'])
            ->where('author', $bookData['author'])
            ->first();

        if ($existing) {
            // Update only metadata fields
            $existing->update([
                'description' => $bookData['description'] ?? $existing->description,
                'isbn' => $bookData['isbn'] ?? $existing->isbn,
                'published_year' => $bookData['published_year'] ?? $existing->published_year,
                'publisher' => $bookData['publisher'] ?? $existing->publisher,
                'ol_work_key' => $bookData['ol_work_key'] ?? $existing->ol_work_key,
                'ol_cover_id' => $bookData['ol_cover_id'] ?? $existing->ol_cover_id,
                'external_id' => $bookData['external_id'] ?? $existing->external_id,
                'cover_url' => $bookData['cover_url'] ?? $existing->cover_url,
                'last_synced_at' => now(),
            ]);

            return $existing;
        }

        // Create new book
        return Book::create([
            'title' => $bookData['title'],
            'author' => $bookData['author'],
            'description' => $bookData['description'] ?? null,
            'isbn' => $bookData['isbn'] ?? null,
            'published_year' => $bookData['published_year'] ?? null,
            'publisher' => $bookData['publisher'] ?? null,
            'external_id' => $bookData['external_id'] ?? null,
            'cover_url' => $bookData['cover_url'] ?? null,
            'ol_work_key' => $bookData['ol_work_key'] ?? null,
            'ol_cover_id' => $bookData['ol_cover_id'] ?? null,
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
        // Remove common Wikipedia suffixes
        $title = preg_replace('/\s*[-–—]\s*Wikipedia\s*$/i', '', $title);

        // Remove extra whitespace
        $title = trim(preg_replace('/\s+/', ' ', $title));

        return $title;
    }
}
