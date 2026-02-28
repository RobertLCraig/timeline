<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consent logging for GDPR compliance.
 * Logs when users accept cookies or Terms of Service.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('consent_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // 'cookie_consent', 'tos_accepted'
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_logs');
    }
};
