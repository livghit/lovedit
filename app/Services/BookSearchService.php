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
        $books = Book::where(function ($q) use ($query) {
            $q->where('title', 'LIKE', "%{$query}%")
                ->orWhere('author', 'LIKE', "%{$query}%");
        })
            ->whereNotNull('cover_url')
            ->where('cover_url', '!=', '')
            ->limit(20)
            ->get();

        // Increment search count for found books
        $books->each(fn ($book) => $book->incrementSearchCount());

        return SearchResult::local($books, $query);
    }

    /**
     * Search Open Library API and return results (without saving to database)
     */
    public function searchOnline(string $query): SearchResult
    {
        $cacheKey = $this->getCacheKey('search', $query);

        // Cache the API response
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

                // Filter out books without covers
                $rawFiltered = array_filter($raw, fn ($book) => isset($book['cover_i']) && $book['cover_i']);

                return [
                    'raw' => $rawFiltered,
                    'formatted' => $this->formatSearchResults(['docs' => $rawFiltered]),
                ];
            } catch (\Exception $e) {
                \Log::error('Book search failed: '.$e->getMessage());

                return ['raw' => [], 'formatted' => []];
            }
        });

        // Convert formatted results to collection of books (not saved to DB yet)
        $books = collect($cached['formatted'])->map(function ($bookData) {
            // Create a Book model instance without saving it
            $book = new Book($bookData);
            $book->id = $bookData['external_id'] ?? null; // Use external_id as temporary ID for display

            return $book;
        });

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
                // Skip books without covers
                if (! isset($result['cover_i']) || ! $result['cover_i']) {
                    continue;
                }

                $bookData = $this->importService->formatOpenLibraryData($result);

                // Import book WITHOUT fetching work details (much faster)
                $book = $this->importService->upsertFromOpenLibrary($bookData, null);

                // Download cover locally in background (optional, can be commented out for even faster imports)
                if ($result['cover_i'] ?? null) {
                    $this->downloadCoverLocally($result['cover_i'], $result['cover_url'] ?? null);
                }

                $book->markAsDiscoveredOnline();
                $book->update(['sync_batch_id' => $batch->id]);

                // Dispatch background job to fetch work details (description, subjects, etc.)
                \App\Jobs\FetchBookWorkDetails::dispatch($book);

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
     * Fetch detailed work information from OpenLibrary Works API
     *
     * @param  string  $workKey  OpenLibrary work key (e.g., /works/OL2010879W)
     * @return array|null Work details including description, subjects, etc.
     */
    public function fetchWorkDetails(string $workKey): ?array
    {
        $cacheKey = $this->getCacheKey('work_details', $workKey);

        return Cache::remember($cacheKey, self::CACHE_DURATION_DETAILS, function () use ($workKey) {
            // Check rate limit
            if (! $this->checkRateLimit()) {
                \Log::warning('Work details rate limit exceeded', ['workKey' => $workKey]);

                return null;
            }

            try {
                // Ensure workKey starts with /works/
                $workKey = str_starts_with($workKey, '/works/') ? $workKey : "/works/{$workKey}";

                $url = "https://openlibrary.org{$workKey}.json";
                $response = Http::timeout(10)->get($url);

                if ($response->failed()) {
                    \Log::warning('Failed to fetch work details', ['workKey' => $workKey, 'status' => $response->status()]);

                    return null;
                }

                $data = $response->json();

                return $this->formatWorkDetails($data);
            } catch (\Exception $e) {
                \Log::error('Fetch work details failed: '.$e->getMessage(), ['workKey' => $workKey]);

                return null;
            }
        });
    }

    /**
     * Format work details from OpenLibrary Works API
     */
    protected function formatWorkDetails(array $data): array
    {
        $formatted = [];

        // Extract description (can be string or object with 'value' key)
        if (isset($data['description'])) {
            $formatted['description'] = is_string($data['description'])
                ? $data['description']
                : ($data['description']['value'] ?? null);
        }

        // Extract subjects
        if (isset($data['subjects']) && is_array($data['subjects'])) {
            $formatted['subjects'] = $data['subjects'];
        }

        // Extract subtitle
        if (isset($data['subtitle'])) {
            $formatted['subtitle'] = $data['subtitle'];
        }

        // Extract first publish date
        if (isset($data['first_publish_date'])) {
            $formatted['first_publish_date'] = $data['first_publish_date'];
        }

        // Extract excerpts (take first one if available)
        if (isset($data['excerpts']) && is_array($data['excerpts']) && count($data['excerpts']) > 0) {
            $excerpt = $data['excerpts'][0];
            $formatted['excerpt'] = is_string($excerpt) ? $excerpt : ($excerpt['excerpt'] ?? $excerpt['text'] ?? null);
        }

        // Extract links
        if (isset($data['links']) && is_array($data['links'])) {
            $formatted['links'] = array_map(function ($link) {
                return [
                    'title' => $link['title'] ?? null,
                    'url' => $link['url'] ?? null,
                ];
            }, $data['links']);
        }

        return $formatted;
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
