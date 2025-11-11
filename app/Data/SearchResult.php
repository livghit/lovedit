<?php

namespace App\Data;

use Illuminate\Support\Collection;

class SearchResult
{
    public function __construct(
        public bool $isLocal,
        public Collection $books,
        public bool $hasOnlineOption,
        public ?string $query = null,
        public int $totalCount = 0,
        public string $source = 'local',
        public ?string $message = null,
    ) {}

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'is_local' => $this->isLocal,
            'books' => $this->books->map(fn ($book) => [
                'id' => $book->id,
                'title' => $book->title,
                'author' => $book->author,
                'description' => $book->description,
                'cover_url' => $book->cover_url,
                'external_id' => $book->external_id,
                'published_year' => $book->published_year,
                'publisher' => $book->publisher,
            ])->all(),
            'has_online_option' => $this->hasOnlineOption,
            'query' => $this->query,
            'total_count' => $this->totalCount,
            'source' => $this->source,
            'message' => $this->message,
        ];
    }

    /**
     * Create a local search result
     */
    public static function local(Collection $books, string $query, ?string $message = null): self
    {
        return new self(
            isLocal: true,
            books: $books,
            hasOnlineOption: $books->isEmpty(),
            query: $query,
            totalCount: $books->count(),
            source: 'local',
            message: $message ?? ($books->isEmpty() ? 'No local results found. Try searching online.' : 'Results from your library'),
        );
    }

    /**
     * Create an online search result
     */
    public static function online(Collection $books, string $query, int $totalCount = 0, ?string $message = null): self
    {
        return new self(
            isLocal: false,
            books: $books,
            hasOnlineOption: false,
            query: $query,
            totalCount: $totalCount > 0 ? $totalCount : $books->count(),
            source: 'online',
            message: $message ?? 'Results from Open Library. These will be saved to your library.',
        );
    }
}
