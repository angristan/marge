import { useForm } from '@inertiajs/react';
import {
    Button,
    Center,
    Container,
    Paper,
    PinInput,
    Stack,
    Tabs,
    Text,
    TextInput,
    Title,
} from '@mantine/core';
import { IconKey, IconShieldLock } from '@tabler/icons-react';
import { type FormEvent, useState } from 'react';

export default function TwoFactorChallenge() {
    const [activeTab, setActiveTab] = useState<string | null>('code');
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/login/2fa');
    };

    return (
        <Container size={420} my={100}>
            <Center mb="xl">
                <Title order={1}>Two-Factor Authentication</Title>
            </Center>

            <Paper withBorder shadow="md" p={30} radius="md">
                <form onSubmit={handleSubmit}>
                    <Tabs value={activeTab} onChange={setActiveTab}>
                        <Tabs.List mb="lg">
                            <Tabs.Tab
                                value="code"
                                leftSection={<IconShieldLock size={16} />}
                            >
                                Authenticator
                            </Tabs.Tab>
                            <Tabs.Tab
                                value="recovery"
                                leftSection={<IconKey size={16} />}
                            >
                                Recovery Code
                            </Tabs.Tab>
                        </Tabs.List>

                        <Tabs.Panel value="code">
                            <Stack>
                                <Text size="sm" c="dimmed">
                                    Enter the 6-digit code from your
                                    authenticator app.
                                </Text>
                                <Center>
                                    <PinInput
                                        length={6}
                                        type="number"
                                        value={data.code}
                                        onChange={(value) =>
                                            setData('code', value)
                                        }
                                        error={!!errors.code}
                                        size="lg"
                                        oneTimeCode
                                        autoFocus
                                    />
                                </Center>
                                {errors.code && (
                                    <Text c="red" size="sm" ta="center">
                                        {errors.code}
                                    </Text>
                                )}
                            </Stack>
                        </Tabs.Panel>

                        <Tabs.Panel value="recovery">
                            <Stack>
                                <Text size="sm" c="dimmed">
                                    Enter one of your recovery codes.
                                </Text>
                                <TextInput
                                    placeholder="XXXX-XXXX"
                                    value={data.code}
                                    onChange={(e) =>
                                        setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    error={errors.code}
                                    styles={{
                                        input: {
                                            textAlign: 'center',
                                            fontFamily: 'monospace',
                                            letterSpacing: '0.1em',
                                        },
                                    }}
                                />
                            </Stack>
                        </Tabs.Panel>
                    </Tabs>

                    <Button
                        type="submit"
                        fullWidth
                        mt="lg"
                        loading={processing}
                    >
                        Verify
                    </Button>
                </form>
            </Paper>
        </Container>
    );
}
