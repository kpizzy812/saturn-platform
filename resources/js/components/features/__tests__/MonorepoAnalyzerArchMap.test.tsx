import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { MonorepoAnalyzer } from '../MonorepoAnalyzer';

// Mock axios module entirely so axios.post is a controllable vi.fn
vi.mock('axios', () => ({
    default: {
        post: vi.fn(),
    },
}));

import axios from 'axios';
const mockedPost = axios.post as ReturnType<typeof vi.fn>;

// Default mock analysis with apps, databases, services, and dependencies
const mockAnalysisData = {
    is_monorepo: false,
    monorepo_type: null,
    repository_name: 'fundingbot',
    git_branch: 'main',
    applications: [
        {
            name: 'fundingbot',
            path: '.',
            framework: 'dockerfile',
            build_pack: 'dockerfile',
            default_port: 3000,
            type: 'backend',
            application_mode: 'web',
        },
        {
            name: 'hummingbot',
            path: 'hummingbot_custom',
            framework: 'dockerfile',
            build_pack: 'dockerfile',
            default_port: 0,
            type: 'unknown',
            application_mode: 'worker',
        },
    ],
    databases: [
        {
            type: 'redis',
            name: 'redis',
            env_var_name: 'REDIS_URL',
            consumers: ['fundingbot', 'hummingbot'],
            detected_via: 'docker-compose:redis',
            port: 6379,
        },
    ],
    services: [
        {
            type: 'stripe',
            description: 'Payment processing',
            required_env_vars: ['STRIPE_SECRET_KEY'],
        },
    ],
    env_variables: [],
    app_dependencies: [
        {
            app_name: 'hummingbot',
            depends_on: ['fundingbot'],
            internal_urls: {},
            deploy_order: 0,
        },
        {
            app_name: 'fundingbot',
            depends_on: [],
            internal_urls: {},
            deploy_order: 1,
        },
    ],
    docker_compose_services: [],
    ci_config: null,
};

const mockAnalysisResponse = {
    data: {
        success: true,
        data: mockAnalysisData,
    },
};

// Default props for the component
const defaultProps = {
    gitRepository: 'https://github.com/example/fundingbot',
    gitBranch: 'main',
    environmentUuid: 'env-uuid-123',
    destinationUuid: 'dest-uuid-456',
    onComplete: vi.fn(),
    autoStart: true,
};

