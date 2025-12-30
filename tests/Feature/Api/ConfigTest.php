<?php

declare(strict_types=1);

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GET /api/config', function (): void {
    it('returns config with is_admin false when not authenticated', function (): void {
        $response = $this->getJson('/api/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'site_name',
                'require_author',
                'require_email',
                'moderation_mode',
                'max_depth',
                'edit_window_minutes',
                'timestamp',
                'is_admin',
            ])
            ->assertJson([
                'is_admin' => false,
            ]);
    });

    it('returns config with is_admin true when admin is authenticated', function (): void {
        $response = $this->withSession(['admin_authenticated' => true])
            ->getJson('/api/config');

        $response->assertStatus(200)
            ->assertJson([
                'is_admin' => true,
            ]);
    });
});

describe('POST /api/threads/{uri}/comments', function (): void {
    it('creates comment with is_admin true when admin is authenticated', function (): void {
        $response = $this->withSession(['admin_authenticated' => true])
            ->postJson('/api/threads/test/comments', [
                'body' => 'Admin comment here',
                'author' => 'Admin',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'is_admin' => true,
            ]);

        $this->assertDatabaseHas('comments', [
            'body_markdown' => 'Admin comment here',
            'is_admin' => true,
        ]);
    });

    it('creates comment with is_admin false when not authenticated', function (): void {
        $response = $this->postJson('/api/threads/test/comments', [
            'body' => 'Regular comment here',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'is_admin' => false,
            ]);

        $this->assertDatabaseHas('comments', [
            'body_markdown' => 'Regular comment here',
            'is_admin' => false,
        ]);
    });
});
