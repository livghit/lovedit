# Hybrid Search Implementation Plan: Local-First with Online Fallback

## Overview

Replace the current real-time Open Library API dependency with a **local-first search strategy** that populates the database organically as users search online. This provides instant local searches while maintaining a growing library of cached books.

---

## Phase 1: Database & Model Updates

### 1.1 Database Migration

**File**: `database/migrations/2025_11_12_XXXXXX_update_books_for_hybrid_search.php`

**New columns on `books` table**:

- `ol_work_key` (string, nullable) - Open Library work identifier (e.g., "OL45883W")
- `ol_cover_id` (integer, nullable) - Cover image ID from Open Library
- `cover_stored_locally` (boolean, default: false) - Indicates if cover is in `/storage/app/covers/`
- `discovered_via_search` (boolean, default: false) - true if added via user search
- `first_discovered_at` (timestamp, nullable) - When first added to database
- `last_synced_at` (timestamp, nullable) - Last time metadata was updated
- `is_user_created` (boolean, default: false) - true if user manually created it
- `search_count` (integer, default: 0) - Track how often this book is searched

**Indexes**:

- Index on `(title, author)` - For fast local search deduplication
- Index on `discovered_via_search` - For cleanup queries
- Index on `search_count` - For popularity tracking

### 1.2 Book Model Updates

**File**: `app/Models/Book.php`

**Changes**:

- Add new fillable fields: `ol_work_key`, `ol_cover_id`, `cover_stored_locally`, `discovered_via_search`, `first_discovered_at`, `last_synced_at`, `is_user_created`, `search_count`
- Add casts for boolean/timestamp fields
- Add method: `markAsDiscoveredOnline()` - Sets flags when found via online search
- Add method: `hasCoverStored()` - Returns if cover is locally stored
- Add scope: `discoveredViaSearch()` - Query only books found via searches
- Add scope: `userCreated()` - Query only manually created books
- Add scope: `getPopular()` - Order by search_count

### 1.3 Create SyncBatch Model

**File**: `app/Models/SyncBatch.php`

**Purpose**: Track which books were added in which sync batches

**Columns**:

- `id` (primary key)
- `type` (enum: 'manual_search', 'monthly_popular', 'system')
- `status` (enum: 'pending', 'running', 'completed', 'failed')
- `books_count` (integer) - How many books added in this batch
- `batch_date` (timestamp) - When batch ran
- `metadata` (json) - Additional info (query, filters, etc.)
- `created_at`, `updated_at`

**Relation**: `hasMany(Book)` - Link books to their discovery batch

---

## Phase 2: Search Service Architecture

### 2.1 Create SearchResult DTO

**File**: `app/Data/SearchResult.php`

**Purpose**: Standardize search response format

**Properties**:

```php
public bool $isLocal;           // true = from DB, false = from API
public Collection $books;       // Array of book results
public bool $hasOnlineOption;  // Show "Find Online" button?
public ?string $query;         // Original search query
public int $totalCount;        // Total results available
public string $source;         // 'local' | 'online'
public ?string $message;       // User-facing message
```

### 2.2 Refactor BookSearchService

**File**: `app/Services/BookSearchService.php`

**New Methods**:

- `searchLocal(string $query): SearchResult`
    - Full-text search on books table (title, author)
    - Returns instant results
    - Increments `search_count` on found books

- `searchOnline(string $query): SearchResult`
    - Query Open Library API
    - Auto-save results to DB via `saveSearchResults()`
    - Download covers locally
    - Return results + store in DB

- `search(string $query, bool $forceOnline = false): SearchResult`
    - Main entry point
    - If `forceOnline` = false: Try local first
    - If local empty && !forceOnline: Return with `hasOnlineOption = true`
    - If `forceOnline` = true: Query API directly

- `saveSearchResults(Collection $apiResults): void`
    - For each result:
        - Check if book exists by `(title, author)` match
        - If new: Create with `discovered_via_search = true`
        - If exists: Update metadata only
        - Download and store cover locally
        - Create SyncBatch record

- `downloadCoverLocally(string $externalId, string $coverUrl): ?string`
    - Download cover from Open Library
    - Save to `/storage/app/covers/{external_id}.jpg`
    - Return relative path
    - Set `cover_stored_locally = true`

