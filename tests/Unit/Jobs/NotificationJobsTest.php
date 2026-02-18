<?php

use App\Jobs\SendMessageToDiscordJob;
use App\Jobs\SendMessageToSlackJob;
use App\Jobs\SendMessageToTelegramJob;
use App\Notifications\Dto\DiscordMessage;
use App\Notifications\Dto\SlackMessage;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// ---------------------------------------------------------------------------
// SendMessageToDiscordJob
// ---------------------------------------------------------------------------

it('discord job has correct tries, backoff, and maxExceptions', function () {
    $reflection = new ReflectionClass(SendMessageToDiscordJob::class);
    $defaults = $reflection->getDefaultProperties();

    expect($defaults['tries'])->toBe(5)
        ->and($defaults['backoff'])->toBe(10)
        ->and($defaults['maxExceptions'])->toBe(5);
});

it('discord job implements ShouldBeEncrypted and ShouldQueue', function () {
    $message = new DiscordMessage('Title', 'Desc', DiscordMessage::infoColor());
    $job = new SendMessageToDiscordJob($message, 'https://discord.test/webhook');

    expect($job)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($job)->toBeInstanceOf(ShouldQueue::class);
});

it('discord job is dispatched to the high queue', function () {
    $message = new DiscordMessage('Title', 'Desc', DiscordMessage::infoColor());
    $job = new SendMessageToDiscordJob($message, 'https://discord.test/webhook');

    // onQueue() stores the name in the $queue property provided by Queueable
    expect($job->queue)->toBe('high');
});

it('discord job handle posts to the provided webhook URL', function () {
    Http::fake();

    $webhookUrl = 'https://discord.test/api/webhooks/123/abc';
    $message = new DiscordMessage('Deploy OK', 'App deployed', DiscordMessage::successColor());
    $job = new SendMessageToDiscordJob($message, $webhookUrl);

    $job->handle();

    Http::assertSent(fn (Request $request) => $request->url() === $webhookUrl);
});

it('discord job posts the payload returned by DiscordMessage->toPayload()', function () {
    Http::fake();

    $webhookUrl = 'https://discord.test/api/webhooks/456/xyz';
    $message = new DiscordMessage('Test', 'Body text', DiscordMessage::errorColor());
    $expectedPayload = $message->toPayload();

    $job = new SendMessageToDiscordJob($message, $webhookUrl);
    $job->handle();

    Http::assertSent(function (Request $request) use ($expectedPayload) {
        $body = $request->data();

        // Top-level keys must match
        return isset($body['embeds'])
            && $body['embeds'][0]['title'] === $expectedPayload['embeds'][0]['title']
            && $body['embeds'][0]['description'] === $expectedPayload['embeds'][0]['description']
            && $body['embeds'][0]['color'] === $expectedPayload['embeds'][0]['color'];
    });
});

it('discord job sends exactly one HTTP request per handle call', function () {
    Http::fake();

    $message = new DiscordMessage('Single', 'Once', DiscordMessage::warningColor());
    $job = new SendMessageToDiscordJob($message, 'https://discord.test/webhook');

    $job->handle();

    Http::assertSentCount(1);
});

// ---------------------------------------------------------------------------
// SendMessageToSlackJob
// ---------------------------------------------------------------------------

it('slack job implements ShouldQueue but NOT ShouldBeEncrypted', function () {
    $message = new SlackMessage('Alert', 'Something happened');
    $job = new SendMessageToSlackJob($message, 'https://hooks.slack.test/services/T/B/X');

    expect($job)->toBeInstanceOf(ShouldQueue::class)
        ->and($job)->not->toBeInstanceOf(ShouldBeEncrypted::class);
});

it('slack job is dispatched to the high queue', function () {
    $message = new SlackMessage('Alert', 'Something happened');
    $job = new SendMessageToSlackJob($message, 'https://hooks.slack.test/services/T/B/X');

    expect($job->queue)->toBe('high');
});

it('slack job handle posts to the provided webhook URL with correct block format', function () {
    Http::fake();

    $webhookUrl = 'https://hooks.slack.test/services/T/B/X';
    $message = new SlackMessage('Deployment', 'App v1.2 deployed', SlackMessage::successColor());
    $job = new SendMessageToSlackJob($message, $webhookUrl);

    $job->handle();

    Http::assertSent(function (Request $request) use ($webhookUrl) {
        $body = $request->data();

        return $request->url() === $webhookUrl
            && isset($body['text'])
            && isset($body['blocks'])
            && isset($body['attachments']);
    });
});

it('slack job payload contains the color from SlackMessage', function () {
    Http::fake();

    $webhookUrl = 'https://hooks.slack.test/services/T/B/Y';
    $color = SlackMessage::errorColor();
    $message = new SlackMessage('Error', 'Something broke', $color);
    $job = new SendMessageToSlackJob($message, $webhookUrl);

    $job->handle();

    Http::assertSent(function (Request $request) use ($color) {
        $body = $request->data();
        $attachmentColor = $body['attachments'][0]['color'] ?? null;

        return $attachmentColor === $color;
    });
});