describe('MonorepoAnalyzer — Architecture Map section', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('Architecture Map visibility', () => {
        it('renders "Architecture Map" heading when apps and databases exist', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });
        });

        it('does NOT render Architecture Map when there are no databases, services, or dependencies', async () => {
            mockedPost.mockResolvedValue({
                data: {
                    success: true,
                    data: {
                        ...mockAnalysisData,
                        databases: [],
                        services: [],
                        app_dependencies: [],
                    },
                },
            });

            render(<MonorepoAnalyzer {...defaultProps} />);

            // Wait for analysis to complete — app names should appear in page
            await waitFor(() => {
                expect(screen.getAllByText('fundingbot').length).toBeGreaterThanOrEqual(1);
            });

            expect(screen.queryByText('Architecture Map')).toBeNull();
        });

        it('renders Architecture Map when apps and services exist but no databases', async () => {
            mockedPost.mockResolvedValue({
                data: {
                    success: true,
                    data: {
                        ...mockAnalysisData,
                        databases: [],
                        services: [{ type: 'stripe', description: 'Payment', required_env_vars: ['STRIPE_KEY'] }],
                        app_dependencies: [],
                    },
                },
            });

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });
        });

        it('renders Architecture Map when apps and dependencies exist but no databases', async () => {
            mockedPost.mockResolvedValue({
                data: {
                    success: true,
                    data: {
                        ...mockAnalysisData,
                        databases: [],
                        services: [],
                        app_dependencies: [
                            { app_name: 'hummingbot', depends_on: ['fundingbot'], internal_urls: {}, deploy_order: 0 },
                        ],
                    },
                },
            });

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });
        });
    });

    describe('App nodes in Architecture Map', () => {
        it('shows app name "fundingbot" in the architecture map', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            // fundingbot appears at least once in the map (it also appears in other sections)
            expect(screen.getAllByText('fundingbot').length).toBeGreaterThanOrEqual(1);
        });

        it('shows app name "hummingbot" in the architecture map', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            expect(screen.getAllByText('hummingbot').length).toBeGreaterThanOrEqual(1);
        });
    });

    describe('Database nodes in Architecture Map', () => {
        it('shows "redis" as a connected database node', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            // redis should appear as a database node (text in the node)
            expect(screen.getAllByText('redis').length).toBeGreaterThanOrEqual(1);
        });

        it('renders one database node per app row that consumes the database', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            // redis has 2 consumers (fundingbot + hummingbot), so it appears at least twice in map rows
            const redisElements = screen.getAllByText('redis');
            expect(redisElements.length).toBeGreaterThanOrEqual(2);
        });
    });

    describe('External services row', () => {
        it('shows "External" label when services exist', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            expect(screen.getByText('External')).toBeDefined();
        });

        it('shows "stripe" service node', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            // stripe may appear multiple times (in the map and in service details section)
            expect(screen.getAllByText('stripe').length).toBeGreaterThanOrEqual(1);
        });

        it('does NOT show "External" label when no services exist', async () => {
            mockedPost.mockResolvedValue({
                data: {
                    success: true,
                    data: {
                        ...mockAnalysisData,
                        services: [],
                    },
                },
            });

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            expect(screen.queryByText('External')).toBeNull();
        });

        it('shows multiple services when they exist', async () => {
            mockedPost.mockResolvedValue({
                data: {
                    success: true,
                    data: {
                        ...mockAnalysisData,
                        services: [
                            { type: 'stripe', description: 'Payment', required_env_vars: ['STRIPE_KEY'] },
                            { type: 'sendgrid', description: 'Email', required_env_vars: ['SENDGRID_KEY'] },
                        ],
                    },
                },
            });

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('External')).toBeDefined();
            });

            // stripe and sendgrid may appear multiple times across sections
            expect(screen.getAllByText('stripe').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('sendgrid').length).toBeGreaterThanOrEqual(1);
        });
    });

    describe('Deploy order timeline', () => {
        it('shows deploy order timeline with "Deploy:" label when apps and databases are selected', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            // The deploy section has a "Deploy:" label (rendered with Rocket icon + text)
            expect(screen.getByText(/Deploy:/)).toBeDefined();
        });

        it('shows redis database in the deploy order timeline', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText(/Deploy:/)).toBeDefined();
            });

            // redis should appear in the deploy timeline as a Badge
            const redisNodes = screen.getAllByText('redis');
            expect(redisNodes.length).toBeGreaterThanOrEqual(1);
        });

        it('shows apps in deploy order after databases in timeline', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText(/Deploy:/)).toBeDefined();
            });

            // Both apps should appear somewhere on the page (in deploy timeline and app sections)
            expect(screen.getAllByText('hummingbot').length).toBeGreaterThanOrEqual(1);
            expect(screen.getAllByText('fundingbot').length).toBeGreaterThanOrEqual(1);
        });

        it('hummingbot (deploy_order 0) appears before fundingbot (deploy_order 1) in timeline', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            const { container } = render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText(/Deploy:/)).toBeDefined();
            });

            // Find the deploy timeline section by locating the "Deploy:" label
            const deployLabel = screen.getByText(/Deploy:/);
            const deploySection = deployLabel.closest('div');
            expect(deploySection).toBeDefined();

            // In the timeline container, hummingbot (order 0) must come before fundingbot (order 1)
            const timelineText = deploySection?.textContent || '';
            const hummingbotIdx = timelineText.indexOf('hummingbot');
            const fundingbotIdx = timelineText.indexOf('fundingbot');

            expect(hummingbotIdx).toBeGreaterThanOrEqual(0);
            expect(fundingbotIdx).toBeGreaterThanOrEqual(0);
            expect(hummingbotIdx).toBeLessThan(fundingbotIdx);
        });

        it('databases appear before apps in the deploy timeline', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText(/Deploy:/)).toBeDefined();
            });

            const deployLabel = screen.getByText(/Deploy:/);
            const deploySection = deployLabel.closest('div');
            const timelineText = deploySection?.textContent || '';

            const redisIdx = timelineText.indexOf('redis');
            const hummingbotIdx = timelineText.indexOf('hummingbot');
            const fundingbotIdx = timelineText.indexOf('fundingbot');

            // redis (database) must deploy before any app
            expect(redisIdx).toBeGreaterThanOrEqual(0);
            expect(redisIdx).toBeLessThan(hummingbotIdx);
            expect(redisIdx).toBeLessThan(fundingbotIdx);
        });
    });

    describe('App-to-app dependency nodes', () => {
        it('shows dependency node when hummingbot depends on fundingbot', async () => {
            mockedPost.mockResolvedValue(mockAnalysisResponse);

            render(<MonorepoAnalyzer {...defaultProps} />);

            await waitFor(() => {
                expect(screen.getByText('Architecture Map')).toBeDefined();
            });

            // hummingbot depends on fundingbot, so fundingbot appears both as:
            // 1. Its own app node row
            // 2. As a dependency node in hummingbot's row
            expect(screen.getAllByText('fundingbot').length).toBeGreaterThanOrEqual(2);
        });
    });
});
