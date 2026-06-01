<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records how an event was created so human posts can be told apart from
     * agent posts: 'web' (SPA), 'api' (token REST), 'mcp' (MCP tool).
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('source', 16)->default('web')->after('created_by');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
