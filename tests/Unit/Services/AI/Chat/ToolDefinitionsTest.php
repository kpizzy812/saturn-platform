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
        $this->assertCount(2, $tools);

        // Check parse_intent tool
        $parseIntent = $tools[0];
        $this->assertEquals('parse_intent', $parseIntent['name']);
        $this->assertArrayHasKey('description', $parseIntent);
        $this->assertArrayHasKey('input_schema', $parseIntent);
        $this->assertEquals('object', $parseIntent['input_schema']['type']);
        $this->assertArrayHasKey('properties', $parseIntent['input_schema']);
        $this->assertContains('intent', $parseIntent['input_schema']['required']);

        // Check execute_command tool
        $executeCommand = $tools[1];
        $this->assertEquals('execute_command', $executeCommand['name']);
        $this->assertArrayHasKey('input_schema', $executeCommand);
    }

    #[Test]
    public function it_returns_openai_tools_with_correct_structure(): void
    {
        $tools = ToolDefinitions::forOpenAI();

        $this->assertIsArray($tools);
        $this->assertCount(2, $tools);

        // Check parse_intent tool
        $parseIntent = $tools[0];
        $this->assertEquals('function', $parseIntent['type']);
        $this->assertEquals('parse_intent', $parseIntent['function']['name']);
        $this->assertArrayHasKey('description', $parseIntent['function']);
        $this->assertArrayHasKey('parameters', $parseIntent['function']);
        $this->assertTrue($parseIntent['function']['strict']);

        // Check execute_command tool
        $executeCommand = $tools[1];
        $this->assertEquals('function', $executeCommand['type']);
        $this->assertEquals('execute_command', $executeCommand['function']['name']);
    }

    #[Test]
    public function it_returns_intent_parsing_schema_for_structured_output(): void
    {
        $schema = ToolDefinitions::intentParsingSchema();

        $this->assertEquals('json_schema', $schema['type']);
        $this->assertEquals('intent_result', $schema['json_schema']['name']);
        $this->assertTrue($schema['json_schema']['strict']);

        $properties = $schema['json_schema']['schema']['properties'];
        $this->assertArrayHasKey('intent', $properties);
        $this->assertArrayHasKey('confidence', $properties);
        $this->assertArrayHasKey('response_text', $properties);
    }

    #[Test]
    public function it_includes_all_intent_types_in_schema(): void
    {
        $expectedIntents = ['deploy', 'restart', 'stop', 'start', 'logs', 'status', 'help', 'none'];

        $this->assertEquals($expectedIntents, ToolDefinitions::INTENT_TYPES);
    }

    #[Test]
    public function it_includes_all_resource_types(): void
    {
        $expectedTypes = ['application', 'service', 'database', 'server'];

        $this->assertEquals($expectedTypes, ToolDefinitions::RESOURCE_TYPES);
    }

    #[Test]
    public function it_returns_parse_intent_only_tools(): void
    {
        $anthropicTools = ToolDefinitions::parseIntentOnlyAnthropic();
        $this->assertCount(1, $anthropicTools);
        $this->assertEquals('parse_intent', $anthropicTools[0]['name']);

        $openaiTools = ToolDefinitions::parseIntentOnlyOpenAI();
        $this->assertCount(1, $openaiTools);
        $this->assertEquals('parse_intent', $openaiTools[0]['function']['name']);
    }

    #[Test]
    public function anthropic_tool_schema_has_valid_json_schema(): void
    {
        $tools = ToolDefinitions::forAnthropic();
        $parseIntent = $tools[0];

        $schema = $parseIntent['input_schema'];

        // Validate schema structure
        $this->assertEquals('object', $schema['type']);
        $this->assertIsArray($schema['properties']);

        // Check intent enum values
        $intentProperty = $schema['properties']['intent'];
        $this->assertEquals('string', $intentProperty['type']);
        $this->assertContains('deploy', $intentProperty['enum']);
        $this->assertContains('none', $intentProperty['enum']);

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
        $parseIntent = $tools[0]['function'];

        // All properties should be in required for strict mode
        $required = $parseIntent['parameters']['required'];
        $properties = array_keys($parseIntent['parameters']['properties']);

        foreach ($properties as $property) {
            $this->assertContains($property, $required, "Property {$property} should be required for strict mode");
        }
    }
}
