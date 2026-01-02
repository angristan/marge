<?php

declare(strict_types=1);

namespace App\Actions\Telegram;

use App\Actions\Comment\ApproveComment;
use App\Actions\Comment\CreateComment;
use App\Actions\Comment\DeleteComment;
use App\Actions\Comment\DownvoteComment;
use App\Actions\Comment\UpvoteComment;
use App\Models\Comment;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessTelegramWebhook
{
    use AsAction;

    private const TELEGRAM_API_BASE = 'https://api.telegram.org/bot';

    /**
     * Process incoming Telegram webhook update.
     *
     * @param  array<string, mixed>  $update
     */
    public function handle(array $update): bool
    {
        // Handle message reply (admin replying to notification)
        if (isset($update['message']['reply_to_message'])) {
            return $this->handleReply($update['message']);
        }

        // Handle message reaction
        if (isset($update['message_reaction'])) {
            return $this->handleReaction($update['message_reaction']);
        }

        return false;
    }

    public function asController(Request $request): JsonResponse
    {
        // Validate webhook secret
        $secretHeader = $request->header('X-Telegram-Bot-Api-Secret-Token');
        $expectedSecret = Setting::getValue('telegram_webhook_secret');

        if (! $expectedSecret || ! hash_equals($expectedSecret, $secretHeader ?? '')) {
            Log::warning('Telegram webhook: invalid secret');

            return response()->json(['ok' => false], 401);
        }

        $update = $request->all();

        try {
            $this->handle($update);
        } catch (\Exception $e) {
            Log::error('Telegram webhook processing failed', [
                'error' => $e->getMessage(),
                'update' => $update,
            ]);
        }

        // Always return 200 to Telegram to prevent retries
        return response()->json(['ok' => true]);
    }

    /**
     * Handle a reply to a comment notification.
     * Creates a new admin reply to the original comment.
     *
     * @param  array<string, mixed>  $message
     */
    private function handleReply(array $message): bool
    {
        $replyToMessageId = $message['reply_to_message']['message_id'] ?? null;
        $replyText = $message['text'] ?? null;
        $chatId = $message['chat']['id'] ?? null;
        $messageId = $message['message_id'] ?? null;

        if (! $replyToMessageId || ! $replyText) {
            return false;
        }

        // Find the comment by telegram_message_id
        $parentComment = Comment::where('telegram_message_id', $replyToMessageId)->first();

        if (! $parentComment) {
            Log::info('Telegram webhook: no comment found for message', [
                'message_id' => $replyToMessageId,
            ]);

            return false;
        }

        // Create admin reply
        $reply = CreateComment::run([
            'uri' => $parentComment->thread->uri,
            'parent_id' => $parentComment->id,
            'body' => $replyText,
            'author' => Setting::getValue('admin_display_name', 'Admin'),
            'is_admin' => true,
        ]);

        Log::info('Telegram webhook: created admin reply', [
            'parent_comment_id' => $parentComment->id,
            'reply_id' => $reply->id,
        ]);

        // React to confirm
        if ($chatId && $messageId) {
            $this->reactToMessage($chatId, $messageId, '✍️');
        }

        return true;
    }

    /**
     * React to a Telegram message with an emoji.
     */
    private function reactToMessage(int|string $chatId, int $messageId, string $emoji): void
    {
        $botToken = Setting::getValue('telegram_bot_token');
        if (! $botToken) {
            return;
        }

        try {
            $response = Http::post(self::TELEGRAM_API_BASE.$botToken.'/setMessageReaction', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'reaction' => [['type' => 'emoji', 'emoji' => $emoji]],
            ]);

            if (! $response->json('ok')) {
                Log::warning('Telegram reaction failed', [
                    'error' => $response->json('description'),
                    'chat_id' => $chatId,
                    'message_id' => $messageId,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Telegram reaction failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle a reaction to a comment notification.
     *
     * Supported reactions:
     * - 👌 -> Approve pending comment
     * - 💩 -> Delete comment
     * - 👍 -> Upvote
     * - 👎 -> Downvote (if enabled)
     *
     * @param  array<string, mixed>  $reaction
     */
    private function handleReaction(array $reaction): bool
    {
        $messageId = $reaction['message_id'] ?? null;
        $newReactions = $reaction['new_reaction'] ?? [];

        if (! $messageId || empty($newReactions)) {
            return false;
        }

        $comment = Comment::where('telegram_message_id', $messageId)->first();

        if (! $comment) {
            return false;
        }

        foreach ($newReactions as $reactionData) {
            $emoji = $reactionData['emoji'] ?? null;

            if ($emoji === '👌' && $comment->isPending()) {
                ApproveComment::run($comment);
                Log::info('Telegram webhook: approved comment', ['comment_id' => $comment->id]);
            }

            if ($emoji === '💩') {
                DeleteComment::make()->asAdmin($comment);
                Log::info('Telegram webhook: deleted comment', ['comment_id' => $comment->id]);
            }

            if ($emoji === '👍') {
                UpvoteComment::run($comment);
                Log::info('Telegram webhook: upvoted comment', ['comment_id' => $comment->id]);
            }

            if ($emoji === '👎' && Setting::getValue('enable_downvotes', 'false') === 'true') {
                DownvoteComment::run($comment);
                Log::info('Telegram webhook: downvoted comment', ['comment_id' => $comment->id]);
            }
        }

        return true;
    }
}
