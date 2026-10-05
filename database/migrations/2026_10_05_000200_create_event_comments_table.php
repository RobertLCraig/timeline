<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments on an event (card 0011). Additive: a new table only.
     * The body is plain text and is rendered as text, never as HTML.
     */
    public function up(): void
    {
        Schema::create('event_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_comments');
    }
};
