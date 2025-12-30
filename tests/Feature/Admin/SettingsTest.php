<?php

declare(strict_types=1);

use App\Actions\Admin\SetupAdmin;
use App\Models\Comment;
use App\Models\ImportMapping;
use App\Models\Setting;
use App\Models\Thread;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    SetupAdmin::run('admin', 'password123', 'Test Site', 'https://example.com');

    $this->post('/admin/login', [
        'username' => 'admin',
        'password' => 'password123',
    ]);
});

describe('Admin Settings', function (): void {
    it('shows settings page', function (): void {
        $response = $this->get('/admin/settings');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Settings/Index')
                ->has('settings')
                ->has('settings.site_name')
                ->has('settings.moderation_mode')
        );
    });

    it('updates site name', function (): void {
        $response = $this->post('/admin/settings', [
            'site_name' => 'New Site Name',
        ]);

        $response->assertRedirect();
        expect(Setting::getValue('site_name'))->toBe('New Site Name');
    });

    it('updates moderation mode', function (): void {
        $response = $this->post('/admin/settings', [
            'moderation_mode' => 'all',
        ]);

        $response->assertRedirect();
        expect(Setting::getValue('moderation_mode'))->toBe('all');
    });

    it('updates spam settings', function (): void {
        $response = $this->post('/admin/settings', [
            'rate_limit_per_minute' => 10,
            'max_links' => 5,
            'blocked_words' => "spam\nviagra",
        ]);

        $response->assertRedirect();
        expect(Setting::getValue('rate_limit_per_minute'))->toBe('10');
        expect(Setting::getValue('max_links'))->toBe('5');
        expect(Setting::getValue('blocked_words'))->toBe("spam\nviagra");
    });

    it('updates SMTP settings', function (): void {
        $response = $this->post('/admin/settings', [
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => 'user@example.com',
            'smtp_from_address' => 'noreply@example.com',
        ]);

        $response->assertRedirect();
        expect(Setting::getValue('smtp_host'))->toBe('smtp.example.com');
        expect(Setting::getValue('smtp_port'))->toBe('587');
    });

    it('validates moderation mode values', function (): void {
        $response = $this->post('/admin/settings', [
            'moderation_mode' => 'invalid',
        ]);

        $response->assertSessionHasErrors(['moderation_mode']);
    });

    it('validates numeric fields', function (): void {
        $response = $this->post('/admin/settings', [
            'max_depth' => 4, // Max is 3
        ]);

        $response->assertSessionHasErrors(['max_depth']);
    });

    it('clears empty values', function (): void {
        Setting::setValue('custom_css', '.test { color: red; }');

        $response = $this->post('/admin/settings', [
            'custom_css' => '',
        ]);

        $response->assertRedirect();
        // Empty string values delete the setting, so getValue returns default (empty string)
        expect(Setting::getValue('custom_css', ''))->toBe('');
    });

    it('wipes all data', function (): void {
        $thread = Thread::create(['uri' => '/test']);
        Comment::create([
            'thread_id' => $thread->id,
            'body_markdown' => 'Test',
            'body_html' => '<p>Test</p>',
            'status' => Comment::STATUS_APPROVED,
        ]);
        ImportMapping::createMapping('isso', '1', 'thread', $thread->id);

        $response = $this->delete('/admin/settings/wipe');

        $response->assertRedirect();
        expect(Thread::count())->toBe(0);
        expect(Comment::count())->toBe(0);
        expect(ImportMapping::count())->toBe(0);
    });

    it('wipes soft-deleted comments', function (): void {
        $thread = Thread::create(['uri' => '/test']);
        $comment = Comment::create([
            'thread_id' => $thread->id,
            'body_markdown' => 'Test',
            'body_html' => '<p>Test</p>',
            'status' => Comment::STATUS_APPROVED,
        ]);
        $comment->delete();

        expect(Comment::withTrashed()->count())->toBe(1);

        $this->delete('/admin/settings/wipe');

        expect(Comment::withTrashed()->count())->toBe(0);
    });

    it('preserves settings after wipe', function (): void {
        Setting::setValue('site_name', 'My Site');
        Thread::create(['uri' => '/test']);

        $this->delete('/admin/settings/wipe');

        expect(Setting::getValue('site_name'))->toBe('My Site');
    });
});
