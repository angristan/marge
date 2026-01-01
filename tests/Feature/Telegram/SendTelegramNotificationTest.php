<?php

declare(strict_types=1);

use App\Actions\Telegram\SendTelegramNotification;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\Thread;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Http::fake([
        'api.telegram.org/*' => Http::response([
            'ok' => true,
            'result' => ['message_id' => 12345],
        ]),
    ]);
});

it('sends telegram notification for new comment', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_chat_id', '123456');

    $thread = Thread::factory()->create(['title' => 'Test Thread']);
    $comment = Comment::factory()->create([
        'thread_id' => $thread->id,
        'is_admin' => false,
        'author' => 'Test User',
    ]);

    $messageId = SendTelegramNotification::run($comment);

    expect($messageId)->toBe(12345);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === '123456';
    });

    $comment->refresh();
    expect($comment->telegram_message_id)->toBe(12345);
});

it('does not send notification when telegram is disabled', function (): void {
    Setting::setValue('enable_telegram', 'false');

    $comment = Comment::factory()->create(['is_admin' => false]);

    $messageId = SendTelegramNotification::run($comment);

    expect($messageId)->toBeNull();
    Http::assertNothingSent();
});

it('does not send notification for admin comments', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_chat_id', '123456');

    $comment = Comment::factory()->admin()->create();

    $messageId = SendTelegramNotification::run($comment);

    expect($messageId)->toBeNull();
    Http::assertNothingSent();
});

it('does not send notification without bot token', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_chat_id', '123456');
    // No bot token set

    $comment = Comment::factory()->create(['is_admin' => false]);

    $messageId = SendTelegramNotification::run($comment);

    expect($messageId)->toBeNull();
    Http::assertNothingSent();
});

it('does not send notification without chat id', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_bot_token', 'test-token', true);
    // No chat ID set

    $comment = Comment::factory()->create(['is_admin' => false]);

    $messageId = SendTelegramNotification::run($comment);

    expect($messageId)->toBeNull();
    Http::assertNothingSent();
});

it('sends upvote notification when enabled', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_chat_id', '123456');
    Setting::setValue('telegram_notify_upvotes', 'true');

    $comment = Comment::factory()->create();

    $messageId = SendTelegramNotification::make()->handleUpvote($comment, 5);

    expect($messageId)->toBe(12345);
    Http::assertSent(function ($request) {
        return str_contains($request['text'], 'Upvote Received');
    });
});

it('does not send upvote notification when disabled', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_chat_id', '123456');
    Setting::setValue('telegram_notify_upvotes', 'false');

    $comment = Comment::factory()->create();

    $messageId = SendTelegramNotification::make()->handleUpvote($comment, 5);

    expect($messageId)->toBeNull();
    Http::assertNothingSent();
});

it('includes pending badge for pending comments', function (): void {
    Setting::setValue('enable_telegram', 'true');
    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_chat_id', '123456');

    $comment = Comment::factory()->pending()->create(['is_admin' => false]);

    SendTelegramNotification::run($comment);

    Http::assertSent(function ($request) {
        return str_contains($request['text'], '[PENDING]');
    });
});

it('sends test message successfully', function (): void {
    Setting::setValue('telegram_bot_token', 'test-token', true);
    Setting::setValue('telegram_chat_id', '123456');

    $result = SendTelegramNotification::make()->sendTest();

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Test message sent to Telegram');
});

it('fails test message without bot token', function (): void {
    Setting::setValue('telegram_chat_id', '123456');

    $result = SendTelegramNotification::make()->sendTest();

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Bot token not configured');
});

it('fails test message without chat id', function (): void {
    Setting::setValue('telegram_bot_token', 'test-token', true);

    $result = SendTelegramNotification::make()->sendTest();

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Chat ID not configured');
});
