import { describe, it, expect } from 'vitest';
import { parseDeploymentLogs, type DeploymentStage } from '../DeploymentGraph';

describe('parseDeploymentLogs', () => {
    it('marks running stage as failed when deployment status is failed', () => {
        const logs = [
            { output: 'Deployment started' },
            { output: 'Cloning repository...' },
            { output: 'Clone complete' },
            { output: 'Building docker image started' },
            // Build started but no completion/failure log
        ];

        const stages = parseDeploymentLogs(logs, 'failed');

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('failed');

        // Stages after build should be skipped
        const pushStage = stages.find(s => s.id === 'push');
        expect(pushStage?.status).toBe('skipped');

        const deployStage = stages.find(s => s.id === 'deploy');
        expect(deployStage?.status).toBe('skipped');
    });

    it('keeps running stage as running when deployment is in_progress', () => {
        const logs = [
            { output: 'Deployment started' },
            { output: 'Building docker image started' },
        ];

        const stages = parseDeploymentLogs(logs, 'in_progress');

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('running');
    });

    it('marks running stage as skipped when deployment is cancelled', () => {
        const logs = [
            { output: 'Deployment started' },
            { output: 'Building docker image started' },
        ];

        const stages = parseDeploymentLogs(logs, 'cancelled');

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('skipped');
    });

    it('skips all pending stages after failed stage', () => {
        const logs = [
            { output: 'Deployment started' },
            { output: 'Cloning repository...' },
            // Clone started but never completed
        ];

        const stages = parseDeploymentLogs(logs, 'failed');

        const cloneStage = stages.find(s => s.id === 'clone');
        expect(cloneStage?.status).toBe('failed');

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('skipped');

        const pushStage = stages.find(s => s.id === 'push');
        expect(pushStage?.status).toBe('skipped');
    });

    it('does not alter completed stages when deployment failed', () => {
        const logs = [
            { output: 'Deployment started' },
            { output: 'Cloning repository...' },
            { output: 'Clone complete' },
            { output: 'Building docker image started' },
        ];

        const stages = parseDeploymentLogs(logs, 'failed');

        const cloneStage = stages.find(s => s.id === 'clone');
        expect(cloneStage?.status).toBe('completed');

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('failed');
    });

    it('handles structured stage logs with failed deployment', () => {
        const logs = [
            { output: 'Preparing...', stage: 'prepare', timestamp: '2024-01-01T00:00:00Z' },
            { output: 'Cloning repo...', stage: 'clone', timestamp: '2024-01-01T00:00:05Z' },
            { output: 'Building image...', stage: 'build', timestamp: '2024-01-01T00:00:10Z' },
        ];

        const stages = parseDeploymentLogs(logs, 'failed');

        const prepareStage = stages.find(s => s.id === 'prepare');
        expect(prepareStage?.status).toBe('completed');

        const cloneStage = stages.find(s => s.id === 'clone');
        expect(cloneStage?.status).toBe('completed');

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('failed');

        const pushStage = stages.find(s => s.id === 'push');
        expect(pushStage?.status).toBe('skipped');
    });

    it('works without deployment status (backwards compatible)', () => {
        const logs = [
            { output: 'Deployment started' },
            { output: 'Building docker image started' },
        ];

        const stages = parseDeploymentLogs(logs);

        const buildStage = stages.find(s => s.id === 'build');
        expect(buildStage?.status).toBe('running');
    });
});
