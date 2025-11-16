<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'author',
        'description',
        'isbn',
        'cover_url',
        'external_id',
        'published_year',
        'first_publish_date',
        'publisher',
        'ol_work_key',
        'ol_cover_id',
        'cover_stored_locally',
        'discovered_via_search',
        'first_discovered_at',
        'last_synced_at',
        'is_user_created',
        'search_count',
        'sync_batch_id',
        'ol_last_synced_at',
        'ol_sync_status',
        'cached_from_ol',
        'subjects',
        'excerpt',
        'links',
        'number_of_pages',
        'languages',
        'edition_count',
        'ratings_average',
        'ratings_count',
    ];

    protected function casts(): array
    {
        return [
            'published_year' => 'integer',
            'ol_last_synced_at' => 'datetime',
            'cached_from_ol' => 'boolean',
            'cover_stored_locally' => 'boolean',
            'discovered_via_search' => 'boolean',
            'first_discovered_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'is_user_created' => 'boolean',
            'search_count' => 'integer',
            'subjects' => 'array',
            'links' => 'array',
            'languages' => 'array',
            'number_of_pages' => 'integer',
            'edition_count' => 'integer',
            'ratings_average' => 'decimal:2',
            'ratings_count' => 'integer',
        ];
    }

    /**
     * Mark this book as discovered during an online search
     */
    public function markAsDiscoveredOnline(): self
    {
        $this->update([
            'discovered_via_search' => true,
            'first_discovered_at' => $this->first_discovered_at ?? now(),
            'last_synced_at' => now(),
        ]);

        return $this;
    }

    /**
     * Check if cover is stored locally
     */
    public function hasCoverStored(): bool
    {
        return $this->cover_stored_locally;
    }

    /**
     * Increment search count for this book
     */
    public function incrementSearchCount(): self
    {
        $this->increment('search_count');

        return $this;
    }

    /**
     * Scope to get only books discovered via search
     */
    public function scopeDiscoveredViaSearch(Builder $query): Builder
    {
        return $query->where('discovered_via_search', true);
    }

    /**
     * Scope to get only user-created books
     */
    public function scopeUserCreated(Builder $query): Builder
    {
        return $query->where('is_user_created', true);
    }

    /**
     * Scope to get books ordered by popularity (search count)
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByDesc('search_count');
    }

    /**
     * Get the sync batch this book belongs to
     */
    public function syncBatch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class);
    }

    public function cacheMetadata(): HasMany
    {
        return $this->hasMany(BookCacheMetadata::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function toReviewLists(): HasMany
    {
        return $this->hasMany(ToReviewList::class);
    }
}
