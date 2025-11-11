<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->string('ol_work_key')->nullable()->index();
            $table->unsignedBigInteger('ol_cover_id')->nullable();
            $table->boolean('cover_stored_locally')->default(false);
            $table->boolean('discovered_via_search')->default(false)->index();
            $table->timestamp('first_discovered_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->boolean('is_user_created')->default(false);
            $table->unsignedInteger('search_count')->default(0)->index();

            // Composite index for local search deduplication
            $table->index(['title', 'author'], 'idx_title_author_dedup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex('idx_title_author_dedup');
            $table->dropColumn([
                'ol_work_key',
                'ol_cover_id',
                'cover_stored_locally',
                'discovered_via_search',
                'first_discovered_at',
                'last_synced_at',
                'is_user_created',
                'search_count',
            ]);
        });
    }
};
