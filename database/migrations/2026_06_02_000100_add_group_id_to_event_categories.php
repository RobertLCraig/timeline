<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scope categories to a group. NULL group_id = a global/seeded category
     * available to every group; a non-NULL group_id = a category that only
     * applies to that one group.
     */
    public function up(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('id')->constrained('groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('group_id');
        });
    }
};