it('slack job payload text field contains the message title', function () {
    Http::fake();

    $webhookUrl = 'https://hooks.slack.test/services/T/B/Z';
    $title = 'Build Succeeded';
    $message = new SlackMessage($title, 'All checks passed');
    $job = new SendMessageToSlackJob($message, $webhookUrl);

    $job->handle();

    Http::assertSent(function (Request $request) use ($title) {
        return $request->data()['text'] === $title;
    });
});

// ---------------------------------------------------------------------------
// SendMessageToTelegramJob
// ---------------------------------------------------------------------------

it('telegram job has correct tries and maxExceptions', function () {
    $reflection = new ReflectionClass(SendMessageToTelegramJob::class);
    $defaults = $reflection->getDefaultProperties();

    expect($defaults['tries'])->toBe(5)
        ->and($defaults['maxExceptions'])->toBe(3);
});

it('telegram job implements ShouldBeEncrypted and ShouldQueue', function () {
    $job = new SendMessageToTelegramJob(
        text: 'Hello',
        buttons: [],
        token: 'bot-token',
        chatId: '12345',
    );

    expect($job)->toBeInstanceOf(ShouldBeEncrypted::class)
        ->and($job)->toBeInstanceOf(ShouldQueue::class);
});

it('telegram job is dispatched to the high queue', function () {
    $job = new SendMessageToTelegramJob(
        text: 'Hello',
        buttons: [],
        token: 'bot-token',
        chatId: '12345',
    );

    expect($job->queue)->toBe('high');
});

it('telegram job handle posts to the correct Telegram API URL', function () {
    $token = 'my-secret-token';
    $expectedUrl = "https://api.telegram.org/bot{$token}/sendMessage";

    Http::fake([$expectedUrl => Http::response(['ok' => true], 200)]);

    $job = new SendMessageToTelegramJob(
        text: 'Deployment finished',
        buttons: [],
        token: $token,
        chatId: '-100123456',
    );
    $job->handle();

    Http::assertSent(fn (Request $request) => $request->url() === $expectedUrl);
});

it('telegram job replaces http://localhost in button URLs with app url', function () {
    $token = 'tok';
    $appUrl = config('app.url');

    Http::fake(["https://api.telegram.org/bot{$token}/sendMessage" => Http::response(['ok' => true], 200)]);

    $job = new SendMessageToTelegramJob(
        text: 'Go here',
        buttons: [['text' => 'Open', 'url' => 'http://localhost/dashboard']],
        token: $token,
        chatId: '999',
    );
    $job->handle();

    Http::assertSent(function (Request $request) use ($appUrl) {
        $markup = json_decode($request->data()['reply_markup'] ?? '{}', true);
        $buttonUrl = $markup['inline_keyboard'][0][0]['url'] ?? '';

        // localhost must be replaced and the correct app url present
        return ! str_contains($buttonUrl, 'http://localhost')
            && str_contains($buttonUrl, $appUrl);
    });
});

it('telegram job includes message_thread_id when threadId is provided', function () {
    $token = 'tok2';
    $threadId = '42';

    Http::fake(["https://api.telegram.org/bot{$token}/sendMessage" => Http::response(['ok' => true], 200)]);

    $job = new SendMessageToTelegramJob(
        text: 'Hello thread',
        buttons: [],
        token: $token,
        chatId: '111',
        threadId: $threadId,
    );
    $job->handle();

    Http::assertSent(function (Request $request) use ($threadId) {
        return ($request->data()['message_thread_id'] ?? null) === $threadId;
    });
});

it('telegram job does not include message_thread_id when threadId is null', function () {
    $token = 'tok3';

    Http::fake(["https://api.telegram.org/bot{$token}/sendMessage" => Http::response(['ok' => true], 200)]);

    $job = new SendMessageToTelegramJob(
        text: 'No thread',
        buttons: [],
        token: $token,
        chatId: '222',
        threadId: null,
    );
    $job->handle();

    Http::assertSent(function (Request $request) {
        return ! array_key_exists('message_thread_id', $request->data());
    });
});

it('telegram job throws RuntimeException when API returns a failed response', function () {
    $token = 'tok4';

    // Use a dedicated fake without a prior wildcard so the 400 response takes effect
    Http::fake(['*' => Http::response(['ok' => false], 400)]);

    $job = new SendMessageToTelegramJob(
        text: 'Will fail',
        buttons: [],
        token: $token,
        chatId: '333',
    );

    expect(fn () => $job->handle())->toThrow(RuntimeException::class);
});

// ---------------------------------------------------------------------------
// DiscordMessage DTO
// ---------------------------------------------------------------------------