- `getCoverPath(Book $book): ?string`
    - Return local path if available
    - Fall back to `cover_url` if not
    - Return null if neither exists

**Rate Limiting**:

- Keep existing 30 calls/min limit
- Add cache key: `open_library:search:{query_hash}` (24hr TTL)
- Prevent duplicate simultaneous requests

### 2.3 Create BookImportService

**File**: `app/Services/BookImportService.php`

**Purpose**: Handle book creation/updating with proper validation

**Methods**:

- `upsertFromOpenLibrary(array $bookData): Book`
    - Validate OL data format
    - Check for duplicates via title+author
    - Create or update book record
    - Handle conflicts gracefully
    - Return Book model

- `formatOpenLibraryData(array $data): array`
    - Map OL API fields to our schema
    - Extract: title, author, description, publish_year, cover_url, external_id, ol_work_key
    - Sanitize text fields
    - Return standardized array

- `deduplicateTitle(string $title): string`
    - Remove " - Wikipedia" or similar suffixes
    - Normalize spacing
    - Return clean title

---

## Phase 3: Frontend Changes

### 3.1 Update BookSearch Page

**File**: `resources/js/pages/books/search.tsx`

**New Features**:

- State: `searchMode` (enum: 'local', 'online')
- State: `onlineSearching` (boolean) - Show spinner
- Display: "Local results" vs "Online results" badges
- Button: "Find Online" - Visible only when local returns 0 results
- Button: "Load More" - Pagination for online results
- Loading states and error handling

**Flow**:

1. User types and submits search
2. Component calls `/api/books/search?q={query}&local=true` (default)
3. Display results instantly with "Local" badge
4. If 0 results: Show "Find Online" button
5. User clicks "Find Online"
6. Component calls `/api/books/search?q={query}&online=true`
7. Show spinner while loading
8. Display results with "Online" badge
9. Message: "These results will be saved to your library"

### 3.2 Update BookController Routes

**File**: `routes/web.php` or `routes/api.php`

**New API Endpoint**:

```
GET /api/books/search
  ?q={query}
  &online={true|false}  (default: false)
  &page={page}          (default: 1)
  &limit={limit}        (default: 20)

Returns: SearchResult DTO as JSON
```

---

## Phase 4: Backend API Implementation

### 4.1 Create BookSearchController

**File**: `app/Http/Controllers/Api/BookSearchController.php`

**Action**: `search(SearchBooksRequest $request)`

```php
$query = $request->input('q');
$forceOnline = $request->boolean('online', false);

$result = $this->bookSearchService->search($query, $forceOnline);

// Convert to JSON response
return response()->json($result->toArray());
```

### 4.2 Update SearchBooksRequest

**File**: `app/Http/Requests/SearchBooksRequest.php`

**Validations**:

- `q` - required, min:2, max:255
- `online` - optional, boolean
- `page` - optional, integer, min:1
- `limit` - optional, integer, min:1, max:100

---

## Phase 5: Storage & Cover Management

### 5.1 Directory Structure

```
storage/
  app/
    covers/
      {external_id}.jpg    # e.g., OL1234567M.jpg
      {external_id}.png    # Alternative format if needed
      index.html           # Prevent directory listing
```

### 5.2 Update Filesystem Config

**File**: `config/filesystems.php`

**New disk** (if needed):

```php
'covers' => [
    'driver' => 'local',
    'root' => storage_path('app/covers'),
    'url' => '/storage/covers',
    'visibility' => 'public',
]
```

### 5.3 Serve Covers Via Route

**File**: `routes/web.php`

**Add Route**:

```php
Route::get('/covers/{externalId}', [CoverController::class, 'show'])
    ->name('covers.show');
```

**Create CoverController**:

```php
// app/Http/Controllers/CoverController.php
public function show(string $externalId): Response|RedirectResponse
{
    $path = storage_path("app/covers/{$externalId}.jpg");

    if (!file_exists($path)) {
        // Redirect to Open Library CDN as fallback
        return redirect("https://covers.openlibrary.org/b/id/{$externalId}-M.jpg");
    }

    return response()->file($path);
}
```

---

