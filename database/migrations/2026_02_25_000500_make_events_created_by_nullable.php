<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make events.created_by nullable so that accounts can be deleted while
 * preserving the group's shared events (GDPR account deletion).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });
    }
};
