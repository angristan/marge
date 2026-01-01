<?php

declare(strict_types=1);

namespace App\Actions\Comment;

use App\Actions\Telegram\SendTelegramNotification;
use App\Models\Comment;
use App\Support\BloomFilter;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class UpvoteComment
{
    use AsAction;

    /**
     * Upvote a comment.
     * Returns the new upvote count, or null if already voted.
     *
     * Uses row locking to prevent race conditions when multiple
     * users vote simultaneously.
     */
    public function handle(Comment $comment, ?string $ip = null, ?string $userAgent = null): ?int
    {
        $voterId = BloomFilter::createVoterId($ip, $userAgent);

        $newCount = DB::transaction(function () use ($comment, $voterId): ?int {
            // Lock the row to prevent concurrent modifications
            /** @var Comment $comment */
            $comment = Comment::lockForUpdate()->find($comment->id);

            // Load existing bloom filter
            $filter = BloomFilter::fromHex($comment->voters_bloom);

            // Check if already voted
            if ($filter->mightContain($voterId)) {
                return null;
            }

            // Add to bloom filter and save
            $filter->add($voterId);
            $comment->upvotes = $comment->upvotes + 1;
            $comment->voters_bloom = $filter->toHex();
            $comment->save();

            return $comment->upvotes;
        });

        // Send Telegram notification if upvote was successful
        if ($newCount !== null) {
            SendTelegramNotification::make()->handleUpvote($comment, $newCount);
        }

        return $newCount;
    }
}
