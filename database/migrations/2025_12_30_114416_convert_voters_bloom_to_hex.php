<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert voters_bloom from binary to hex-encoded text.
 *
 * This simplifies storage and avoids PostgreSQL PDO issues with binary null bytes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // Add new text column
        Schema::table('comments', function (Blueprint $table): void {
            $table->string('voters_bloom_hex', 256)->nullable()->after('upvotes');
        });

        // Migrate existing data (if any)
        if ($driver === 'pgsql') {
            DB::statement("UPDATE comments SET voters_bloom_hex = encode(voters_bloom, 'hex') WHERE voters_bloom IS NOT NULL");
        } else {
            // SQLite
            DB::statement('UPDATE comments SET voters_bloom_hex = hex(voters_bloom) WHERE voters_bloom IS NOT NULL');
        }

        // Drop old column and rename new one
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('voters_bloom');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->renameColumn('voters_bloom_hex', 'voters_bloom');
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        // Add binary column back
        Schema::table('comments', function (Blueprint $table): void {
            $table->binary('voters_bloom_bin')->nullable()->after('upvotes');
        });

        // Migrate data back to binary
        if ($driver === 'pgsql') {
            DB::statement("UPDATE comments SET voters_bloom_bin = decode(voters_bloom, 'hex') WHERE voters_bloom IS NOT NULL AND voters_bloom != ''");
        } else {
            // SQLite - use unhex (available in SQLite 3.41.0+)
            // For older SQLite, this would fail - acceptable for rollback scenario
            DB::statement("UPDATE comments SET voters_bloom_bin = unhex(voters_bloom) WHERE voters_bloom IS NOT NULL AND voters_bloom != ''");
        }

        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('voters_bloom');
        });

        Schema::table('comments', function (Blueprint $table): void {
            $table->renameColumn('voters_bloom_bin', 'voters_bloom');
        });
    }
};
