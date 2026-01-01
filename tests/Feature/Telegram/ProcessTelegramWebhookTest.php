<?php

declare(strict_types=1);

use App\Models\Comment;
use App\Models\Setting;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Setting::setValue('telegram_webhook_secret', 'test-secret', true);

    // Fake Telegram API for any outgoing notifications
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 99999],
        ]),
    ]);
});

describe('webhook security', function (): void {
    it('rejects requests without secret token', function (): void {
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => ['text' => 'test'],
        ]);

        $response->assertUnauthorized();
    });

    it('rejects requests with invalid secret token', function (): void {
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => ['text' => 'test'],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
        ]);

        $response->assertUnauthorized();
    });

    it('accepts requests with valid secret token', function (): void {
        $response = $this->postJson('/api/telegram/webhook', [
            'update_id' => 123,
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();
    });
});

describe('reply handling', function (): void {
    it('creates admin reply when replying to notification', function (): void {
        $thread = Thread::factory()->create(['uri' => '/test']);
        $comment = Comment::factory()->create([
            'thread_id' => $thread->id,
            'telegram_message_id' => 12345,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'text' => 'This is my admin reply',
                'reply_to_message' => [
                    'message_id' => 12345,
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('comments', [
            'parent_id' => $comment->id,
            'body_markdown' => 'This is my admin reply',
            'is_admin' => true,
        ]);
    });

    it('ignores reply to unknown message', function (): void {
        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'text' => 'Reply to unknown',
                'reply_to_message' => [
                    'message_id' => 99999,
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $this->assertDatabaseCount('comments', 0);
    });

    it('ignores reply without text', function (): void {
        $thread = Thread::factory()->create(['uri' => '/test']);
        $comment = Comment::factory()->create([
            'thread_id' => $thread->id,
            'telegram_message_id' => 12345,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'reply_to_message' => [
                    'message_id' => 12345,
                ],
                // No text field
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        // Only the original comment should exist
        $this->assertDatabaseCount('comments', 1);
    });
});

describe('reaction handling', function (): void {
    it('upvotes comment on thumbs up reaction', function (): void {
        $comment = Comment::factory()->create([
            'telegram_message_id' => 12345,
            'upvotes' => 0,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '👍'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->upvotes)->toBe(1);
    });

    it('approves pending comment on ok reaction', function (): void {
        $comment = Comment::factory()->pending()->create([
            'telegram_message_id' => 12345,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '👌'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->status)->toBe('approved');
    });

    it('does not approve already approved comment', function (): void {
        $comment = Comment::factory()->create([
            'telegram_message_id' => 12345,
            'status' => 'approved',
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '👌'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->status)->toBe('approved');
    });

    it('deletes comment on poop reaction', function (): void {
        $comment = Comment::factory()->create([
            'telegram_message_id' => 12345,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '💩'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->status)->toBe('deleted');
        expect($comment->trashed())->toBeTrue();
    });

    it('downvotes comment on thumbs down reaction when enabled', function (): void {
        Setting::setValue('enable_downvotes', 'true');

        $comment = Comment::factory()->create([
            'telegram_message_id' => 12345,
            'downvotes' => 0,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '👎'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->downvotes)->toBe(1);
    });

    it('ignores thumbs down reaction when downvotes disabled', function (): void {
        Setting::setValue('enable_downvotes', 'false');

        $comment = Comment::factory()->create([
            'telegram_message_id' => 12345,
            'downvotes' => 0,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '👎'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->downvotes)->toBe(0);
    });

    it('ignores reaction on unknown message', function (): void {
        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 99999,
                'new_reaction' => [
                    ['emoji' => '👍'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();
    });

    it('handles multiple reactions in one update', function (): void {
        $comment = Comment::factory()->pending()->create([
            'telegram_message_id' => 12345,
            'upvotes' => 0,
        ]);

        $response = $this->postJson('/api/telegram/webhook', [
            'message_reaction' => [
                'message_id' => 12345,
                'new_reaction' => [
                    ['emoji' => '👍'],
                    ['emoji' => '👌'],
                ],
            ],
        ], [
            'X-Telegram-Bot-Api-Secret-Token' => 'test-secret',
        ]);

        $response->assertOk();

        $comment->refresh();
        expect($comment->upvotes)->toBe(1);
        expect($comment->status)->toBe('approved');
    });
});
