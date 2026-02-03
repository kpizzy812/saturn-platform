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
    public const RESOURCE_TYPES = ['application', 'service', 'database', 'server'];

    /**
     * Available intent types.
     */
    public const INTENT_TYPES = ['deploy', 'restart', 'stop', 'start', 'logs', 'status', 'help', 'none'];

    /**
     * Get tool definitions for Anthropic API.
     * Anthropic uses `input_schema` with JSON Schema.
     */
    public static function forAnthropic(): array
    {
        return [
            self::parseIntentToolAnthropic(),
            self::executeCommandToolAnthropic(),
        ];
    }

    /**
     * Get tool definitions for OpenAI API.
     * OpenAI uses `functions` or `tools` with `parameters` JSON Schema.
     */
    public static function forOpenAI(): array
    {
        return [
            self::parseIntentToolOpenAI(),
            self::executeCommandToolOpenAI(),
        ];
    }

    /**
     * Get structured output schema for intent parsing.
     * Used with OpenAI's response_format.
     */
    public static function intentParsingSchema(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'intent_result',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'intent' => [
                            'type' => ['string', 'null'],
                            'enum' => [...self::INTENT_TYPES, null],
                            'description' => 'The detected intent or null if no actionable intent found',
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'minimum' => 0,
                            'maximum' => 1,
                            'description' => 'Confidence score from 0.0 to 1.0',
                        ],
                        'resource_type' => [
                            'type' => ['string', 'null'],
                            'enum' => [...self::RESOURCE_TYPES, null],
                            'description' => 'Type of resource if detected',
                        ],
                        'resource_name' => [
                            'type' => ['string', 'null'],
                            'description' => 'Name of the resource if mentioned',
                        ],
                        'resource_id' => [
                            'type' => ['string', 'integer', 'null'],
                            'description' => 'ID of the resource if mentioned',
                        ],
                        'response_text' => [
                            'type' => 'string',
                            'description' => 'Natural language response to the user',
                        ],
                    ],
                    'required' => ['intent', 'confidence', 'response_text'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Parse intent tool for Anthropic.
     */
    private static function parseIntentToolAnthropic(): array
    {
        return [
            'name' => 'parse_intent',
            'description' => 'Parse user message to detect actionable intent for resource management. Call this tool to analyze what the user wants to do.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'intent' => [
                        'type' => 'string',
                        'enum' => self::INTENT_TYPES,
                        'description' => 'The detected intent: deploy, restart, stop, start, logs, status, help, or none',
                    ],
                    'confidence' => [
                        'type' => 'number',
                        'description' => 'Confidence score from 0.0 to 1.0',
                    ],
                    'resource_type' => [
                        'type' => 'string',
                        'enum' => [...self::RESOURCE_TYPES, 'null'],
                        'description' => 'Type of resource: application, service, database, server, or null',
                    ],
                    'resource_name' => [
                        'type' => 'string',
                        'description' => 'Name of the resource if mentioned, otherwise null',
                    ],
                    'resource_id' => [
                        'type' => 'string',
                        'description' => 'ID of the resource if mentioned, otherwise null',
                    ],
                    'response_text' => [
                        'type' => 'string',
                        'description' => 'Natural language response to show the user',
                    ],
                ],
                'required' => ['intent', 'confidence', 'response_text'],
            ],
        ];
    }

    /**
     * Execute command tool for Anthropic.
     */
    private static function executeCommandToolAnthropic(): array
    {
        return [
            'name' => 'execute_command',
            'description' => 'Execute a command on a resource. Only call after confirming user intent.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'command' => [
                        'type' => 'string',
                        'enum' => ['deploy', 'restart', 'stop', 'start'],
                        'description' => 'The command to execute',
                    ],
                    'resource_type' => [
                        'type' => 'string',
                        'enum' => self::RESOURCE_TYPES,
                        'description' => 'Type of resource to execute command on',
                    ],
                    'resource_id' => [
                        'type' => 'integer',
                        'description' => 'ID of the resource',
                    ],
                ],
                'required' => ['command', 'resource_type', 'resource_id'],
            ],
        ];
    }

    /**
     * Parse intent tool for OpenAI.
     */
    private static function parseIntentToolOpenAI(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'parse_intent',
                'description' => 'Parse user message to detect actionable intent for resource management. Call this function to analyze what the user wants to do.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'intent' => [
                            'type' => 'string',
                            'enum' => self::INTENT_TYPES,
                            'description' => 'The detected intent: deploy, restart, stop, start, logs, status, help, or none',
                        ],
                        'confidence' => [
                            'type' => 'number',
                            'description' => 'Confidence score from 0.0 to 1.0',
                        ],
                        'resource_type' => [
                            'type' => ['string', 'null'],
                            'enum' => [...self::RESOURCE_TYPES, null],
                            'description' => 'Type of resource: application, service, database, server, or null',
                        ],
                        'resource_name' => [
                            'type' => ['string', 'null'],
                            'description' => 'Name of the resource if mentioned, otherwise null',
                        ],
                        'resource_id' => [
                            'type' => ['string', 'null'],
                            'description' => 'ID of the resource if mentioned, otherwise null',
                        ],
                        'response_text' => [
                            'type' => 'string',
                            'description' => 'Natural language response to show the user',
                        ],
                    ],
                    'required' => ['intent', 'confidence', 'resource_type', 'resource_name', 'resource_id', 'response_text'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Execute command tool for OpenAI.
     */
    private static function executeCommandToolOpenAI(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'execute_command',
                'description' => 'Execute a command on a resource. Only call after confirming user intent.',
                'strict' => true,
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'command' => [
                            'type' => 'string',
                            'enum' => ['deploy', 'restart', 'stop', 'start'],
                            'description' => 'The command to execute',
                        ],
                        'resource_type' => [
                            'type' => 'string',
                            'enum' => self::RESOURCE_TYPES,
                            'description' => 'Type of resource to execute command on',
                        ],
                        'resource_id' => [
                            'type' => 'integer',
                            'description' => 'ID of the resource',
                        ],
                    ],
                    'required' => ['command', 'resource_type', 'resource_id'],
                    'additionalProperties' => false,
                ],
            ],
        ];
    }

    /**
     * Get only the parse_intent tool for intent parsing operations.
     */
    public static function parseIntentOnlyAnthropic(): array
    {
        return [self::parseIntentToolAnthropic()];
    }

    /**
     * Get only the parse_intent tool for OpenAI.
     */
    public static function parseIntentOnlyOpenAI(): array
    {
        return [self::parseIntentToolOpenAI()];
    }
}
