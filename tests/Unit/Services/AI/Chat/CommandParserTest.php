<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Services\AI\Chat\CommandParser;
use App\Services\AI\Chat\DTOs\ParsedCommand;
use App\Services\AI\Chat\DTOs\ParsedIntent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CommandParserTest extends TestCase
{
    private CommandParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new CommandParser;
    }

    #[Test]
    public function it_extracts_resource_name_from_message(): void
    {
        $info = $this->parser->extractResourceInfo('deploy my-awesome-app');

        $this->assertEquals('my-awesome-app', $info['name']);
    }

    #[Test]
    public function it_extracts_resource_name_from_russian_message(): void
    {
        $info = $this->parser->extractResourceInfo('деплой my-app');

        $this->assertEquals('my-app', $info['name']);
    }

    #[Test]
    public function it_extracts_project_and_environment_from_message(): void
    {
        $info = $this->parser->extractResourceInfo('deploy my-app in my-project/production');

        $this->assertEquals('my-app', $info['name']);
        $this->assertEquals('my-project', $info['project']);
        $this->assertEquals('production', $info['environment']);
    }

    #[Test]
    public function it_extracts_only_project_when_no_environment(): void
    {
        $info = $this->parser->extractResourceInfo('deploy my-app in my-project');

        $this->assertEquals('my-app', $info['name']);
        $this->assertEquals('my-project', $info['project']);
        $this->assertNull($info['environment']);
    }

    #[Test]
    public function it_extracts_project_and_environment_in_russian(): void
    {
        $info = $this->parser->extractResourceInfo('деплой my-app в my-project/staging');

        $this->assertEquals('my-app', $info['name']);
        $this->assertEquals('my-project', $info['project']);
        $this->assertEquals('staging', $info['environment']);
    }

    #[Test]
    public function it_extracts_delete_command_resource(): void
    {
        $info = $this->parser->extractResourceInfo('delete test-project');

        $this->assertEquals('test-project', $info['name']);
    }

    #[Test]
    public function it_extracts_russian_delete_command_resource(): void
    {
        $info = $this->parser->extractResourceInfo('удали my-project');

        $this->assertEquals('my-project', $info['name']);
    }

    #[Test]
    public function parsed_command_is_actionable(): void
    {
        $command = new ParsedCommand('deploy', 'application', 'my-app');

        $this->assertTrue($command->isActionable());
        $this->assertTrue($command->isDangerous());
        $this->assertTrue($command->hasResource());
    }

    #[Test]
    public function parsed_command_restart_is_not_dangerous(): void
    {
        $command = new ParsedCommand('restart', 'application', 'my-app');

        $this->assertTrue($command->isActionable());
        $this->assertFalse($command->isDangerous());
    }

    #[Test]
    public function parsed_command_delete_is_dangerous(): void
    {
        $command = new ParsedCommand('delete', 'project', 'test-project');

        $this->assertTrue($command->isActionable());
        $this->assertTrue($command->isDangerous());
    }

    #[Test]
    public function parsed_command_none_is_not_actionable(): void
    {
        $command = new ParsedCommand('none');

        $this->assertFalse($command->isActionable());
        $this->assertFalse($command->isDangerous());
        $this->assertFalse($command->hasResource());
    }

    #[Test]
    public function parsed_intent_with_multiple_commands(): void
    {
        $commands = [
            new ParsedCommand('restart', 'application', 'app1'),
            new ParsedCommand('restart', 'application', 'app2'),
            new ParsedCommand('restart', 'database', 'db1'),
        ];

        $intent = new ParsedIntent($commands, 0.9);

        $this->assertTrue($intent->hasCommands());
        $this->assertTrue($intent->hasMultipleCommands());
        $this->assertCount(3, $intent->commands);
        $this->assertEquals('restart', $intent->getFirstCommand()->action);
    }

    #[Test]
    public function parsed_intent_detects_dangerous_commands(): void
    {
        $commands = [
            new ParsedCommand('restart', 'application', 'app1'),
            new ParsedCommand('delete', 'project', 'test-project'),
        ];

        $intent = new ParsedIntent($commands, 0.9);

        $this->assertTrue($intent->hasDangerousCommands());
        $this->assertCount(1, $intent->getDangerousCommands());
    }

    #[Test]
    public function parsed_intent_from_ai_response(): void
    {
        $data = [
            'commands' => [
                ['action' => 'restart', 'resource_type' => 'application', 'resource_name' => 'app1'],
                ['action' => 'restart', 'resource_type' => 'application', 'resource_name' => 'app2'],
            ],
            'confidence' => 0.95,
            'response_text' => 'Перезапускаю приложения app1 и app2.',
        ];

        $intent = ParsedIntent::fromAIResponse($data);

        $this->assertTrue($intent->hasCommands());
        $this->assertCount(2, $intent->commands);
        $this->assertEquals('Перезапускаю приложения app1 и app2.', $intent->responseText);
        $this->assertEquals(0.95, $intent->confidence);
    }

    #[Test]
    public function parsed_intent_from_ai_response_with_dangerous_commands(): void
    {
        $data = [
            'commands' => [
                ['action' => 'delete', 'resource_type' => 'project', 'resource_name' => 'test-project'],
            ],
            'confidence' => 0.9,
            'response_text' => 'Удаляю проект test-project.',
        ];

        $intent = ParsedIntent::fromAIResponse($data);

        $this->assertTrue($intent->requiresConfirmation);
        $this->assertNotNull($intent->confirmationMessage);
        $this->assertStringContainsString('delete', $intent->confirmationMessage);
    }

    #[Test]
    public function parsed_command_to_array(): void
    {
        $command = new ParsedCommand(
            action: 'deploy',
            resourceType: 'application',
            resourceName: 'my-app',
            projectName: 'my-project',
            environmentName: 'production',
        );

        $array = $command->toArray();

        $this->assertEquals('deploy', $array['action']);
        $this->assertEquals('application', $array['resource_type']);
        $this->assertEquals('my-app', $array['resource_name']);
        $this->assertEquals('my-project', $array['project_name']);
        $this->assertEquals('production', $array['environment_name']);
    }

    #[Test]
    public function parsed_command_from_array(): void
    {
        $data = [
            'action' => 'restart',
            'resource_type' => 'database',
            'resource_name' => 'my-db',
        ];

        $command = ParsedCommand::fromArray($data);

        $this->assertEquals('restart', $command->action);
        $this->assertEquals('database', $command->resourceType);
        $this->assertEquals('my-db', $command->resourceName);
    }
}
