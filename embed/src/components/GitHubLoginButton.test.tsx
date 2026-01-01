import { cleanup, fireEvent, render, screen } from '@testing-library/preact';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import GitHubLoginButton from './GitHubLoginButton';

describe('GitHubLoginButton', () => {
    let onSuccess: ReturnType<typeof vi.fn>;
    let mockOpen: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        onSuccess = vi.fn();
        mockOpen = vi.fn();
        vi.stubGlobal('open', mockOpen);
    });

    afterEach(() => {
        cleanup();
        vi.unstubAllGlobals();
        vi.clearAllMocks();
    });

    it('renders button with GitHub text', () => {
        render(
            <GitHubLoginButton
                authUrl="https://example.com/auth/github"
                expectedOrigin="https://example.com"
                onSuccess={onSuccess}
            />,
        );

        expect(
            screen.getByRole('button', { name: /Login with GitHub/ }),
        ).toBeInTheDocument();
    });

    it('opens popup on click', () => {
        mockOpen.mockReturnValue({ closed: false });

        render(
            <GitHubLoginButton
                authUrl="https://example.com/auth/github"
                expectedOrigin="https://example.com"
                onSuccess={onSuccess}
            />,
        );

        const button = screen.getByRole('button');
        fireEvent.click(button);

        expect(mockOpen).toHaveBeenCalledWith(
            'https://example.com/auth/github',
            'github-oauth',
            expect.stringContaining('width=600,height=700'),
        );
    });

    it('falls back to redirect when popup is blocked', () => {
        mockOpen.mockReturnValue(null);

        const mockLocation = { href: '' };
        vi.stubGlobal('location', mockLocation);

        render(
            <GitHubLoginButton
                authUrl="https://example.com/auth/github"
                expectedOrigin="https://example.com"
                onSuccess={onSuccess}
            />,
        );

        const button = screen.getByRole('button');
        fireEvent.click(button);

        expect(mockLocation.href).toBe('https://example.com/auth/github');
    });

    it('renders GitHub icon', () => {
        render(
            <GitHubLoginButton
                authUrl="https://example.com/auth/github"
                expectedOrigin="https://example.com"
                onSuccess={onSuccess}
            />,
        );

        const svg = document.querySelector('svg');
        expect(svg).toBeInTheDocument();
    });
});
