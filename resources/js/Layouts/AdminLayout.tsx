import { Link, usePage } from '@inertiajs/react';
import {
    AppShell,
    Burger,
    Group,
    NavLink,
    Title,
    ActionIcon,
    useMantineColorScheme,
} from '@mantine/core';
import { useDisclosure } from '@mantine/hooks';
import {
    IconDashboard,
    IconMessages,
    IconSettings,
    IconLogout,
    IconSun,
    IconMoon,
} from '@tabler/icons-react';
import type { ReactNode } from 'react';

interface AdminLayoutProps {
    children: ReactNode;
}

export default function AdminLayout({ children }: AdminLayoutProps) {
    const [opened, { toggle }] = useDisclosure();
    const { colorScheme, toggleColorScheme } = useMantineColorScheme();
    const { url } = usePage();

    const navItems = [
        { label: 'Dashboard', href: '/admin', icon: IconDashboard },
        { label: 'Comments', href: '/admin/comments', icon: IconMessages },
        { label: 'Settings', href: '/admin/settings', icon: IconSettings },
    ];

    return (
        <AppShell
            header={{ height: 60 }}
            navbar={{ width: 250, breakpoint: 'sm', collapsed: { mobile: !opened } }}
            padding="md"
        >
            <AppShell.Header>
                <Group h="100%" px="md" justify="space-between">
                    <Group>
                        <Burger opened={opened} onClick={toggle} hiddenFrom="sm" size="sm" />
                        <Title order={3}>Opaska</Title>
                    </Group>
                    <Group>
                        <ActionIcon
                            variant="subtle"
                            onClick={() => toggleColorScheme()}
                            aria-label="Toggle color scheme"
                        >
                            {colorScheme === 'dark' ? <IconSun size={20} /> : <IconMoon size={20} />}
                        </ActionIcon>
                    </Group>
                </Group>
            </AppShell.Header>

            <AppShell.Navbar p="md">
                {navItems.map((item) => (
                    <NavLink
                        key={item.href}
                        component={Link}
                        href={item.href}
                        label={item.label}
                        leftSection={<item.icon size={20} />}
                        active={url === item.href || (item.href !== '/admin' && url.startsWith(item.href))}
                        mb="xs"
                    />
                ))}
                <NavLink
                    component={Link}
                    href="/admin/logout"
                    method="post"
                    as="button"
                    label="Logout"
                    leftSection={<IconLogout size={20} />}
                    mt="auto"
                />
            </AppShell.Navbar>

            <AppShell.Main>{children}</AppShell.Main>
        </AppShell>
    );
}
