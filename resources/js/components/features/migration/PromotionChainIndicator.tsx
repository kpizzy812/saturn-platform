import { ArrowRight, AlertTriangle } from 'lucide-react';
import { Alert } from '@/components/ui';

interface Environment {
    id?: number;
    name: string;
    type?: string;
}

interface Props {
    sourceEnvironment: Environment;
    targetEnvironment: Environment;
    allEnvironments?: Environment[];
}

const ENV_ORDER: Record<string, number> = {
    development: 1,
    uat: 2,
    staging: 2,
    production: 3,
};

function getEnvOrder(env: Environment): number {
    return ENV_ORDER[env.type || env.name.toLowerCase()] || 0;
}

export function PromotionChainIndicator({ sourceEnvironment, targetEnvironment, allEnvironments = [] }: Props) {
    const sourceOrder = getEnvOrder(sourceEnvironment);
    const targetOrder = getEnvOrder(targetEnvironment);

    // Determine chain environments in order
    const chainEnvs = allEnvironments.length > 0
        ? [...allEnvironments].sort((a, b) => getEnvOrder(a) - getEnvOrder(b))
        : [sourceEnvironment, targetEnvironment];

    // Check if skipping an environment
    const isSkipping = targetOrder - sourceOrder > 1;
    const isReverseDirection = targetOrder < sourceOrder;

    return (
        <div className="space-y-2">
            {/* Chain visualization */}
            <div className="flex items-center justify-center gap-1 py-2">
                {chainEnvs.map((env, idx) => {
                    const isSource = env.name === sourceEnvironment.name || env.id === sourceEnvironment.id;
                    const isTarget = env.name === targetEnvironment.name || env.id === targetEnvironment.id;
                    const isBetween = getEnvOrder(env) > sourceOrder && getEnvOrder(env) < targetOrder;

                    return (
                        <div key={env.name + idx} className="flex items-center gap-1">
                            {idx > 0 && (
                                <ArrowRight className={`h-3 w-3 ${
                                    isTarget || isBetween ? 'text-primary' : 'text-foreground-subtle'
                                }`} />
                            )}
                            <div
                                className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                    isSource
                                        ? 'bg-primary/20 text-primary ring-1 ring-primary/40'
                                        : isTarget
                                            ? 'bg-success/20 text-success ring-1 ring-success/40'
                                            : isBetween
                                                ? 'bg-warning/10 text-warning ring-1 ring-warning/30'
                                                : 'bg-foreground/5 text-foreground-muted'
                                }`}
                            >
                                {env.name}
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Warnings */}
            {isSkipping && (
                <Alert variant="warning">
                    <div className="flex items-start gap-2 text-xs">
                        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                        <span>
                            Skipping environment(s). Consider promoting through each stage sequentially.
                        </span>
                    </div>
                </Alert>
            )}

            {isReverseDirection && (
                <Alert variant="warning">
                    <div className="flex items-start gap-2 text-xs">
                        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 flex-shrink-0" />
                        <span>
                            Reverse promotion direction (higher → lower). This is unusual — ensure this is intentional.
                        </span>
                    </div>
                </Alert>
            )}
        </div>
    );
}
