<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Key-value store for platform-wide admin-configurable settings.
 * Initial values seeded by the migration so the admin UI always has defaults.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });

        // Seed default NSFW settings
        \Illuminate\Support\Facades\DB::table('app_settings')->insert([
            ['key' => 'nsfw_checks_enabled', 'value' => '0'],
            ['key' => 'nudity_threshold',    'value' => '0.6'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
