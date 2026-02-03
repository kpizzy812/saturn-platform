<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Services\AI\Chat\CommandParser;
use App\Services\AI\Chat\DTOs\IntentResult;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('deployIntentProvider')]
    public function it_parses_deploy_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('deploy', $result->intent);
        $this->assertTrue($result->requiresConfirmation);
    }

    public static function deployIntentProvider(): array
    {
        return [
            ['deploy'],
            ['Deploy'],
            ['DEPLOY'],
            ['deploy my-app'],
            ['деплой'],
            ['задеплой'],
            ['разверни'],
            ['redeploy'],
        ];
    }

    #[Test]
    #[DataProvider('restartIntentProvider')]
    public function it_parses_restart_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('restart', $result->intent);
        $this->assertFalse($result->requiresConfirmation);
    }

    public static function restartIntentProvider(): array
    {
        return [
            ['restart'],
            ['Restart'],
            ['restart my-service'],
            ['перезапусти'],
            ['рестарт'],
            ['reboot'],
        ];
    }

    #[Test]
    #[DataProvider('stopIntentProvider')]
    public function it_parses_stop_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('stop', $result->intent);
        $this->assertTrue($result->requiresConfirmation);
    }

    public static function stopIntentProvider(): array
    {
        return [
            ['stop'],
            ['Stop'],
            ['stop my-app'],
            ['останови'],
            ['стоп'],
            ['выключи'],
        ];
    }

    #[Test]
    #[DataProvider('startIntentProvider')]
    public function it_parses_start_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('start', $result->intent);
        $this->assertFalse($result->requiresConfirmation);
    }

    public static function startIntentProvider(): array
    {
        return [
            ['start'],
            ['Start'],
            ['start my-app'],
            ['запусти'],
            ['старт'],
            ['включи'],
        ];
    }

    #[Test]
    #[DataProvider('logsIntentProvider')]
    public function it_parses_logs_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('logs', $result->intent);
        $this->assertFalse($result->requiresConfirmation);
    }

    public static function logsIntentProvider(): array
    {
        return [
            ['logs'],
            ['Logs'],
            ['log'],
            ['логи'],
            ['лог'],
            ['покажи логи'],
            ['show logs'],
        ];
    }

    #[Test]
    #[DataProvider('statusIntentProvider')]
    public function it_parses_status_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('status', $result->intent);
        $this->assertFalse($result->requiresConfirmation);
    }

    public static function statusIntentProvider(): array
    {
        return [
            ['status'],
            ['Status'],
            ['статус'],
            ['состояние'],
            ['state'],
        ];
    }

    #[Test]
    #[DataProvider('helpIntentProvider')]
    public function it_parses_help_intents(string $message): void
    {
        $result = $this->parser->parse($message);

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertTrue($result->hasIntent());
        $this->assertEquals('help', $result->intent);
        $this->assertFalse($result->requiresConfirmation);
    }

    public static function helpIntentProvider(): array
    {
        return [
            ['help'],
            ['Help'],
            ['помощь'],
            ['помоги'],
            ['что ты умеешь'],
            ['что ты можешь'],
        ];
    }

    #[Test]
    public function it_returns_no_intent_for_generic_messages(): void
    {
        $result = $this->parser->parse('Hello, how are you?');

        $this->assertInstanceOf(IntentResult::class, $result);
        $this->assertFalse($result->hasIntent());
        $this->assertNull($result->intent);
    }

    #[Test]
    public function it_extracts_resource_name_from_message(): void
    {
        $result = $this->parser->parse('deploy my-awesome-app');

        $this->assertTrue($result->hasIntent());
        $this->assertEquals('deploy', $result->intent);
        $this->assertEquals('my-awesome-app', $result->params['resource_name'] ?? null);
    }

    #[Test]
    public function it_uses_context_when_provided(): void
    {
        $context = [
            'type' => 'application',
            'id' => 123,
            'name' => 'test-app',
            'uuid' => 'abc-123',
        ];

        $result = $this->parser->parse('deploy', $context);

        $this->assertTrue($result->hasIntent());
        $this->assertEquals('deploy', $result->intent);
        $this->assertEquals('application', $result->params['resource_type'] ?? null);
        $this->assertEquals(123, $result->params['resource_id'] ?? null);
        $this->assertEquals('abc-123', $result->params['resource_uuid'] ?? null);
    }

    #[Test]
    public function it_generates_confirmation_message_for_dangerous_intents(): void
    {
        $context = [
            'type' => 'application',
            'id' => 123,
            'name' => 'my-app',
        ];

        $result = $this->parser->parse('deploy', $context);

        $this->assertTrue($result->requiresConfirmation);
        $this->assertNotNull($result->confirmationMessage);
        $this->assertStringContainsString('my-app', $result->confirmationMessage);
    }
}
