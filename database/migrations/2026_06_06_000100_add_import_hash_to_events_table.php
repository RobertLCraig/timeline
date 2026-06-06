<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Adds an optional `import_hash` to events so bulk importers (e.g. the
     * photo-import pipeline) can be re-run idempotently: a row's hash uniquely
     * identifies the source it came from, and a second import with the same hash
     * UPDATES that event instead of creating a duplicate.
     *
     * Additive and safe for production: the column is nullable, all existing
     * rows get NULL, and the unique index is composite on (group_id,
     * import_hash). Both SQLite and MySQL treat NULLs as distinct in a UNIQUE
     * index, so the unlimited existing NULL rows never collide — only two
     * real hashes within the same group would.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('import_hash', 64)->nullable()->after('source');
            $table->unique(['group_id', 'import_hash'], 'events_group_import_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique('events_group_import_hash_unique');
            $table->dropColumn('import_hash');
        });
    }
};
