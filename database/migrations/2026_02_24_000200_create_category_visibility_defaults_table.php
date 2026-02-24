<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user, per-category default social visibility tier.
     * Users can customise what visibility tier new events default to
     * for each category in their User Settings → Category Visibility page.
     */
    public function up(): void
    {
        Schema::create('category_visibility_defaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('event_categories')->onDelete('cascade');
            $table->enum('visibility_tier', [
                'family',
                'close_friends',
                'friends',
                'acquaintances',
                'public',
                'private',
            ])->default('friends');
            $table->timestamps();

            $table->unique(['user_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_visibility_defaults');
    }
};
