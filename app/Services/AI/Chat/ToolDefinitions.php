<?php

namespace App\Services\AI\Chat;

/**
 * Defines tools/functions for AI chat providers.
 * Supports both Anthropic (tool_use) and OpenAI (function_calling) formats.
 */
final class ToolDefinitions
{
    /**
     * Available resource types in the system.
     */
    public const RESOURCE_TYPES = ['application', 'service', 'database', 'server', 'project'];

    /**
     * Available action types.
     */
    public const ACTION_TYPES = [
        'deploy', 'restart', 'stop', 'start', 'logs', 'status', 'delete',
        'analyze_errors', 'analyze_deployment', 'code_review', 'health_check', 'metrics',
        'help', 'none',
    ];

    /**
     * Legacy intent types for backward compatibility.
     */
    public const INTENT_TYPES = self::ACTION_TYPES;

    /**
     * Get tool definitions for Anthropic API.
     */
    public static function forAnthropic(): array
    {
        return [
            self::parseCommandsToolAnthropic(),
        ];
    }

    /**
     * Get tool definitions for OpenAI API.
     */
    public static function forOpenAI(): array
    {
        return [
            self::parseCommandsToolOpenAI(),
        ];
    }

    /**
     * Parse commands tool for Anthropic - supports multiple commands.
     */
    private static function parseCommandsToolAnthropic(): array
    {
        return [
            'name' => 'parse_commands',
            'description' => 'Parse user message to detect one or more actionable commands. Supports multiple resources and multiple actions in a single message.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'commands' => [
                        'type' => 'array',
                        'description' => 'List of commands extracted from user message. Each command is one action on one resource.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'action' => [
                                    'type' => 'string',
                                    'enum' => self::ACTION_TYPES,
                                    'description' => 'The action to perform: deploy, restart, stop, start, logs, status, delete, help, or none',
                                ],
                                'resource_type' => [
                                    'type' => 'string',
                                    'enum' => [...self::RESOURCE_TYPES, 'null'],
                                    'description' => 'Type of resource: application, service, database, server, project, or null',
                                ],
                                'resource_name' => [
                                    'type' => 'string',
                                    'description' => 'Name of the resource if mentioned',
                                ],
                                'project_name' => [
                                    'type' => 'string',
                                    'description' => 'Project name if mentioned (for filtering)',
                                ],
                                'environment_name' => [
                                    'type' => 'string',
                                    'description' => 'Environment name if mentioned (for filtering)',
                                ],
                                'deployment_uuid' => [
                                    'type' => 'string',
                                    'description' => 'UUID of specific deployment for analyze_deployment command',
                                ],
                                'target_scope' => [
                                    'type' => 'string',
                                    'enum' => ['single', 'multiple', 'all'],
                                    'description' => 'Scope for batch operations: single resource, multiple named resources, or all resources',
                                ],
                                'resource_names' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                    'description' => 'Array of resource names for multiple analysis',
                                ],
                                'time_period' => [
                                    'type' => 'string',
                                    'description' => 'Time period for metrics command (e.g., "24h", "7d", "30d")',
                                ],
                            ],
                            'required' => ['action'],
                        ],
                    ],
                    'confidence' => [
                        'type' => 'number',
                        'description' => 'Overall confidence score from 0.0 to 1.0',
                    ],
                    'response_text' => [
                        'type' => 'string',
                        'description' => 'Natural language response to show the user. MUST be in the same language as user message.',
                    ],
                ],
                'required' => ['commands', 'confidence', 'response_text'],
            ],
        ];
    }

    /**
     * Parse commands tool for OpenAI - supports multiple commands.
     */
    private static function parseCommandsToolOpenAI(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'parse_commands',
                'description' => 'Parse user message to detect one or more actionable commands. Supports multiple resources and multiple actions in a single message.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'commands' => [
                            'type' => 'array',
                            'description' => 'List of commands extracted from user message',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'action' => [
                                        'type' => 'string',
                                        'enum' => self::ACTION_TYPES,
                                        'description' => 'The action to perform',
                                    ],
                                    'resource_type' => [
                                        'type' => ['string', 'null'],
                                        'enum' => [...self::RESOURCE_TYPES, null],
                                        'description' => 'Type of resource',
                                    ],
                                    'resource_name' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'Name of the resource',
                                    ],
                                    'project_name' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'Project name if mentioned',
                                    ],
                                    'environment_name' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'Environment name if mentioned',
                                    ],
                                    'deployment_uuid' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'UUID of specific deployment for analyze_deployment command',
                                    ],
                                    'target_scope' => [
                                        'type' => ['string', 'null'],
                                        'enum' => ['single', 'multiple', 'all', null],
                                        'description' => 'Scope for batch operations',
                                    ],
                                    'resource_names' => [
                                        'type' => ['array', 'null'],
                                        'items' => ['type' => 'string'],
                                        'description' => 'Array of resource names for multiple analysis',
                                    ],
                                    'time_period' => [
                                        'type' => ['string', 'null'],
                                        'description' => 'Time period for metrics command',
                                    ],
                                ],
                                'required' => ['action', 'resource_type', 'resource_name', 'project_name', 'environment_name', 'deployment_uuid', 'target_scope', 'resource_names', 'time_period'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'description' => 'Overall confidence score from 0.0 to 1.0',
                        ],
                        'response_text' => [
                            'type' => 'string',
                            'description' => 'Natural language response to show the user',
                        ],
                    ],
                    'required' => ['commands', 'confidence', 'response_text'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Get only the parse_commands tool for Anthropic.
     */
    public static function parseCommandsAnthropic(): array
    {
        return [self::parseCommandsToolAnthropic()];
    }

    /**
     * Get only the parse_commands tool for OpenAI.
     */
    public static function parseCommandsOpenAI(): array
    {
        return [self::parseCommandsToolOpenAI()];
    }

    /**
     * Legacy: Get parse_intent tool for backward compatibility.
     */
    public static function parseIntentOnlyAnthropic(): array
    {
        return self::parseCommandsAnthropic();
    }

    /**
     * Legacy: Get parse_intent tool for backward compatibility.
     */
    public static function parseIntentOnlyOpenAI(): array
    {
        return self::parseCommandsOpenAI();
    }

    /**
     * Get structured output schema for command parsing.
     */
    public static function commandParsingSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'parsed_commands',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'commands' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'action' => [
                                        'type' => 'string',
                                        'enum' => self::ACTION_TYPES,
                                    ],
                                    'resource_type' => [
                                        'type' => ['string', 'null'],
                                        'enum' => [...self::RESOURCE_TYPES, null],
                                    ],
                                    'resource_name' => [
                                        'type' => ['string', 'null'],
                                    ],
                                    'project_name' => [
                                        'type' => ['string', 'null'],
                                    ],
                                    'environment_name' => [
                                        'type' => ['string', 'null'],
                                    ],
                                    'deployment_uuid' => [
                                        'type' => ['string', 'null'],
                                    ],
                                    'target_scope' => [
                                        'type' => ['string', 'null'],
                                        'enum' => ['single', 'multiple', 'all', null],
                                    ],
                                    'resource_names' => [
                                        'type' => ['array', 'null'],
                                        'items' => ['type' => 'string'],
                                    ],
                                    'time_period' => [
                                        'type' => ['string', 'null'],
                                    ],
                                ],
                                'required' => ['action'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'minimum' => 0,
                            'maximum' => 1,
                        ],
                        'response_text' => [
                            'type' => 'string',
                        ],
                    ],
                    'required' => ['commands', 'confidence', 'response_text'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Legacy schema for backward compatibility.
     */
    public static function intentParsingSchema(): array
    {
        return self::commandParsingSchema();
    }
}
