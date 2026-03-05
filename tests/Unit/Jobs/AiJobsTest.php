<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExecuteAiCommandJob;
use App\Jobs\ProcessAiChatMessageJob;
use App\Models\AiChatMessage;
use App\Models\AiChatSession;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for AI-related Jobs: ProcessAiChatMessageJob, ExecuteAiCommandJob.
 */
class AiJobsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // =========================================================================
    // ProcessAiChatMessageJob
    // =========================================================================

    /** @test */
    public function process_ai_chat_message_has_single_try(): void
    {
        $session = Mockery::mock(AiChatSession::class)->makePartial()->shouldIgnoreMissing();
        $job = new ProcessAiChatMessageJob($session, 'hello', true);

        $this->assertEquals(1, $job->tries);
    }

    /** @test */
    public function process_ai_chat_message_has_120s_timeout(): void
    {
        $session = Mockery::mock(AiChatSession::class)->makePartial()->shouldIgnoreMissing();
        $job = new ProcessAiChatMessageJob($session, 'hello');

        $this->assertEquals(120, $job->timeout);
    }

    /** @test */
    public function process_ai_chat_message_stores_constructor_params(): void
    {
        $session = Mockery::mock(AiChatSession::class)->makePartial()->shouldIgnoreMissing();
        $job = new ProcessAiChatMessageJob($session, 'test content', false);

        $this->assertSame($session, $job->session);
        $this->assertEquals('test content', $job->content);
        $this->assertFalse($job->executeCommands);
    }

    /** @test */
    public function process_ai_chat_message_defaults_execute_commands_to_true(): void
    {
        $session = Mockery::mock(AiChatSession::class)->makePartial()->shouldIgnoreMissing();
        $job = new ProcessAiChatMessageJob($session, 'hello');

        $this->assertTrue($job->executeCommands);
    }

    /** @test */
    public function process_ai_chat_message_source_uses_chat_service(): void
    {
        $source = file_get_contents(app_path('Jobs/ProcessAiChatMessageJob.php'));

        $this->assertStringContainsString('AiChatService $chatService', $source);
        $this->assertStringContainsString('$chatService->sendMessage(', $source);
    }

    /** @test */
    public function process_ai_chat_message_source_creates_error_message_on_failure(): void
    {
        $source = file_get_contents(app_path('Jobs/ProcessAiChatMessageJob.php'));

        $this->assertStringContainsString('$this->session->messages()->create(', $source);
        $this->assertStringContainsString("'role' => 'assistant'", $source);
        $this->assertStringContainsString('Sorry, I encountered an error', $source);
    }

    /** @test */
    public function process_ai_chat_message_source_broadcasts_error(): void
    {
        $source = file_get_contents(app_path('Jobs/ProcessAiChatMessageJob.php'));

        $this->assertStringContainsString('broadcast(new AiChatMessageReceived(', $source);
    }

    /** @test */
    public function process_ai_chat_message_source_rethrows_exception(): void
    {
        $source = file_get_contents(app_path('Jobs/ProcessAiChatMessageJob.php'));

        $this->assertStringContainsString('throw $e;', $source);
    }

    // =========================================================================
    // ExecuteAiCommandJob
    // =========================================================================

    /** @test */
    public function execute_ai_command_has_retry_config(): void
    {
        $session = Mockery::mock(AiChatSession::class)->makePartial()->shouldIgnoreMissing();
        $message = Mockery::mock(AiChatMessage::class)->makePartial()->shouldIgnoreMissing();
        $job = new ExecuteAiCommandJob($session, $message, 'restart', ['app_id' => 1]);

        $this->assertEquals(2, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    /** @test */
    public function execute_ai_command_stores_constructor_params(): void
    {
        $session = Mockery::mock(AiChatSession::class)->makePartial()->shouldIgnoreMissing();
        $message = Mockery::mock(AiChatMessage::class)->makePartial()->shouldIgnoreMissing();
        $job = new ExecuteAiCommandJob($session, $message, 'deploy', ['env' => 'prod']);

        $this->assertSame($session, $job->session);
        $this->assertSame($message, $job->message);
        $this->assertEquals('deploy', $job->intent);
        $this->assertEquals(['env' => 'prod'], $job->params);
    }

    /** @test */
    public function execute_ai_command_source_creates_intent_result(): void
    {
        $source = file_get_contents(app_path('Jobs/ExecuteAiCommandJob.php'));

        $this->assertStringContainsString('new IntentResult(', $source);
        $this->assertStringContainsString('intent: $this->intent', $source);
        $this->assertStringContainsString('params: $this->params', $source);
        $this->assertStringContainsString('confidence: 1.0', $source);
    }

    /** @test */
    public function execute_ai_command_source_uses_command_executor(): void
    {
        $source = file_get_contents(app_path('Jobs/ExecuteAiCommandJob.php'));

        $this->assertStringContainsString('new CommandExecutor(', $source);
        $this->assertStringContainsString('$executor->execute($intentResult)', $source);
    }

    /** @test */
    public function execute_ai_command_source_updates_message_status(): void
    {
        $source = file_get_contents(app_path('Jobs/ExecuteAiCommandJob.php'));

        $this->assertStringContainsString("updateCommandStatus('executing')", $source);
        $this->assertStringContainsString('$result->success', $source);
        $this->assertStringContainsString("'completed'", $source);
        $this->assertStringContainsString("'failed'", $source);
    }

    /** @test */
    public function execute_ai_command_source_broadcasts_result(): void
    {
        $source = file_get_contents(app_path('Jobs/ExecuteAiCommandJob.php'));

        $this->assertStringContainsString('broadcast(new AiCommandExecuted(', $source);
        $this->assertStringContainsString('success: $result->success', $source);
        $this->assertStringContainsString('result: $result->message', $source);
    }

    /** @test */
    public function execute_ai_command_source_handles_failure_with_broadcast(): void
    {
        $source = file_get_contents(app_path('Jobs/ExecuteAiCommandJob.php'));

        // Check that error is broadcast on failure
        $this->assertStringContainsString("updateCommandStatus('failed', \$e->getMessage())", $source);
        $this->assertStringContainsString('Command execution failed:', $source);
    }
}