## Phase 6: Optional Monthly Sync (Enhancement)

### 6.1 Create PopularBooksSync Job

**File**: `app/Console/Commands/SyncPopularSubjects.php`

**Purpose**: Monthly top-up of popular books (optional, non-critical)

**Logic**:

- Fetch from subjects: Fiction, Science Fiction, Fantasy, Mystery, Romance, Biography, etc.
- Top 100 books per subject, sorted by rating
- Save to DB with `type = 'monthly_popular'` in SyncBatch
- Runs once monthly

**Command**:

```bash
php artisan books:sync-popular-subjects --subjects=5
```

---

## Phase 7: Data Cleanup & Maintenance

### 7.1 Create Book Cleanup Command

**File**: `app/Console/Commands/CleanupOldBooks.php`

**Purpose**: Remove stale cached books after 6 months without searches

**Logic**:

- Find books with `discovered_via_search = true`
- Not searched in 6 months (`search_count = 0` and `first_discovered_at < 6 months`)
- Delete covers from storage
- Delete book records
- Log deleted count

**Command**:

```bash
php artisan books:cleanup-old --days=180 --dry-run
```

### 7.2 Create Cover Cleanup Command

**File**: `app/Console/Commands/CleanupOrphanCovers.php`

**Purpose**: Remove orphaned cover files

**Logic**:

- Scan `/storage/app/covers/` directory
- Find files where `external_id` not in books table
- Delete orphaned files
- Log deleted count

---

## Phase 8: Testing

### 8.1 Unit Tests

**File**: `tests/Unit/Services/BookSearchServiceTest.php`

**Tests**:

- `test_searchLocal_returns_results_from_database`
- `test_searchLocal_returns_empty_when_no_matches`
- `test_searchOnline_saves_results_to_database`
- `test_searchOnline_downloads_covers_locally`
- `test_search_tries_local_first`
- `test_search_returns_online_option_when_local_empty`
- `test_search_respects_rate_limiting`
- `test_getCoverPath_returns_local_path_if_exists`
- `test_getCoverPath_falls_back_to_url`

### 8.2 Feature Tests

**File**: `tests/Feature/BookSearchControllerTest.php`

**Tests**:

- `test_search_endpoint_returns_local_results`
- `test_search_endpoint_with_online_flag_queries_api`
- `test_search_endpoint_auto_saves_online_results`
- `test_search_endpoint_respects_pagination`
- `test_search_endpoint_validates_query_length`
- `test_covers_are_downloaded_and_stored_locally`

### 8.3 Integration Tests

**File**: `tests/Feature/HybridSearchIntegrationTest.php`

**Tests**:

- `test_full_search_flow_local_empty_then_online`
- `test_books_persist_after_online_search`
- `test_subsequent_search_finds_previously_searched_book`
- `test_cover_path_updates_after_online_search`

---

## Phase 9: Configuration & Environment

### 9.1 Update .env

```env
BOOK_COVER_STORAGE=local      # Where covers are stored (local|s3|etc)
BOOK_SEARCH_CACHE_TTL=86400   # 24 hours
BOOK_CLEANUP_DAYS=180         # Cleanup books not searched in 180 days
OPEN_LIBRARY_RATE_LIMIT=30    # Calls per minute
```

### 9.2 Update config/services.php

```php
'open_library' => [
    'base_url' => 'https://openlibrary.org',
    'rate_limit' => env('OPEN_LIBRARY_RATE_LIMIT', 30),
    'search_cache_ttl' => env('BOOK_SEARCH_CACHE_TTL', 86400),
],
```

---

## Phase 10: Migration Path

### 10.1 Decommission Old System

**What to remove**:

- `BookCacheManager` service - No longer needed (covers handled locally)
- `SyncBookMetadata` command - Replaced by optional monthly sync
- Old cache tables if not used elsewhere

**Keep**:

- `BookCacheMetadata` model/table - Still useful for review analytics
- Rate limiting logic from `BookSearchService`

### 10.2 Data Migration

- Existing books get: `is_user_created = true` (they were manually added)
- No action needed for existing covers

---

## Implementation Order (Sequential)

