<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an ordered photo list to events (card 0009). Additive and safe for
     * production: the column is nullable and existing rows keep NULL, which
     * the Event model reads as a one-photo gallery from `image_url`.
     * `image_url` itself is untouched and stays the cover (first photo).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('image_urls')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('image_urls');
        });
    }
};
