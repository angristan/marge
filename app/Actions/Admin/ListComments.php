<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Comment;
use App\Support\Gravatar;
use App\Support\Markdown;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

class ListComments
{
    use AsAction;

    /**
     * List comments with filtering and pagination.
     *
     * @param  array{
     *     status?: string|null,
     *     search?: string|null,
     *     thread_id?: int|null,
     *     per_page?: int,
     * }  $filters
     * @return array<string, mixed>
     */
    public function handle(array $filters = []): array
    {
        $query = Comment::with('thread')
            ->orderBy('created_at', 'desc');

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['thread_id'])) {
            $query->where('thread_id', $filters['thread_id']);
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('body_markdown', 'like', "%{$search}%")
                    ->orWhere('author', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage);

        return [
            'data' => collect($paginator->items())->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'author' => $comment->author,
                'email' => $comment->email,
                'avatar' => Gravatar::url($comment->email, 40),
                'body_excerpt' => Markdown::toPlainText($comment->body_markdown, 150),
                'body_html' => $comment->body_html,
                'status' => $comment->status,
                'email_verified' => $comment->email_verified,
                'is_admin' => $comment->is_admin,
                'upvotes' => $comment->upvotes,
                'thread_id' => $comment->thread_id,
                'thread_uri' => $comment->thread->uri,
                'thread_title' => $comment->thread->title,
                'created_at' => $comment->created_at->toIso8601String(),
            ])->toArray(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
