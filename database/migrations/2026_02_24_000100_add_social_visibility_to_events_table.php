<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Social visibility tier for the new social-category visibility system
            $table->enum('social_visibility', [
                'family',
                'close_friends',
                'friends',
                'acquaintances',
                'public',
                'private',
            ])->default('friends')->after('visibility');

            // Whether this event's social_visibility is a manual override
            // (false = inheriting from category default)
            $table->boolean('visibility_is_override')->default(false)->after('social_visibility');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['social_visibility', 'visibility_is_override']);
        });
    }
};
