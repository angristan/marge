import AdminLayout from '@/Layouts/AdminLayout';
import {
    Box,
    Code,
    Container,
    Paper,
    SegmentedControl,
    Select,
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

interface Thread {
    uri: string;
    title: string | null;
    comments_count: number;
}

interface PreviewProps {
    appUrl: string;
    threads: Thread[];
}

export default function Preview({ appUrl, threads }: PreviewProps) {
    const [theme, setTheme] = useState('auto');
    const [viewMode, setViewMode] = useState('admin');
    const [selectedThread, setSelectedThread] = useState<string | null>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    const threadOptions = [
        { value: '', label: 'New thread (preview-page)' },
        ...threads.map((t) => ({
            value: t.uri,
            label: `${t.title || t.uri} (${t.comments_count} comments)`,
        })),
    ];

    useEffect(() => {
        if (!containerRef.current) return;

        // Clear previous widget
        const container = containerRef.current;
        const threadUri = selectedThread || '/preview-page';
        container.innerHTML = `<div id="marge-thread" data-uri="${threadUri}"></div>`;

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

        // Create and append new script (with cache buster to force reload)
        const script = document.createElement('script');
        script.src = `${appUrl}/embed/embed.js?t=${Date.now()}`;
        script.setAttribute('data-marge', appUrl);
        script.setAttribute('data-marge-theme', theme);
        script.setAttribute('data-marge-preview', 'true');
        if (viewMode === 'guest') {
            script.setAttribute('data-marge-guest', 'true');
        }
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
    }, [appUrl, theme, viewMode, selectedThread]);

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
                            <div>
                                <Text fw={500} mb="xs">
                                    View as
                                </Text>
                                <SegmentedControl
                                    value={viewMode}
                                    onChange={setViewMode}
                                    data={[
                                        { label: 'Admin', value: 'admin' },
                                        { label: 'Guest', value: 'guest' },
                                    ]}
                                />
                            </div>
                            <div>
                                <Text fw={500} mb="xs">
                                    Thread
                                </Text>
                                <Select
                                    value={selectedThread}
                                    onChange={setSelectedThread}
                                    data={threadOptions}
                                    placeholder="Select a thread"
                                    searchable
                                    clearable
                                />
                            </div>
                            <Text size="sm" c="dimmed">
                                Preview URL:{' '}
                                <Code>{selectedThread || '/preview-page'}</Code>
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
