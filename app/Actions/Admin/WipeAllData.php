<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Comment;
use App\Models\ImportMapping;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class WipeAllData
{
    use AsAction;

    /**
     * Delete all comments, threads, and import mappings.
     *
     * @return array{comments: int, threads: int, mappings: int}
     */
    public function handle(): array
    {
        return DB::transaction(function () {
            $commentsCount = Comment::withTrashed()->count();
            $threadsCount = Thread::count();
            $mappingsCount = ImportMapping::count();

            // Force delete all comments (bypass soft deletes)
            Comment::withTrashed()->forceDelete();

            // Delete all threads
            Thread::query()->delete();

            // Delete all import mappings
            ImportMapping::query()->delete();

            return [
                'comments' => $commentsCount,
                'threads' => $threadsCount,
                'mappings' => $mappingsCount,
            ];
        });
    }
}
