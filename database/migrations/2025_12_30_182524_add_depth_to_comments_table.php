<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('depth')->default(0)->after('parent_id');
        });

        // Calculate depth for existing comments
        // Iterate until no more updates are needed (handles arbitrary nesting)
        $updated = 1;
        while ($updated > 0) {
            $updated = DB::update('
                UPDATE comments
                SET depth = (
                    SELECT parent.depth + 1
                    FROM comments AS parent
                    WHERE parent.id = comments.parent_id
                )
                WHERE parent_id IS NOT NULL
                AND depth = 0
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table): void {
            $table->dropColumn('depth');
        });
    }
};
