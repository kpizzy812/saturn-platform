<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Services\AI\Chat\DTOs\ChatResponse;
use App\Services\AI\Chat\DTOs\ToolCall;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChatResponseTest extends TestCase
{
    #[Test]
    public function it_creates_successful_response(): void
    {
        $response = ChatResponse::success(
            content: 'Hello, world!',
            provider: 'openai',
            model: 'gpt-4o-mini',
            inputTokens: 10,
            outputTokens: 5,
            stopReason: 'stop',
        );

        $this->assertTrue($response->success);
        $this->assertEquals('Hello, world!', $response->content);
        $this->assertEquals('openai', $response->provider);
        $this->assertEquals('gpt-4o-mini', $response->model);
        $this->assertEquals(10, $response->inputTokens);
        $this->assertEquals(5, $response->outputTokens);
        $this->assertEquals('stop', $response->stopReason);
        $this->assertNull($response->error);
    }

    #[Test]
    public function it_creates_failed_response(): void
    {
        $response = ChatResponse::failed(
            error: 'API rate limit exceeded',
            provider: 'claude',
            model: 'claude-sonnet-4-20250514',
        );

        $this->assertFalse($response->success);
        $this->assertEquals('', $response->content);
        $this->assertEquals('API rate limit exceeded', $response->error);
        $this->assertEquals('claude', $response->provider);
    }

    #[Test]
    public function it_calculates_total_tokens(): void
    {
        $response = ChatResponse::success(
            content: 'Test',
            provider: 'openai',
            model: 'gpt-4o-mini',
            inputTokens: 100,
            outputTokens: 50,
        );

        $this->assertEquals(150, $response->getTotalTokens());
    }

    #[Test]
    public function it_handles_tool_calls(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'parse_intent', ['intent' => 'deploy']),
            new ToolCall('call_2', 'execute_command', ['command' => 'restart']),
        ];

        $response = ChatResponse::success(
            content: '',
            provider: 'openai',
            model: 'gpt-4o-mini',
            stopReason: 'tool_calls',
            toolCalls: $toolCalls,
        );

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(2, $response->toolCalls);
    }

    #[Test]
    public function it_returns_false_when_no_tool_calls(): void
    {
        $response = ChatResponse::success(
            content: 'Just text',
            provider: 'openai',
            model: 'gpt-4o-mini',
        );

        $this->assertFalse($response->hasToolCalls());
        $this->assertEquals([], $response->toolCalls);
    }

    #[Test]
    public function it_gets_first_tool_call(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'first_tool', []),
            new ToolCall('call_2', 'second_tool', []),
        ];

        $response = ChatResponse::success(
            content: '',
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolCalls: $toolCalls,
        );

        $first = $response->getFirstToolCall();
        $this->assertInstanceOf(ToolCall::class, $first);
        $this->assertEquals('first_tool', $first->name);
    }

    #[Test]
    public function it_returns_null_when_no_first_tool_call(): void
    {
        $response = ChatResponse::success(
            content: 'Text',
            provider: 'openai',
            model: 'gpt-4o-mini',
        );

        $this->assertNull($response->getFirstToolCall());
    }

    #[Test]
    public function it_gets_tool_call_by_name(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'parse_intent', ['intent' => 'deploy']),
            new ToolCall('call_2', 'execute_command', ['command' => 'restart']),
        ];

        $response = ChatResponse::success(
            content: '',
            provider: 'openai',
            model: 'gpt-4o-mini',
            toolCalls: $toolCalls,
        );

        $parseIntent = $response->getToolCall('parse_intent');
        $this->assertInstanceOf(ToolCall::class, $parseIntent);
        $this->assertEquals('deploy', $parseIntent->arguments['intent']);

        $executeCommand = $response->getToolCall('execute_command');
        $this->assertInstanceOf(ToolCall::class, $executeCommand);
        $this->assertEquals('restart', $executeCommand->arguments['command']);

        $nonExistent = $response->getToolCall('non_existent');
        $this->assertNull($nonExistent);
    }

    #[Test]
    public function it_detects_tool_use_stop_reason(): void
    {
        // Anthropic style
        $response1 = ChatResponse::success(
            content: '',
            provider: 'claude',
            model: 'claude-sonnet-4-20250514',
            stopReason: 'tool_use',
        );

        $this->assertTrue($response1->stoppedForToolUse());

        // OpenAI style
        $response2 = ChatResponse::success(
            content: '',
            provider: 'openai',
            model: 'gpt-4o-mini',
            stopReason: 'tool_calls',
        );

        $this->assertTrue($response2->stoppedForToolUse());

        // Regular stop
        $response3 = ChatResponse::success(
            content: 'Done',
            provider: 'openai',
            model: 'gpt-4o-mini',
            stopReason: 'stop',
        );

        $this->assertFalse($response3->stoppedForToolUse());
    }

    #[Test]
    public function it_converts_to_array_with_tool_calls(): void
    {
        $toolCalls = [
            new ToolCall('call_1', 'parse_intent', ['intent' => 'deploy']),
        ];

        $response = ChatResponse::success(
            content: 'Processing...',
            provider: 'openai',
            model: 'gpt-4o-mini',
            inputTokens: 50,
            outputTokens: 25,
            stopReason: 'tool_calls',
            toolCalls: $toolCalls,
        );

        $array = $response->toArray();

        $this->assertEquals('Processing...', $array['content']);
        $this->assertEquals('openai', $array['provider']);
        $this->assertEquals('gpt-4o-mini', $array['model']);
        $this->assertEquals(50, $array['input_tokens']);
        $this->assertEquals(25, $array['output_tokens']);
        $this->assertEquals('tool_calls', $array['stop_reason']);
        $this->assertTrue($array['success']);
        $this->assertNull($array['error']);

        $this->assertCount(1, $array['tool_calls']);
        $this->assertEquals('parse_intent', $array['tool_calls'][0]['name']);
    }

    #[Test]
    public function it_converts_failed_response_to_array(): void
    {
        $response = ChatResponse::failed(
            error: 'Connection timeout',
            provider: 'claude',
            model: 'claude-sonnet-4-20250514',
        );

        $array = $response->toArray();

        $this->assertEquals('', $array['content']);
        $this->assertFalse($array['success']);
        $this->assertEquals('Connection timeout', $array['error']);
        $this->assertEquals([], $array['tool_calls']);
    }
}
