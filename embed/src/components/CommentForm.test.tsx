import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/preact';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Config } from '../api';
import CommentForm from './CommentForm';

const createMockApi = () => ({
    createComment: vi.fn().mockResolvedValue({ id: 1 }),
    previewMarkdown: vi.fn().mockResolvedValue({ html: '<p>Preview</p>' }),
    logout: vi.fn().mockResolvedValue(undefined),
    getGitHubAuthUrl: vi
        .fn()
        .mockReturnValue('https://example.com/auth/github'),
    getBaseUrl: vi.fn().mockReturnValue('https://example.com'),
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
    enable_downvotes: false,
    admin_badge_label: 'Admin',
    github_auth_enabled: false,
    commenter: null,
    ...overrides,
});

describe('CommentForm', () => {
    let mockApi: ReturnType<typeof createMockApi>;
    let onSubmit: ReturnType<typeof vi.fn>;
    let onConfigRefresh: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        mockApi = createMockApi();
        onSubmit = vi.fn();
        onConfigRefresh = vi.fn();
    });

    afterEach(() => {
        cleanup();
        vi.clearAllMocks();
    });

    it('renders the comment form', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(
            screen.getByPlaceholderText(/Write your comment/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Post Comment' }),
        ).toBeInTheDocument();
    });

    it("shows 'Reply' button text when parentId is provided", () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                parentId={123}
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Reply' }),
        ).toBeInTheDocument();
    });

    it("shows 'Post as Admin' button text when user is admin", () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig({ is_admin: true })}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(
            screen.getByRole('button', { name: 'Post as Admin' }),
        ).toBeInTheDocument();
    });

    it('shows name/email fields when not admin and not GitHub authenticated', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByPlaceholderText(/Name/)).toBeInTheDocument();
        expect(screen.getByPlaceholderText(/Email/)).toBeInTheDocument();
    });

    it('hides name/email fields when user is admin', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig({ is_admin: true })}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.queryByPlaceholderText(/Name/)).not.toBeInTheDocument();
        expect(screen.queryByPlaceholderText(/Email/)).not.toBeInTheDocument();
    });

    it('hides name/email fields when GitHub authenticated', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig({
                    commenter: {
                        github_id: '123',
                        github_username: 'testuser',
                        name: 'Test User',
                        email: 'test@example.com',
                    },
                })}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.queryByPlaceholderText(/Name/)).not.toBeInTheDocument();
        expect(screen.queryByPlaceholderText(/Email/)).not.toBeInTheDocument();
    });

    it('shows required markers when fields are required', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig({
                    require_author: true,
                    require_email: true,
                })}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(screen.getByPlaceholderText('Name *')).toBeInTheDocument();
        expect(screen.getByPlaceholderText('Email *')).toBeInTheDocument();
    });

    it('submits form with correct data', async () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const textarea = screen.getByPlaceholderText(/Write your comment/);
        fireEvent.input(textarea, { target: { value: 'Test comment' } });

        const submitButton = screen.getByRole('button', {
            name: 'Post Comment',
        });
        fireEvent.click(submitButton);

        await waitFor(() => {
            expect(mockApi.createComment).toHaveBeenCalledWith('/test', {
                parent_id: undefined,
                author: undefined,
                email: undefined,
                website: undefined,
                body: 'Test comment',
                notify_replies: false,
                title: undefined,
                url: undefined,
                honeypot: '',
                timestamp: '2024-01-01T00:00:00Z',
            });
        });

        await waitFor(() => {
            expect(onSubmit).toHaveBeenCalled();
        });
    });

    it('disables submit button when body is empty', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const submitButton = screen.getByRole('button', {
            name: 'Post Comment',
        });
        expect(submitButton).toBeDisabled();
    });

    it('enables submit button when body has content', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const textarea = screen.getByPlaceholderText(/Write your comment/);
        fireEvent.input(textarea, { target: { value: 'Test' } });

        const submitButton = screen.getByRole('button', {
            name: 'Post Comment',
        });
        expect(submitButton).not.toBeDisabled();
    });

    it('toggles preview mode', async () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const textarea = screen.getByPlaceholderText(/Write your comment/);
        fireEvent.input(textarea, { target: { value: '**bold**' } });

        const previewButton = screen.getByRole('button', { name: 'Preview' });
        fireEvent.click(previewButton);

        await waitFor(() => {
            expect(mockApi.previewMarkdown).toHaveBeenCalledWith('**bold**');
        });

        await waitFor(() => {
            expect(
                screen.getByRole('button', { name: 'Edit' }),
            ).toBeInTheDocument();
        });
    });

    it('shows honeypot field (hidden)', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const honeypot = document.querySelector('input[name="website_url"]');
        expect(honeypot).toBeInTheDocument();
        expect(honeypot).toHaveStyle({ display: 'none' });
    });

    it('disables notify checkbox when no email', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const checkbox = screen.getByRole('checkbox');
        expect(checkbox).toBeDisabled();
    });

    it('enables notify checkbox when email is provided', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const emailInput = screen.getByPlaceholderText(/Email/);
        fireEvent.input(emailInput, { target: { value: 'test@example.com' } });

        const checkbox = screen.getByRole('checkbox');
        expect(checkbox).not.toBeDisabled();
    });

    it('shows GitHub login button when github auth is enabled', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig({ github_auth_enabled: true })}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(
            screen.getByRole('button', { name: /Login with GitHub/ }),
        ).toBeInTheDocument();
    });

    it('shows logout button when GitHub authenticated', () => {
        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig({
                    github_auth_enabled: true,
                    commenter: {
                        github_id: '123',
                        github_username: 'testuser',
                        name: 'Test User',
                        email: 'test@example.com',
                    },
                })}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        expect(
            screen.getByRole('button', { name: /Logout/ }),
        ).toBeInTheDocument();
    });

    it('displays error message on submission failure', async () => {
        mockApi.createComment.mockRejectedValueOnce(
            new Error('Validation failed'),
        );

        render(
            <CommentForm
                api={mockApi as any}
                config={createMockConfig()}
                uri="/test"
                onSubmit={onSubmit}
                onConfigRefresh={onConfigRefresh}
            />,
        );

        const textarea = screen.getByPlaceholderText(/Write your comment/);
        fireEvent.input(textarea, { target: { value: 'Test' } });

        const submitButton = screen.getByRole('button', {
            name: 'Post Comment',
        });
        fireEvent.click(submitButton);

        await waitFor(() => {
            expect(screen.getByText('Validation failed')).toBeInTheDocument();
        });
    });
});
