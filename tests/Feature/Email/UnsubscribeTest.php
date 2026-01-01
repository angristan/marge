<?php

declare(strict_types=1);

namespace Tests\Feature\Email;

use App\Actions\Email\Unsubscribe;
use App\Models\Comment;
use App\Models\NotificationSubscription;
use App\Models\Thread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsubscribes_successfully(): void
    {
        $thread = Thread::factory()->create();
        $comment = Comment::factory()->create(['thread_id' => $thread->id]);

        $subscription = NotificationSubscription::create([
            'email' => 'test@example.com',
            'comment_id' => $comment->id,
            'unsubscribe_token' => 'unsub-token-123',
        ]);

        $result = Unsubscribe::run('unsub-token-123');

        $this->assertTrue($result);

        $subscription->refresh();
        $this->assertNotNull($subscription->unsubscribed_at);
    }

    public function test_unsubscribe_fails_with_invalid_token(): void
    {
        $result = Unsubscribe::run('invalid-token');

        $this->assertFalse($result);
    }

    public function test_unsubscribe_succeeds_if_already_unsubscribed(): void
    {
        $thread = Thread::factory()->create();
        $comment = Comment::factory()->create(['thread_id' => $thread->id]);

        NotificationSubscription::create([
            'email' => 'test@example.com',
            'comment_id' => $comment->id,
            'unsubscribe_token' => 'already-unsub-token',
            'unsubscribed_at' => now()->subDay(),
        ]);

        $result = Unsubscribe::run('already-unsub-token');

        $this->assertTrue($result);
    }

    public function test_unsubscribe_endpoint_redirects_on_success(): void
    {
        $thread = Thread::factory()->create();
        $comment = Comment::factory()->create(['thread_id' => $thread->id]);

        NotificationSubscription::create([
            'email' => 'test@example.com',
            'comment_id' => $comment->id,
            'unsubscribe_token' => 'valid-unsub-token',
        ]);

        $response = $this->get('/unsubscribe/valid-unsub-token');

        $response->assertRedirect('/');
        $response->assertSessionHas('success');
    }

    public function test_unsubscribe_endpoint_redirects_on_failure(): void
    {
        $response = $this->get('/unsubscribe/invalid-token');

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
    }

    public function test_unsubscribe_all_unsubscribes_all_subscriptions_for_email(): void
    {
        $thread1 = Thread::factory()->create();
        $thread2 = Thread::factory()->create();
        $comment1 = Comment::factory()->create(['thread_id' => $thread1->id]);
        $comment2 = Comment::factory()->create(['thread_id' => $thread2->id]);
        $comment3 = Comment::factory()->create(['thread_id' => $thread1->id]);

        $email = 'user@example.com';

        $sub1 = NotificationSubscription::create([
            'email' => $email,
            'comment_id' => $comment1->id,
            'unsubscribe_token' => 'token-1',
        ]);

        $sub2 = NotificationSubscription::create([
            'email' => $email,
            'comment_id' => $comment2->id,
            'unsubscribe_token' => 'token-2',
        ]);

        $sub3 = NotificationSubscription::create([
            'email' => 'other@example.com',
            'comment_id' => $comment3->id,
            'unsubscribe_token' => 'token-3',
        ]);

        $result = Unsubscribe::run('token-1', all: true);

        $this->assertTrue($result);

        $sub1->refresh();
        $sub2->refresh();
        $sub3->refresh();

        $this->assertNotNull($sub1->unsubscribed_at);
        $this->assertNotNull($sub2->unsubscribed_at);
        $this->assertNull($sub3->unsubscribed_at); // Different email, should not be affected
    }

    public function test_unsubscribe_all_endpoint_redirects_on_success(): void
    {
        $thread = Thread::factory()->create();
        $comment = Comment::factory()->create(['thread_id' => $thread->id]);

        NotificationSubscription::create([
            'email' => 'test@example.com',
            'comment_id' => $comment->id,
            'unsubscribe_token' => 'valid-all-token',
        ]);

        $response = $this->get('/unsubscribe/valid-all-token/all');

        $response->assertRedirect('/');
        $response->assertSessionHas('success', 'You have been unsubscribed from all reply notifications.');
    }

    public function test_unsubscribe_all_endpoint_redirects_on_failure(): void
    {
        $response = $this->get('/unsubscribe/invalid-token/all');

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
    }
}
