<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records uploads that were flagged by the NSFW content scanner.
 * Admins review flagged uploads and approve or quarantine them.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('upload_flags', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('url');
            $table->foreignId('uploader_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('scores');                         // Raw Sightengine nudity scores
            $table->float('top_score')->default(0);         // Highest individual score (for sorting)
            $table->enum('status', ['pending', 'approved', 'quarantined'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_flags');
    }
};
