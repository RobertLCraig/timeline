<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a user classifies each group they belong to socially.
     * This determines which events (by social_visibility tier) are shown
     * to that user when viewing the group's timeline.
     */
    public function up(): void
    {
        Schema::create('user_group_visibility', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->enum('visibility_tier', [
                'family',
                'close_friends',
                'friends',
                'acquaintances',
            ])->default('friends');
            $table->timestamps();

            $table->unique(['user_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_group_visibility');
    }
};