1. ✅ **Database Migration** (Phase 1.1)
2. ✅ **Update Models** (Phase 1.2, 1.3)
3. ✅ **Create DTOs** (Phase 2.1)
4. ✅ **Refactor BookSearchService** (Phase 2.2)
5. ✅ **Create BookImportService** (Phase 2.3)
6. ✅ **Update Frontend** (Phase 3.1, 3.2)
7. ✅ **Create Controller & Routes** (Phase 4.1, 4.2)
8. ✅ **Storage & Cover Setup** (Phase 5)
9. ✅ **Tests** (Phase 8 - Run while building)
10. ✅ **Optional: Monthly Sync** (Phase 6)
11. ✅ **Optional: Cleanup Commands** (Phase 7)
12. ✅ **Configuration** (Phase 9)
13. ✅ **Migration Path** (Phase 10)

---

## Key Decision Points

### Decision 1: Cover Storage

**Options**:

- A) Store locally in `/storage/app/covers/`
- B) Use S3 bucket
- C) Use Open Library CDN directly

**Recommendation**: **Option A** (local storage) - Faster, cheaper, more control

### Decision 2: Local Search Strategy

**Options**:

- A) Simple LIKE query on title/author
- B) Full-text search with indexing
- C) Use Scout with Meilisearch/Elasticsearch

**Recommendation**: **Option A** (simple LIKE) - Sufficient for most searches, easy to implement

### Decision 3: Deduplication Logic

**Options**:

- A) Exact match on (title, author)
- B) Fuzzy match with similarity threshold
- C) Use external service (Levenshtein, etc.)

**Recommendation**: **Option A** (exact match) + add verification UI for conflicts

### Decision 4: Cleanup Strategy

**Options**:

- A) Never delete cached books
- B) Soft delete (mark inactive)
- C) Hard delete after N days without searches

**Recommendation**: **Option C** - Keeps database lean, users can search again if needed

---

## Rollout Strategy

### Phase A: Internal Testing (1 week)

- Implement all code
- Run full test suite
- Manual testing of search flows
- Load testing with concurrent searches

### Phase B: Beta Testing (1 week)

- Deploy to staging
- Limited user group tests
- Monitor for bugs
- Gather feedback

### Phase C: Full Rollout (1 day)

- Deploy to production
- Monitor search performance
- Monitor API rate limits
- Monitor cover storage usage

---

## Success Metrics

- ✅ 95%+ searches use local DB (after 1 month)
- ✅ Average search latency < 50ms (was 500ms+)
- ✅ Zero cold-start searches (users find something local)
- ✅ Cover storage usage < 2GB (monitoring)
- ✅ Open Library API calls < 100/day (was 1000+/day)
- ✅ User satisfaction with search accuracy

---

## Risks & Mitigation

| Risk                      | Likelihood | Impact | Mitigation                        |
| ------------------------- | ---------- | ------ | --------------------------------- |
| Cover download fails      | Medium     | Low    | Fallback to OL CDN; retry logic   |
| Duplicate books in DB     | Medium     | Medium | Exact match dedup; UI merge tool  |
| Storage grows too large   | Low        | Medium | Auto-cleanup after 6 months       |
| API rate limit hit        | Low        | Medium | Queue online searches; backoff    |
| Poor local search results | Low        | High   | Switch to full-text search; Scout |

---

## Estimated Effort

- Database & Models: **2 hours**
- Services & DTOs: **4 hours**
- Frontend: **3 hours**
- Controller & Routes: **1 hour**
- Storage & Cover Serving: **1 hour**
- Testing: **3 hours**
- Optional Features: **2 hours**

**Total**: **16 hours** spread across 2-3 days

---

## Questions for Approval

1. ✅ Cover storage location: Local `/storage/app/covers/`?
2. ✅ Simple LIKE search vs. full-text indexing?
3. ✅ Auto-cleanup books after 6 months or keep forever?
4. ✅ Should monthly sync be included or optional?
5. ✅ Keep old caching system or fully remove?

---

## Sign-Off

**Approval requested for**:

- [ ] Architecture design
- [ ] Implementation order
- [ ] Data model changes
- [ ] Testing strategy
- [ ] Rollout plan

**Ready to proceed?** → Comment on each section or ask clarifications before implementation starts.
