<?php

namespace App\Jobs;

use App\Models\Book;
use App\Services\BookSearchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchBookWorkDetails implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Book $book)
    {
        // Delay the job slightly to avoid hitting rate limits
        $this->delay(now()->addSeconds(rand(1, 5)));
    }

    /**
     * Execute the job.
     */
    public function handle(BookSearchService $searchService): void
    {
        // Skip if book doesn't have a work key
        if (! $this->book->ol_work_key) {
            return;
        }

        // Fetch work details from OpenLibrary Works API
        $workDetails = $searchService->fetchWorkDetails($this->book->ol_work_key);

        if ($workDetails && ! empty($workDetails)) {
            // Update book with work details
            $this->book->update([
                'description' => $workDetails['description'] ?? $this->book->description,
                'subjects' => $workDetails['subjects'] ?? $this->book->subjects,
                'subtitle' => $workDetails['subtitle'] ?? $this->book->subtitle,
                'excerpt' => $workDetails['excerpt'] ?? $this->book->excerpt,
                'links' => $workDetails['links'] ?? $this->book->links,
                'first_publish_date' => $workDetails['first_publish_date'] ?? $this->book->first_publish_date,
                'last_synced_at' => now(),
            ]);
        }
    }

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 10;
}
