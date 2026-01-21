import * as React from 'react';
import { SettingsLayout } from './Index';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, Button, Badge, Modal, ModalFooter, Input } from '@/components/ui';
import { Github, GitBranch, MessageSquare, Zap, Settings, CheckCircle2, XCircle } from 'lucide-react';

interface Integration {
    id: string;
    name: string;
    description: string;
    icon: React.ComponentType<{ className?: string }>;
    connected: boolean;
    config?: {
        account?: string;
        lastSync?: string;
        webhookUrl?: string;
    };
}

const mockIntegrations: Integration[] = [
    {
        id: 'github',
        name: 'GitHub',
        description: 'Connect your GitHub repositories for automatic deployments',
        icon: Github,
        connected: true,
        config: {
            account: 'your-username',
            lastSync: '2024-03-28',
        },
    },
    {
        id: 'gitlab',
        name: 'GitLab',
        description: 'Deploy from GitLab repositories',
        icon: GitBranch,
        connected: false,
    },
    {
        id: 'slack',
        name: 'Slack',
        description: 'Get deployment notifications in Slack',
        icon: MessageSquare,
        connected: true,
        config: {
            account: '#deployments',
            lastSync: '2024-03-27',
        },
    },
    {
        id: 'discord',
        name: 'Discord',
        description: 'Receive webhooks for deployment events',
        icon: Zap,
        connected: false,
    },
];

