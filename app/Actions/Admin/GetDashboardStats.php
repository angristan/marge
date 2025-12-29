<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Comment;
use App\Models\Thread;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetDashboardStats
{
    use AsAction;

    /**
     * Get dashboard statistics.
     *
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        return [
            'total_comments' => Comment::count(),
            'pending_comments' => Comment::where('status', Comment::STATUS_PENDING)->count(),
            'approved_comments' => Comment::where('status', Comment::STATUS_APPROVED)->count(),
            'spam_comments' => Comment::where('status', Comment::STATUS_SPAM)->count(),
            'total_threads' => Thread::count(),
            'recent_comments' => $this->getRecentComments(),
            'comments_this_week' => $this->getCommentsThisWeek(),
        ];
    }

    /**
     * Get recent comments for the dashboard.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRecentComments(): array
    {
        return Comment::with('thread')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'author' => $comment->author,
                'body_excerpt' => \App\Support\Markdown::toPlainText($comment->body_markdown, 100),
                'status' => $comment->status,
                'thread_uri' => $comment->thread->uri,
                'thread_title' => $comment->thread->title,
                'created_at' => $comment->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * Get comments count per day for the last 7 days.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCommentsThisWeek(): array
    {
        $startDate = now()->subDays(6)->startOfDay();

        // SQLite and PostgreSQL compatible date grouping
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $dateColumn = 'date(created_at)';
        } else {
            $dateColumn = 'DATE(created_at)';
        }

        $results = Comment::where('created_at', '>=', $startDate)
            ->selectRaw("$dateColumn as date, count(*) as count")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing days with 0
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $data[] = [
                'date' => $date,
                'count' => $results[$date] ?? 0,
            ];
        }

        return $data;
    }
}
