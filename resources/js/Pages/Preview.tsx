import AdminLayout from '@/Layouts/AdminLayout';
import {
    Box,
    Code,
    Container,
    Paper,
    SegmentedControl,
    Stack,
    Text,
    Title,
} from '@mantine/core';
import { useEffect, useRef, useState } from 'react';

declare global {
    interface Window {
        Marge?: unknown;
    }
}

interface PreviewProps {
    appUrl: string;
}

export default function Preview({ appUrl }: PreviewProps) {
    const [theme, setTheme] = useState('auto');
    const containerRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!containerRef.current) return;

        // Clear previous widget
        const container = containerRef.current;
        container.innerHTML = '<div id="marge-thread"></div>';

        // Remove any existing script
        const existingScript = document.querySelector(
            'script[data-marge-preview]',
        );
        if (existingScript) {
            existingScript.remove();
        }

        // Clean up Marge global state if it exists
        if (typeof window !== 'undefined' && window.Marge) {
            window.Marge = undefined;
        }

        // Create and append new script
        const script = document.createElement('script');
        script.src = `${appUrl}/embed/embed.js`;
        script.setAttribute('data-marge', appUrl);
        script.setAttribute('data-marge-theme', theme);
        script.setAttribute('data-marge-preview', 'true');
        script.async = true;
        container.appendChild(script);

        return () => {
            // Cleanup on unmount
            const scriptToRemove = document.querySelector(
                'script[data-marge-preview]',
            );
            if (scriptToRemove) {
                scriptToRemove.remove();
            }
        };
    }, [appUrl, theme]);

    return (
        <AdminLayout>
            <Container size="lg">
                <Stack gap="lg">
                    <div>
                        <Title order={2}>Widget Preview</Title>
                        <Text c="dimmed">
                            See how the comment widget looks on your site
                        </Text>
                    </div>

                    <Paper p="md" withBorder>
                        <Stack gap="md">
                            <div>
                                <Text fw={500} mb="xs">
                                    Theme
                                </Text>
                                <SegmentedControl
                                    value={theme}
                                    onChange={setTheme}
                                    data={[
                                        { label: 'Auto', value: 'auto' },
                                        { label: 'Light', value: 'light' },
                                        { label: 'Dark', value: 'dark' },
                                    ]}
                                />
                            </div>
                            <Text size="sm" c="dimmed">
                                Preview URL: <Code>/preview-page</Code>
                            </Text>
                        </Stack>
                    </Paper>

                    {/* Mock blog post */}
                    <Paper
                        p="xl"
                        withBorder
                        style={{
                            backgroundColor:
                                theme === 'dark'
                                    ? '#1a1b1e'
                                    : theme === 'light'
                                      ? '#ffffff'
                                      : undefined,
                        }}
                    >
                        <Box maw={720} mx="auto">
                            <article>
                                <Title
                                    order={1}
                                    mb="md"
                                    style={{
                                        color:
                                            theme === 'dark'
                                                ? '#c1c2c5'
                                                : theme === 'light'
                                                  ? '#212529'
                                                  : undefined,
                                    }}
                                >
                                    Welcome to My Blog
                                </Title>
                                <Text
                                    mb="xl"
                                    style={{
                                        color:
                                            theme === 'dark'
                                                ? '#909296'
                                                : theme === 'light'
                                                  ? '#495057'
                                                  : undefined,
                                    }}
                                >
                                    This is a sample blog post to demonstrate
                                    how the Marge comment widget integrates with
                                    your content. The widget below allows
                                    visitors to leave comments, reply to others,
                                    and engage with your content.
                                </Text>
                                <Text
                                    mb="xl"
                                    style={{
                                        color:
                                            theme === 'dark'
                                                ? '#909296'
                                                : theme === 'light'
                                                  ? '#495057'
                                                  : undefined,
                                    }}
                                >
                                    Try posting a comment below to see how it
                                    works! You can use Markdown for formatting,
                                    including <strong>bold</strong>,{' '}
                                    <em>italic</em>, and code blocks.
                                </Text>
                            </article>

                            {/* Comment widget container */}
                            <Box
                                mt="xl"
                                pt="xl"
                                style={{
                                    borderTop: `1px solid ${theme === 'dark' ? '#373a40' : '#dee2e6'}`,
                                }}
                            >
                                <div ref={containerRef}>
                                    <div id="marge-thread" />
                                </div>
                            </Box>
                        </Box>
                    </Paper>
                </Stack>
            </Container>
        </AdminLayout>
    );
}
