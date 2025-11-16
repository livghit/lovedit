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
            $table->json('subjects')->nullable()->after('description');
            $table->text('excerpt')->nullable()->after('subjects');
            $table->json('links')->nullable()->after('excerpt');
            $table->string('subtitle')->nullable()->after('title');
            $table->integer('number_of_pages')->nullable()->after('published_year');
            $table->json('languages')->nullable()->after('number_of_pages');
            $table->integer('edition_count')->default(1)->after('languages');
            $table->decimal('ratings_average', 3, 2)->nullable()->after('edition_count');
            $table->integer('ratings_count')->default(0)->after('ratings_average');
            $table->string('first_publish_date')->nullable()->after('published_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn([
                'subjects',
                'excerpt',
                'links',
                'subtitle',
                'number_of_pages',
                'languages',
                'edition_count',
                'ratings_average',
                'ratings_count',
                'first_publish_date',
            ]);
        });
    }
};
