import { Link } from '@inertiajs/react';
import { Cloud, Server } from 'lucide-react';
import { SettingsLayout } from '../Index';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/Card';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';

interface Token {
    uuid: string;
    name: string;
    provider: string;
    servers_count: number;
    created_at: string;
}

interface Props {
    tokens: Token[];
}

const providerLabel: Record<string, string> = {
    hetzner: 'Hetzner',
    digitalocean: 'DigitalOcean',
};

export default function CloudProvidersIndex({ tokens }: Props) {
    return (
        <SettingsLayout activeSection="cloud-providers">
            <div className="space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">Cloud Providers</h2>
                        <p className="mt-1 text-sm text-foreground-muted">
                            Cloud provider tokens available to your team for server provisioning.
                            Token management is handled by your administrator.
                        </p>
                    </div>
                    <Link href="/servers/create">
                        <Button size="sm">
                            <Server className="mr-2 h-4 w-4" />
                            Provision New Server
                        </Button>
                    </Link>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Available Tokens</CardTitle>
                        <CardDescription>
                            These tokens are configured by your administrator and can be used to provision servers.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tokens.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <Cloud className="mb-3 h-10 w-10 text-foreground-muted" />
                                <p className="text-sm font-medium text-foreground">No cloud provider tokens configured</p>
                                <p className="mt-1 text-xs text-foreground-muted">
                                    Contact your administrator to add Hetzner or other cloud provider tokens.
                                </p>
                            </div>
                        ) : (
                            <div className="divide-y divide-border">
                                {tokens.map((token) => (
                                    <div
                                        key={token.uuid}
                                        className="flex items-center justify-between py-4 first:pt-0 last:pb-0"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-background-secondary">
                                                <Cloud className="h-4 w-4 text-foreground-muted" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-foreground">{token.name}</p>
                                                <p className="text-xs text-foreground-muted">
                                                    {token.servers_count}{' '}
                                                    {token.servers_count === 1 ? 'server' : 'servers'} provisioned
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant="info">
                                            {providerLabel[token.provider] ?? token.provider}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </SettingsLayout>
    );
}
