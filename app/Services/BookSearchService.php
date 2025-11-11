<?php

namespace App\Services;

use App\Data\SearchResult;
use App\Models\Book;
use App\Models\BookCacheMetadata;
use App\Models\SyncBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class BookSearchService
{
    protected const CACHE_DURATION_SEARCH = 60 * 60 * 72; // 72 hours for search results

    protected const CACHE_DURATION_DETAILS = 60 * 60 * 24 * 30; // 30 days for book details

    protected const CACHE_DURATION_COVERS = 60 * 60 * 24 * 60; // 60 days for covers

    public function __construct(private BookImportService $importService) {}

    /**
     * Hybrid search: Try local first, optionally search online
     */
    public function search(string $query, bool $forceOnline = false): SearchResult
    {
        if ($forceOnline) {
            return $this->searchOnline($query);
        }

        $localResults = $this->searchLocal($query);

        if ($localResults->books->isNotEmpty() || $localResults->hasOnlineOption === false) {
            return $localResults;
        }

        return $localResults;
    }

    /**
     * Search local database for books
     */
    public function searchLocal(string $query): SearchResult
    {
        $books = Book::where('title', 'LIKE', "%{$query}%")
            ->orWhere('author', 'LIKE', "%{$query}%")
            ->limit(20)
            ->get();

        // Increment search count for found books
        $books->each(fn ($book) => $book->incrementSearchCount());

        return SearchResult::local($books, $query);
    }

    /**
     * Search Open Library API and save results
     */
    public function searchOnline(string $query): SearchResult
    {
        $cacheKey = $this->getCacheKey('search', $query);
        $isFromCache = Cache::has($cacheKey);

        // Store both formatted and raw results
        $cached = Cache::remember($cacheKey, self::CACHE_DURATION_SEARCH, function () use ($query) {
            // Check rate limit
            if (! $this->checkRateLimit()) {
                \Log::warning('Book search rate limit exceeded', ['query' => $query]);

                return ['raw' => [], 'formatted' => []];
            }

            try {
                $response = Http::timeout(10)->get(config('services.openlibrary.api_url'), [
                    'q' => $query,
                    'limit' => 20,
                ]);

                if ($response->failed()) {
                    return ['raw' => [], 'formatted' => []];
                }

                $data = $response->json();
                $raw = $data['docs'] ?? [];

                return [
                    'raw' => $raw,
                    'formatted' => $this->formatSearchResults($data),
                ];
            } catch (\Exception $e) {
                \Log::error('Book search failed: '.$e->getMessage());

                return ['raw' => [], 'formatted' => []];
            }
        });

        // Save results to database only on fresh API calls (not from cache)
        if (! $isFromCache && ! empty($cached['raw'])) {
            $this->saveSearchResults(collect($cached['raw']));
        }

        // Fetch the saved books from DB
        $books = Book::where('title', 'LIKE', "%{$query}%")
            ->orWhere('author', 'LIKE', "%{$query}%")
            ->limit(20)
            ->get();

        return SearchResult::online($books, $query, count($cached['formatted']));
    }

    /**
     * Save search results to database
     */
    public function saveSearchResults(Collection $apiResults): void
    {
        $batch = SyncBatch::create([
            'type' => 'manual_search',
            'status' => 'running',
            'books_count' => 0,
            'metadata' => [],
        ]);

        $savedCount = 0;

        foreach ($apiResults as $result) {
            try {
                $bookData = $this->importService->formatOpenLibraryData($result);
                $book = $this->importService->upsertFromOpenLibrary($bookData);

                if ($result['cover_i'] ?? null) {
                    $this->downloadCoverLocally($result['cover_i'], $result['cover_url'] ?? null);
                }

                $book->markAsDiscoveredOnline();
                $book->update(['sync_batch_id' => $batch->id]);

                $savedCount++;
            } catch (\Exception $e) {
                \Log::error('Failed to save book from search', [
                    'result' => $result,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $batch->update([
            'status' => 'completed',
            'books_count' => $savedCount,
        ]);
    }

    /**
     * Download and store cover locally
     */
    public function downloadCoverLocally(string $coverId, ?string $fallbackUrl = null): ?string
    {
        try {
            $filename = "{$coverId}.jpg";

            // Skip if file already exists
            if (Storage::disk('local')->exists("covers/{$filename}")) {
                return "covers/{$filename}";
            }

            $url = config('services.openlibrary.covers_url')."/{$coverId}-M.jpg";
            $response = Http::timeout(10)->get($url);

            if (! $response->successful()) {
                return null;
            }

            Storage::disk('local')->put("covers/{$filename}", $response->body());

            return "covers/{$filename}";
        } catch (\Exception $e) {
            \Log::error('Failed to download cover', [
                'cover_id' => $coverId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get cover path (local or fallback to URL)
     */
    public function getCoverPath(Book $book): ?string
    {
        if ($book->cover_stored_locally && $book->external_id) {
            $localPath = storage_path("app/covers/{$book->external_id}.jpg");

            if (file_exists($localPath)) {
                return "/covers/{$book->external_id}";
            }
        }

        return $book->cover_url;
    }

    /**
     * Search for books from Open Library API (legacy method)
     *
     * @param  string  $query  Search query (title, author, ISBN)
     * @param  string|null  $author  Optional author filter
     * @return array Array of book data
     */
    public function searchLegacy(string $query, ?string $author = null): array
    {
        $cacheKey = $this->getCacheKey('search', $query, $author);

        return Cache::remember($cacheKey, self::CACHE_DURATION_SEARCH, function () use ($query, $author) {
            // Check rate limit
            if (! $this->checkRateLimit()) {
                \Log::warning('Book search rate limit exceeded', ['query' => $query, 'author' => $author]);

                return [];
            }

            try {
                $params = [
                    'q' => $query,
                    'limit' => 20,
                ];

                if ($author) {
                    $params['author'] = $author;
                }

                $response = Http::timeout(10)->get(config('services.openlibrary.api_url'), $params);

                if ($response->failed()) {
                    return [];
                }

                return $this->formatSearchResults($response->json());
            } catch (\Exception $e) {
                \Log::error('Book search failed: '.$e->getMessage());

                return [];
            }
        });
    }

    /**
     * Get book details by external ID (Open Library ID)
     *
     * @param  string  $externalId  Open Library work/edition ID
     */
    public function getFromExternalId(string $externalId): ?array
    {
        $cacheKey = $this->getCacheKey('external', $externalId);

        return Cache::remember($cacheKey, self::CACHE_DURATION_DETAILS, function () use ($externalId) {
            // Check rate limit
            if (! $this->checkRateLimit()) {
                \Log::warning('Book details rate limit exceeded', ['externalId' => $externalId]);

                return null;
            }

            try {
                $url = "https://openlibrary.org{$externalId}.json";
                $response = Http::timeout(10)->get($url);

                if ($response->failed()) {
                    return null;
                }

                return $this->formatExternalResult($response->json());
            } catch (\Exception $e) {
                \Log::error('Get external book failed: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * Batch fetch book metadata for multiple external IDs
     *
     * @param  array  $externalIds  Array of Open Library IDs
     * @return array Array of book data keyed by external ID
     */
    public function batchGetFromExternalIds(array $externalIds): array
    {
        $results = [];
        $uncachedIds = [];

        // Check cache first
        foreach ($externalIds as $id) {
            $cacheKey = $this->getCacheKey('external', $id);
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                $results[$id] = $cached;
            } else {
                $uncachedIds[] = $id;
            }
        }

        // Fetch uncached items with rate limiting
        foreach ($uncachedIds as $id) {
            if (! $this->checkRateLimit()) {
                \Log::warning('Batch fetch rate limit exceeded');
                break;
            }

            $bookData = $this->getFromExternalId($id);
            if ($bookData) {
                $results[$id] = $bookData;
            }
        }

        return $results;
    }

    /**
     * Format search results from Open Library
     */
    protected function formatSearchResults(array $data): array
    {
        $books = [];

        foreach ($data['docs'] ?? [] as $doc) {
            $book = [
                'title' => $doc['title'] ?? '',
                'author' => $this->extractAuthor($doc),
                'external_id' => $doc['key'] ?? null,
                'isbn' => $doc['isbn'][0] ?? null,
                'published_year' => $doc['first_publish_year'] ?? null,
                'description' => $doc['description'] ?? '',
                'publisher' => $doc['publisher'][0] ?? null,
                'cover_i' => $doc['cover_i'] ?? null,
            ];

            // Add cover URL if available
            if (isset($doc['cover_i'])) {
                $book['cover_url'] = "https://covers.openlibrary.org/b/id/{$doc['cover_i']}-M.jpg";
            }

            $books[] = $book;
        }

        return $books;
    }

    /**
     * Format external book result from Open Library
     */
    protected function formatExternalResult(array $data): array
    {
        $book = [
            'title' => $data['title'] ?? '',
            'author' => '',
            'external_id' => $data['key'] ?? null,
            'published_year' => null,
            'description' => $data['description']['value'] ?? $data['description'] ?? '',
        ];

        // Extract author
        if (isset($data['authors']) && count($data['authors']) > 0) {
            $book['author'] = $data['authors'][0]['name'] ?? '';
        }

        // Extract published year
        if (isset($data['publish_date'])) {
            preg_match('/\d{4}/', $data['publish_date'], $matches);
            if ($matches) {
                $book['published_year'] = (int) $matches[0];
            }
        }

        // Extract ISBN
        if (isset($data['identifiers']['isbn_10'])) {
            $book['isbn'] = $data['identifiers']['isbn_10'][0] ?? null;
        }

        return $book;
    }

    /**
     * Extract author from search result document
     */
    protected function extractAuthor(array $doc): string
    {
        if (isset($doc['author_name']) && count($doc['author_name']) > 0) {
            return $doc['author_name'][0];
        }

        return '';
    }

    /**
     * Generate cache key
     */
    protected function getCacheKey(string $type, ?string $query = null, ?string $author = null): string
    {
        $key = $type;
        if ($query) {
            $key .= ':'.$query;
        }
        if ($author) {
            $key .= ':'.$author;
        }

        return 'books:'.hash('sha256', $key);
    }

    /**
     * Check rate limit for API calls
     */
    protected function checkRateLimit(): bool
    {
        $key = 'ol_api_rate_limit';
        $limit = RateLimiter::attempt($key, config('services.openlibrary.rate_limit'), function () {
            return true;
        }, 60);

        return $limit;
    }

    /**
     * Get the number of API calls used this minute
     */
    public function getRateLimitUsage(): int
    {
        $key = 'ol_api_rate_limit';

        return RateLimiter::attempts($key);
    }

    /**
     * Record cache metadata for tracking purposes
     */
    protected function recordCacheMetadata(int $bookId, string $cacheKey, int $expiresInSeconds): void
    {
        BookCacheMetadata::updateOrCreate(
            ['book_id' => $bookId, 'cache_key' => $cacheKey],
            [
                'expires_at' => now()->addSeconds($expiresInSeconds),
                'hit_count' => \DB::raw('hit_count + 1'),
            ]
        );
    }
}
