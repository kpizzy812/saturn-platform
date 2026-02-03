<?php

namespace Tests\Unit\Services\AI\Chat;

use App\Services\AI\Chat\DTOs\IntentResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IntentResultTest extends TestCase
{
    #[Test]
    public function it_creates_intent_result_with_all_properties(): void
    {
        $result = new IntentResult(
            intent: 'deploy',
            params: ['resource_type' => 'application', 'resource_id' => 123],
            confidence: 0.95,
            requiresConfirmation: true,
            confirmationMessage: 'Are you sure?',
            responseText: 'Deploying...',
        );

        $this->assertEquals('deploy', $result->intent);
        $this->assertEquals(['resource_type' => 'application', 'resource_id' => 123], $result->params);
        $this->assertEquals(0.95, $result->confidence);
        $this->assertTrue($result->requiresConfirmation);
        $this->assertEquals('Are you sure?', $result->confirmationMessage);
        $this->assertEquals('Deploying...', $result->responseText);
    }

    #[Test]
    public function has_intent_returns_true_when_intent_exists(): void
    {
        $result = new IntentResult(intent: 'deploy');

        $this->assertTrue($result->hasIntent());
    }

    #[Test]
    public function has_intent_returns_false_when_intent_is_null(): void
    {
        $result = new IntentResult(intent: null);

        $this->assertFalse($result->hasIntent());
    }

    #[Test]
    public function is_actionable_returns_true_for_actionable_intents(): void
    {
        $actionableIntents = ['deploy', 'restart', 'stop', 'start', 'logs', 'status'];

        foreach ($actionableIntents as $intent) {
            $result = new IntentResult(intent: $intent);
            $this->assertTrue($result->isActionable(), "Intent '{$intent}' should be actionable");
        }
    }

    #[Test]
    public function is_actionable_returns_false_for_non_actionable_intents(): void
    {
        $nonActionableIntents = ['help', 'unknown', null];

        foreach ($nonActionableIntents as $intent) {
            $result = new IntentResult(intent: $intent);
            $this->assertFalse($result->isActionable(), "Intent '{$intent}' should not be actionable");
        }
    }

    #[Test]
    public function is_dangerous_returns_true_for_dangerous_intents(): void
    {
        $dangerousIntents = ['deploy', 'stop', 'delete'];

        foreach ($dangerousIntents as $intent) {
            $result = new IntentResult(intent: $intent);
            $this->assertTrue($result->isDangerous(), "Intent '{$intent}' should be dangerous");
        }
    }

    #[Test]
    public function is_dangerous_returns_false_for_safe_intents(): void
    {
        $safeIntents = ['restart', 'start', 'logs', 'status', 'help'];

        foreach ($safeIntents as $intent) {
            $result = new IntentResult(intent: $intent);
            $this->assertFalse($result->isDangerous(), "Intent '{$intent}' should not be dangerous");
        }
    }

    #[Test]
    public function get_resource_type_returns_correct_value(): void
    {
        $result = new IntentResult(
            intent: 'deploy',
            params: ['resource_type' => 'application'],
        );

        $this->assertEquals('application', $result->getResourceType());
    }

    #[Test]
    public function get_resource_type_returns_null_when_not_set(): void
    {
        $result = new IntentResult(intent: 'deploy');

        $this->assertNull($result->getResourceType());
    }

    #[Test]
    public function get_resource_id_returns_correct_value(): void
    {
        $result = new IntentResult(
            intent: 'deploy',
            params: ['resource_id' => 123],
        );

        $this->assertEquals(123, $result->getResourceId());
    }

    #[Test]
    public function get_resource_uuid_returns_correct_value(): void
    {
        $result = new IntentResult(
            intent: 'deploy',
            params: ['resource_uuid' => 'abc-123'],
        );

        $this->assertEquals('abc-123', $result->getResourceUuid());
    }

    #[Test]
    public function none_creates_empty_intent_result(): void
    {
        $result = IntentResult::none();

        $this->assertFalse($result->hasIntent());
        $this->assertNull($result->intent);
        $this->assertNull($result->responseText);
    }

    #[Test]
    public function none_creates_result_with_response_text(): void
    {
        $result = IntentResult::none('I can help you with that.');

        $this->assertFalse($result->hasIntent());
        $this->assertEquals('I can help you with that.', $result->responseText);
    }

    #[Test]
    public function with_confirmation_creates_intent_requiring_confirmation(): void
    {
        $result = IntentResult::withConfirmation(
            intent: 'deploy',
            params: ['resource_id' => 123],
            confirmationMessage: 'Are you sure you want to deploy?',
            confidence: 0.9,
        );

        $this->assertTrue($result->hasIntent());
        $this->assertEquals('deploy', $result->intent);
        $this->assertTrue($result->requiresConfirmation);
        $this->assertEquals('Are you sure you want to deploy?', $result->confirmationMessage);
        $this->assertEquals(0.9, $result->confidence);
    }

    #[Test]
    public function to_array_returns_all_properties(): void
    {
        $result = new IntentResult(
            intent: 'deploy',
            params: ['resource_type' => 'application'],
            confidence: 0.95,
            requiresConfirmation: true,
            confirmationMessage: 'Confirm?',
            responseText: 'Deploying...',
        );

        $array = $result->toArray();

        $this->assertEquals('deploy', $array['intent']);
        $this->assertEquals(['resource_type' => 'application'], $array['params']);
        $this->assertEquals(0.95, $array['confidence']);
        $this->assertTrue($array['requires_confirmation']);
        $this->assertEquals('Confirm?', $array['confirmation_message']);
        $this->assertEquals('Deploying...', $array['response_text']);
    }
}