export default function IntegrationsSettings() {
    const [integrations, setIntegrations] = React.useState<Integration[]>(mockIntegrations);
    const [showConfigModal, setShowConfigModal] = React.useState(false);
    const [showDisconnectModal, setShowDisconnectModal] = React.useState(false);
    const [selectedIntegration, setSelectedIntegration] = React.useState<Integration | null>(null);
    const [configData, setConfigData] = React.useState({
        apiKey: '',
        webhookUrl: '',
        channel: '',
    });
    const [isConnecting, setIsConnecting] = React.useState(false);

    const handleConfigure = (integration: Integration) => {
        setSelectedIntegration(integration);
        setShowConfigModal(true);
        if (integration.connected && integration.config) {
            setConfigData({
                apiKey: '',
                webhookUrl: integration.config.webhookUrl || '',
                channel: integration.config.account || '',
            });
        }
    };

    const handleConnect = (e: React.FormEvent) => {
        e.preventDefault();
        setIsConnecting(true);

        // Simulate API call
        setTimeout(() => {
            if (selectedIntegration) {
                const updatedIntegrations = integrations.map((int) =>
                    int.id === selectedIntegration.id
                        ? {
                              ...int,
                              connected: true,
                              config: {
                                  account: configData.channel || 'Connected',
                                  lastSync: new Date().toISOString().split('T')[0],
                                  webhookUrl: configData.webhookUrl,
                              },
                          }
                        : int
                );
                setIntegrations(updatedIntegrations);
            }
            setIsConnecting(false);
            setShowConfigModal(false);
            setConfigData({ apiKey: '', webhookUrl: '', channel: '' });
        }, 1000);
    };

    const handleDisconnect = () => {
        if (selectedIntegration) {
            const updatedIntegrations = integrations.map((int) =>
                int.id === selectedIntegration.id
                    ? {
                          ...int,
                          connected: false,
                          config: undefined,
                      }
                    : int
            );
            setIntegrations(updatedIntegrations);
        }
        setShowDisconnectModal(false);
        setSelectedIntegration(null);
    };

    const openDisconnectModal = (integration: Integration) => {
        setSelectedIntegration(integration);
        setShowDisconnectModal(true);
    };

    return (
        <SettingsLayout activeSection="integrations">
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Integrations</CardTitle>
                        <CardDescription>
                            Connect external services to enhance your workflow
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3">
                            {integrations.map((integration) => {
                                const Icon = integration.icon;
                                return (
                                    <div
                                        key={integration.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-background p-4 transition-colors hover:border-border/80"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10">
                                                <Icon className="h-6 w-6 text-primary" />
                                            </div>
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <p className="font-medium text-foreground">{integration.name}</p>
                                                    {integration.connected ? (
                                                        <Badge variant="success">
                                                            <CheckCircle2 className="mr-1 h-3 w-3" />
                                                            Connected
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="default">
                                                            <XCircle className="mr-1 h-3 w-3" />
                                                            Not Connected
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-sm text-foreground-muted">
                                                    {integration.description}
                                                </p>
                                                {integration.connected && integration.config && (
                                                    <div className="mt-2 space-y-1">
                                                        {integration.config.account && (
                                                            <p className="text-xs text-foreground-subtle">
                                                                Account: {integration.config.account}
                                                            </p>
                                                        )}
                                                        {integration.config.lastSync && (
                                                            <p className="text-xs text-foreground-subtle">
                                                                Last sync: {new Date(integration.config.lastSync).toLocaleDateString()}
                                                            </p>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {integration.connected ? (
                                                <>
                                                    <Button
                                                        variant="secondary"
                                                        size="sm"
                                                        onClick={() => handleConfigure(integration)}
                                                    >
                                                        <Settings className="mr-2 h-4 w-4" />
                                                        Settings
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openDisconnectModal(integration)}
                                                    >
                                                        Disconnect
                                                    </Button>
                                                </>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    onClick={() => handleConfigure(integration)}
                                                >
                                                    Connect
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </CardContent>
                </Card>

                {/* Integration Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>About Integrations</CardTitle>
                        <CardDescription>
                            How integrations work with Saturn
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-3 text-sm">
                            <p className="text-foreground-muted">
                                Integrations allow Saturn to connect with external services and automate your deployment workflow.
                            </p>
                            <ul className="list-inside list-disc space-y-2 text-foreground-subtle">
                                <li>GitHub and GitLab integrations enable automatic deployments on code push</li>
                                <li>Slack and Discord integrations send real-time deployment notifications</li>
                                <li>All integrations use secure OAuth or API tokens</li>
                                <li>You can disconnect integrations at any time</li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Configure/Connect Integration Modal */}
            <Modal
                isOpen={showConfigModal}
                onClose={() => setShowConfigModal(false)}
                title={`${selectedIntegration?.connected ? 'Configure' : 'Connect'} ${selectedIntegration?.name}`}
                description={selectedIntegration?.description}
            >
                <form onSubmit={handleConnect}>
                    <div className="space-y-4">
                        {selectedIntegration?.id === 'github' || selectedIntegration?.id === 'gitlab' ? (
                            <>
                                <Input
                                    label="API Token"
                                    type="password"
                                    value={configData.apiKey}
                                    onChange={(e) => setConfigData({ ...configData, apiKey: e.target.value })}
                                    placeholder="ghp_xxxxxxxxxxxx"
                                    required
                                />
                                <p className="text-xs text-foreground-subtle">
                                    Create a personal access token with repo access from your {selectedIntegration.name} settings
                                </p>
                            </>
                        ) : selectedIntegration?.id === 'slack' ? (
                            <>
                                <Input
                                    label="Webhook URL"
                                    value={configData.webhookUrl}
                                    onChange={(e) => setConfigData({ ...configData, webhookUrl: e.target.value })}
                                    placeholder="https://hooks.slack.com/services/..."
                                    required
                                />
                                <Input
                                    label="Channel"
                                    value={configData.channel}
                                    onChange={(e) => setConfigData({ ...configData, channel: e.target.value })}
                                    placeholder="#deployments"
                                    required
                                />
                            </>
                        ) : selectedIntegration?.id === 'discord' ? (
                            <Input
                                label="Webhook URL"
                                value={configData.webhookUrl}
                                onChange={(e) => setConfigData({ ...configData, webhookUrl: e.target.value })}
                                placeholder="https://discord.com/api/webhooks/..."
                                required
                            />
                        ) : null}
                    </div>

                    <ModalFooter>
                        <Button type="button" variant="secondary" onClick={() => setShowConfigModal(false)}>
                            Cancel
                        </Button>
                        <Button type="submit" loading={isConnecting}>
                            {selectedIntegration?.connected ? 'Save Changes' : 'Connect'}
                        </Button>
                    </ModalFooter>
                </form>
            </Modal>

            {/* Disconnect Integration Modal */}
            <Modal
                isOpen={showDisconnectModal}
                onClose={() => setShowDisconnectModal(false)}
                title={`Disconnect ${selectedIntegration?.name}`}
                description={`Are you sure you want to disconnect ${selectedIntegration?.name}? You'll need to reconfigure it to use it again.`}
            >
                <ModalFooter>
                    <Button variant="secondary" onClick={() => setShowDisconnectModal(false)}>
                        Cancel
                    </Button>
                    <Button variant="danger" onClick={handleDisconnect}>
                        Disconnect
                    </Button>
                </ModalFooter>
            </Modal>
        </SettingsLayout>
    );
}
