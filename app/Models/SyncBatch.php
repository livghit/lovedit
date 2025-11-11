<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncBatch extends Model
{
    protected $fillable = [
        'type',
        'status',
        'books_count',
        'batch_date',
        'metadata',
    ];

    protected $casts = [
        'batch_date' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get all books associated with this sync batch
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class, 'sync_batch_id');
    }
}