it('DiscordMessage successColor returns the correct green hex as integer', function () {
    expect(DiscordMessage::successColor())->toBe(hexdec('a1ffa5'));
});

it('DiscordMessage errorColor returns the correct red hex as integer', function () {
    expect(DiscordMessage::errorColor())->toBe(hexdec('ff705f'));
});

it('DiscordMessage warningColor returns the correct orange hex as integer', function () {
    expect(DiscordMessage::warningColor())->toBe(hexdec('ffa743'));
});

it('DiscordMessage infoColor returns the correct grey hex as integer', function () {
    expect(DiscordMessage::infoColor())->toBe(hexdec('4f545c'));
});

it('DiscordMessage toPayload contains embeds with title, description, and color', function () {
    $title = 'Deploy Complete';
    $description = 'All containers are running.';
    $color = DiscordMessage::successColor();

    $message = new DiscordMessage($title, $description, $color);
    $payload = $message->toPayload();

    expect($payload)->toHaveKey('embeds')
        ->and($payload['embeds'][0]['title'])->toBe($title)
        ->and($payload['embeds'][0]['description'])->toBe($description)
        ->and($payload['embeds'][0]['color'])->toBe($color);
});

it('DiscordMessage addField is chainable and fields appear in the payload', function () {
    $message = new DiscordMessage('Title', 'Desc', DiscordMessage::infoColor());

    $returned = $message
        ->addField('Server', 'prod-01', true)
        ->addField('Status', 'running');

    // Returns itself for chaining
    expect($returned)->toBe($message);

    $fields = $message->toPayload()['embeds'][0]['fields'];
    $names = array_column($fields, 'name');

    expect($names)->toContain('Server')
        ->and($names)->toContain('Status');
});

it('DiscordMessage addField inline flag is stored correctly', function () {
    $message = new DiscordMessage('T', 'D', DiscordMessage::infoColor());
    $message->addField('Env', 'production', true);
    $message->addField('Region', 'eu-west', false);

    $fields = $message->toPayload()['embeds'][0]['fields'];

    // Filter out the auto-added Time field
    $named = array_values(array_filter($fields, fn ($f) => in_array($f['name'], ['Env', 'Region'])));

    expect($named[0]['inline'])->toBeTrue()
        ->and($named[1]['inline'])->toBeFalse();
});

it('DiscordMessage critical message adds @here to content', function () {
    $message = new DiscordMessage('CRITICAL', 'System down!', DiscordMessage::errorColor(), isCritical: true);
    $payload = $message->toPayload();

    expect($payload)->toHaveKey('content')
        ->and($payload['content'])->toBe('@here');
});

it('DiscordMessage non-critical message does not include content key', function () {
    $message = new DiscordMessage('Info', 'All good', DiscordMessage::successColor(), isCritical: false);
    $payload = $message->toPayload();

    expect($payload)->not->toHaveKey('content');
});

it('DiscordMessage footer text contains "Saturn Platform"', function () {
    $message = new DiscordMessage('Title', 'Body', DiscordMessage::infoColor());
    $payload = $message->toPayload();

    $footerText = $payload['embeds'][0]['footer']['text'] ?? '';

    expect($footerText)->toContain('Saturn Platform');
});

it('DiscordMessage toPayload always appends a Time field to fields', function () {
    $message = new DiscordMessage('T', 'D', DiscordMessage::infoColor());
    $fields = $message->toPayload()['embeds'][0]['fields'];

    $names = array_column($fields, 'name');

    expect($names)->toContain('Time');
});

// ---------------------------------------------------------------------------
// SlackMessage DTO
// ---------------------------------------------------------------------------

it('SlackMessage constructor sets title, description, and color', function () {
    $message = new SlackMessage('Deployment', 'v2.0 released', '#00ff00');

    expect($message->title)->toBe('Deployment')
        ->and($message->description)->toBe('v2.0 released')
        ->and($message->color)->toBe('#00ff00');
});

it('SlackMessage default color is #0099ff', function () {
    $message = new SlackMessage('Title', 'Body');

    expect($message->color)->toBe('#0099ff');
});

it('SlackMessage infoColor returns #0099ff', function () {
    expect(SlackMessage::infoColor())->toBe('#0099ff');
});

it('SlackMessage errorColor returns #ff0000', function () {
    expect(SlackMessage::errorColor())->toBe('#ff0000');
});

it('SlackMessage successColor returns #00ff00', function () {
    expect(SlackMessage::successColor())->toBe('#00ff00');
});

it('SlackMessage warningColor returns #ffa500', function () {
    expect(SlackMessage::warningColor())->toBe('#ffa500');
});

it('SlackMessage infoColor matches the default constructor color', function () {
    $message = new SlackMessage('T', 'D');

    expect($message->color)->toBe(SlackMessage::infoColor());
});
