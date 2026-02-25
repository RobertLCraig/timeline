<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');            // e.g. 'user.role_changed', 'referral_code.deleted'
            $table->string('target_type')->nullable();   // e.g. 'User', 'ReferralCode'
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('payload')->nullable(); // before/after values or context
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
