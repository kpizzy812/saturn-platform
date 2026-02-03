<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Services\AI\Chat\DTOs\ToolCall;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ToolCallTest extends TestCase
{
    #[Test]
    public function it_creates_tool_call_with_all_properties(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'parse_intent',
            arguments: ['intent' => 'deploy', 'confidence' => 0.95],
            type: 'function',
        );

        $this->assertEquals('call_123', $toolCall->id);
        $this->assertEquals('parse_intent', $toolCall->name);
        $this->assertEquals(['intent' => 'deploy', 'confidence' => 0.95], $toolCall->arguments);
        $this->assertEquals('function', $toolCall->type);
    }

    #[Test]
    public function it_creates_from_anthropic_tool_use_block(): void
    {
        $anthropicData = [
            'id' => 'toolu_abc123',
            'type' => 'tool_use',
            'name' => 'parse_intent',
            'input' => [
                'intent' => 'restart',
                'confidence' => 0.9,
                'resource_type' => 'application',
                'response_text' => 'Restarting the application...',
            ],
        ];

        $toolCall = ToolCall::fromAnthropic($anthropicData);

        $this->assertEquals('toolu_abc123', $toolCall->id);
        $this->assertEquals('parse_intent', $toolCall->name);
        $this->assertEquals('tool_use', $toolCall->type);
        $this->assertEquals('restart', $toolCall->arguments['intent']);
        $this->assertEquals(0.9, $toolCall->arguments['confidence']);
    }

    #[Test]
    public function it_creates_from_openai_function_call(): void
    {
        $openaiData = [
            'id' => 'call_xyz789',
            'type' => 'function',
            'function' => [
                'name' => 'parse_intent',
                'arguments' => json_encode([
                    'intent' => 'deploy',
                    'confidence' => 0.85,
                    'resource_name' => 'my-app',
                    'response_text' => 'Deploying my-app...',
                ]),
            ],
        ];

        $toolCall = ToolCall::fromOpenAI($openaiData);

        $this->assertEquals('call_xyz789', $toolCall->id);
        $this->assertEquals('parse_intent', $toolCall->name);
        $this->assertEquals('function', $toolCall->type);
        $this->assertEquals('deploy', $toolCall->arguments['intent']);
        $this->assertEquals(0.85, $toolCall->arguments['confidence']);
        $this->assertEquals('my-app', $toolCall->arguments['resource_name']);
    }

    #[Test]
    public function it_gets_argument_by_key(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'test',
            arguments: ['foo' => 'bar', 'count' => 42],
        );

        $this->assertEquals('bar', $toolCall->getArgument('foo'));
        $this->assertEquals(42, $toolCall->getArgument('count'));
        $this->assertNull($toolCall->getArgument('missing'));
        $this->assertEquals('default', $toolCall->getArgument('missing', 'default'));
    }

    #[Test]
    public function it_checks_if_is_specific_tool(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'parse_intent',
            arguments: [],
        );

        $this->assertTrue($toolCall->is('parse_intent'));
        $this->assertFalse($toolCall->is('execute_command'));
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'parse_intent',
            arguments: ['intent' => 'status'],
            type: 'function',
        );

        $array = $toolCall->toArray();

        $this->assertEquals('call_123', $array['id']);
        $this->assertEquals('parse_intent', $array['name']);
        $this->assertEquals(['intent' => 'status'], $array['arguments']);
        $this->assertEquals('function', $array['type']);
    }

    #[Test]
    public function it_handles_empty_arguments_from_openai(): void
    {
        $openaiData = [
            'id' => 'call_empty',
            'function' => [
                'name' => 'test_tool',
                'arguments' => '{}',
            ],
        ];

        $toolCall = ToolCall::fromOpenAI($openaiData);

        $this->assertEquals([], $toolCall->arguments);
    }

    #[Test]
    public function it_handles_invalid_json_arguments_from_openai(): void
    {
        $openaiData = [
            'id' => 'call_invalid',
            'function' => [
                'name' => 'test_tool',
                'arguments' => 'not valid json',
            ],
        ];

        $toolCall = ToolCall::fromOpenAI($openaiData);

        $this->assertEquals([], $toolCall->arguments);
    }

    #[Test]
    public function it_handles_missing_fields_from_anthropic(): void
    {
        $anthropicData = [
            'name' => 'test_tool',
            'input' => ['key' => 'value'],
        ];

        $toolCall = ToolCall::fromAnthropic($anthropicData);

        $this->assertStringStartsWith('tool_', $toolCall->id);
        $this->assertEquals('test_tool', $toolCall->name);
        $this->assertEquals(['key' => 'value'], $toolCall->arguments);
    }

    #[Test]
    public function it_defaults_type_to_function(): void
    {
        $toolCall = new ToolCall(
            id: 'call_123',
            name: 'test',
            arguments: [],
        );

        $this->assertEquals('function', $toolCall->type);
    }
}
