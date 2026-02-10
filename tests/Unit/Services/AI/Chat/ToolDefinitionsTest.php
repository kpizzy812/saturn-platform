<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Services\AI\Chat\ToolDefinitions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolDefinitionsTest extends TestCase
{
    #[Test]
    public function it_returns_anthropic_tools_with_correct_structure(): void
    {
        $tools = ToolDefinitions::forAnthropic();

        $this->assertIsArray($tools);
        $this->assertCount(1, $tools);

        // Check parse_commands tool
        $parseCommands = $tools[0];
        $this->assertEquals('parse_commands', $parseCommands['name']);
        $this->assertArrayHasKey('description', $parseCommands);
        $this->assertArrayHasKey('input_schema', $parseCommands);
        $this->assertEquals('object', $parseCommands['input_schema']['type']);
        $this->assertArrayHasKey('properties', $parseCommands['input_schema']);
        $this->assertContains('commands', $parseCommands['input_schema']['required']);
    }

    #[Test]
    public function it_returns_openai_tools_with_correct_structure(): void
    {
        $tools = ToolDefinitions::forOpenAI();

        $this->assertIsArray($tools);
        $this->assertCount(1, $tools);

        // Check parse_commands tool
        $parseCommands = $tools[0];
        $this->assertEquals('function', $parseCommands['type']);
        $this->assertEquals('parse_commands', $parseCommands['function']['name']);
        $this->assertArrayHasKey('description', $parseCommands['function']);
        $this->assertArrayHasKey('parameters', $parseCommands['function']);
        $this->assertTrue($parseCommands['function']['strict']);
    }

    #[Test]
    public function it_returns_command_parsing_schema_for_structured_output(): void
    {
        $schema = ToolDefinitions::commandParsingSchema();

        $this->assertEquals('json_schema', $schema['type']);
        $this->assertEquals('parsed_commands', $schema['json_schema']['name']);
        $this->assertTrue($schema['json_schema']['strict']);

        $properties = $schema['json_schema']['schema']['properties'];
        $this->assertArrayHasKey('commands', $properties);
        $this->assertArrayHasKey('confidence', $properties);
        $this->assertArrayHasKey('response_text', $properties);
    }

    #[Test]
    public function it_includes_all_action_types_in_schema(): void
    {
        $expectedActions = [
            'deploy', 'restart', 'stop', 'start', 'logs', 'status', 'delete',
            'analyze_errors', 'analyze_deployment', 'code_review', 'health_check', 'metrics',
            'help', 'none',
        ];

        $this->assertEquals($expectedActions, ToolDefinitions::ACTION_TYPES);
        // INTENT_TYPES is an alias for ACTION_TYPES
        $this->assertEquals($expectedActions, ToolDefinitions::INTENT_TYPES);
    }

    #[Test]
    public function it_includes_all_resource_types(): void
    {
        $expectedTypes = ['application', 'service', 'database', 'server', 'project'];

        $this->assertEquals($expectedTypes, ToolDefinitions::RESOURCE_TYPES);
    }

    #[Test]
    public function it_returns_parse_commands_only_tools(): void
    {
        $anthropicTools = ToolDefinitions::parseIntentOnlyAnthropic();
        $this->assertCount(1, $anthropicTools);
        $this->assertEquals('parse_commands', $anthropicTools[0]['name']);

        $openaiTools = ToolDefinitions::parseIntentOnlyOpenAI();
        $this->assertCount(1, $openaiTools);
        $this->assertEquals('parse_commands', $openaiTools[0]['function']['name']);
    }

    #[Test]
    public function anthropic_tool_schema_has_valid_json_schema(): void
    {
        $tools = ToolDefinitions::forAnthropic();
        $parseCommands = $tools[0];

        $schema = $parseCommands['input_schema'];

        // Validate schema structure
        $this->assertEquals('object', $schema['type']);
        $this->assertIsArray($schema['properties']);

        // Check commands array has items with action enum
        $commandsProperty = $schema['properties']['commands'];
        $this->assertEquals('array', $commandsProperty['type']);

        $actionProperty = $commandsProperty['items']['properties']['action'];
        $this->assertEquals('string', $actionProperty['type']);
        $this->assertContains('deploy', $actionProperty['enum']);
        $this->assertContains('none', $actionProperty['enum']);

        // Check confidence is number
        $confidenceProperty = $schema['properties']['confidence'];
        $this->assertEquals('number', $confidenceProperty['type']);
    }

    #[Test]
    public function openai_tool_schema_has_strict_mode(): void
    {
        $tools = ToolDefinitions::forOpenAI();

        foreach ($tools as $tool) {
            $this->assertTrue($tool['function']['strict']);
            $this->assertFalse($tool['function']['parameters']['additionalProperties']);
        }
    }

    #[Test]
    public function openai_schema_has_all_required_fields(): void
    {
        $tools = ToolDefinitions::forOpenAI();
        $parseCommands = $tools[0]['function'];

        // All top-level properties should be in required for strict mode
        $required = $parseCommands['parameters']['required'];
        $properties = array_keys($parseCommands['parameters']['properties']);

        foreach ($properties as $property) {
            $this->assertContains($property, $required, "Property {$property} should be required for strict mode");
        }
    }
}
