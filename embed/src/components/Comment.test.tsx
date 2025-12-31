import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/preact';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Comment as CommentType, Config } from '../api';
import Comment from './Comment';

const createMockApi = () => ({
    upvoteComment: vi.fn().mockResolvedValue({ upvotes: 1 }),
    downvoteComment: vi.fn().mockResolvedValue({ downvotes: 1 }),
    createComment: vi.fn().mockResolvedValue({ id: 1 }),
    previewMarkdown: vi.fn().mockResolvedValue({ html: '<p>Preview</p>' }),
    logout: vi.fn().mockResolvedValue(undefined),
    getGitHubAuthUrl: vi
        .fn()
        .mockReturnValue('https://example.com/auth/github'),
});

const createMockConfig = (overrides: Partial<Config> = {}): Config => ({
    site_name: 'Test Site',
    require_author: false,
    require_email: false,
    moderation_mode: 'none',
    max_depth: 3,
    edit_window_minutes: 5,
    timestamp: '2024-01-01T00:00:00Z',
    is_admin: false,
    enable_upvotes: true,
    enable_downvotes: true,
    admin_badge_label: 'Admin',
    github_auth_enabled: false,
    commenter: null,
    ...overrides,
});

const createMockComment = (
    overrides: Partial<CommentType> = {},
): CommentType => ({
    id: 1,
    parent_id: null,
    parent_author: null,
    depth: 0,
    author: 'Test User',
    is_admin: false,
    is_github_user: false,
    github_username: null,
    avatar: 'https://example.com/avatar.png',
    website: null,
    body_html: '<p>Test comment</p>',
    upvotes: 0,
    downvotes: 0,
    created_at: '2024-01-01T12:00:00Z',
    replies: [],
    ...overrides,
});

describe('Comment', () => {
    let mockApi: ReturnType<typeof createMockApi>;
    let onRefresh: ReturnType<typeof vi.fn>;
    let onConfigRefresh: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        mockApi = createMockApi();
        onRefresh = vi.fn();
        onConfigRefresh = vi.fn();
    });

    afterEach(() => {
        cleanup();
        vi.clearAllMocks();
    });

    it('renders comment content', () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByText('Test User')).toBeInTheDocument();
        expect(screen.getByText('Test comment')).toBeInTheDocument();
    });

    it("renders 'Anonymous' when author is null", () => {
        render(
            <Comment
                comment={createMockComment({ author: null })}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByText('Anonymous')).toBeInTheDocument();
    });

    it('renders avatar image', () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const avatar = screen.getByRole('img');
        expect(avatar).toHaveAttribute('src', 'https://example.com/avatar.png');
    });

    it('renders admin badge when user is admin', () => {
        render(
            <Comment
                comment={createMockComment({ is_admin: true })}
                api={mockApi as any}
                config={createMockConfig({ admin_badge_label: 'Moderator' })}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByText('Moderator')).toBeInTheDocument();
    });

    it('renders GitHub badge when user logged in via GitHub', () => {
        render(
            <Comment
                comment={createMockComment({
                    is_github_user: true,
                    github_username: 'octocat',
                })}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const githubLink = screen.getByLabelText('View @octocat on GitHub');
        expect(githubLink).toHaveAttribute(
            'href',
            'https://github.com/octocat',
        );
    });

    it('renders author as link when website is provided', () => {
        render(
            <Comment
                comment={createMockComment({ website: 'https://example.com' })}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const authorLink = screen.getByRole('link', { name: 'Test User' });
        expect(authorLink).toHaveAttribute('href', 'https://example.com');
    });

    it('renders upvote button when enabled', () => {
        render(
            <Comment
                comment={createMockComment({ upvotes: 5 })}
                api={mockApi as any}
                config={createMockConfig({
                    enable_upvotes: true,
                    enable_downvotes: false,
                })}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByText('5')).toBeInTheDocument();
    });

    it('handles upvote click', async () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig({
                    enable_upvotes: true,
                    enable_downvotes: false,
                })}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const upvoteButton = screen.getAllByRole('button')[0];
        fireEvent.click(upvoteButton);

        await waitFor(() => {
            expect(mockApi.upvoteComment).toHaveBeenCalledWith(1);
        });
    });

    it('handles downvote click', async () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig({
                    enable_upvotes: false,
                    enable_downvotes: true,
                })}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const downvoteButton = screen.getAllByRole('button')[0];
        fireEvent.click(downvoteButton);

        await waitFor(() => {
            expect(mockApi.downvoteComment).toHaveBeenCalledWith(1);
        });
    });

    it('disables vote buttons after voting', async () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig({
                    enable_upvotes: true,
                    enable_downvotes: true,
                })}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const buttons = screen.getAllByRole('button');
        const upvoteButton = buttons[0];
        fireEvent.click(upvoteButton);

        await waitFor(() => {
            expect(upvoteButton).toBeDisabled();
        });
    });

    it('toggles reply form on Reply button click', () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const replyButton = screen.getByRole('button', { name: /Reply/ });
        fireEvent.click(replyButton);

        expect(
            screen.getByPlaceholderText(/Write your comment/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: /Cancel/ }),
        ).toBeInTheDocument();
    });

    it('renders nested replies', () => {
        const comment = createMockComment({
            replies: [
                createMockComment({
                    id: 2,
                    author: 'Reply User',
                    body_html: '<p>Reply content</p>',
                }),
            ],
        });

        render(
            <Comment
                comment={comment}
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                depth={0}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByText('Test User')).toBeInTheDocument();
        expect(screen.getByText('Reply User')).toBeInTheDocument();
    });

    it('sets data-depth attribute based on visual depth', () => {
        render(
            <Comment
                comment={createMockComment()}
                api={mockApi as any}
                config={createMockConfig({ max_depth: 3 })}
                uri="/test"
                depth={5}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const commentEl = document.querySelector("[data-comment-id='1']");
        expect(commentEl).toHaveAttribute('data-depth', '3');
    });

    it('shows parent author link when depth exceeds max_depth', () => {
        render(
            <Comment
                comment={createMockComment({
                    parent_id: 99,
                    parent_author: 'Parent User',
                })}
                api={mockApi as any}
                config={createMockConfig({ max_depth: 2 })}
                uri="/test"
                depth={3}
                onRefresh={onRefresh}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByText(/Parent User/)).toBeInTheDocument();
    });
});
