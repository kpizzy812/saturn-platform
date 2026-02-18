<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationChannelsController extends Controller
{
    /**
     * Get all notification channel settings for the current team
     */
    public function index(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with([
            'emailNotificationSettings',
            'slackNotificationSettings',
            'discordNotificationSettings',
            'telegramNotificationSettings',
            'webhookNotificationSettings',
            'pushoverNotificationSettings',
        ])->findOrFail($teamId);

        return response()->json([
            'email' => $team->emailNotificationSettings,
            'slack' => $team->slackNotificationSettings,
            'discord' => $team->discordNotificationSettings,
            'telegram' => $team->telegramNotificationSettings,
            'webhook' => $team->webhookNotificationSettings,
            'pushover' => $team->pushoverNotificationSettings,
        ]);
    }

    /**
     * Update email notification settings
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('emailNotificationSettings')->findOrFail($teamId);

        $validator = Validator::make($request->all(), [
            'smtp_enabled' => 'sometimes|boolean',
            'smtp_from_address' => 'sometimes|email|nullable',
            'smtp_from_name' => 'sometimes|string|nullable',
            'smtp_recipients' => ['sometimes', 'string', 'nullable', 'regex:/^[^@\s]+@[^@\s]+(,[^@\s]+@[^@\s]+)*$/'],
            'smtp_host' => 'sometimes|string|nullable',
            'smtp_port' => 'sometimes|integer|nullable|min:1|max:65535',
            'smtp_username' => 'sometimes|string|nullable',
            'smtp_password' => 'sometimes|string|nullable',
            'resend_enabled' => 'sometimes|boolean',
            'resend_api_key' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team->emailNotificationSettings->update($request->only([
            'smtp_enabled',
            'smtp_from_address',
            'smtp_from_name',
            'smtp_recipients',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'resend_enabled',
            'resend_api_key',
        ]));

        return response()->json([
            'message' => 'Email notification settings updated successfully.',
            'settings' => $team->emailNotificationSettings,
        ]);
    }

    /**
     * Update Slack notification settings
     */
    public function updateSlack(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('slackNotificationSettings')->findOrFail($teamId);

        $validator = Validator::make($request->all(), [
            'slack_enabled' => 'sometimes|boolean',
            'slack_webhook_url' => 'sometimes|url|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team->slackNotificationSettings->update($request->only([
            'slack_enabled',
            'slack_webhook_url',
        ]));

        return response()->json([
            'message' => 'Slack notification settings updated successfully.',
            'settings' => $team->slackNotificationSettings,
        ]);
    }

    /**
     * Update Discord notification settings
     */
    public function updateDiscord(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('discordNotificationSettings')->findOrFail($teamId);

        $validator = Validator::make($request->all(), [
            'discord_enabled' => 'sometimes|boolean',
            'discord_webhook_url' => 'sometimes|url|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team->discordNotificationSettings->update($request->only([
            'discord_enabled',
            'discord_webhook_url',
        ]));

        return response()->json([
            'message' => 'Discord notification settings updated successfully.',
            'settings' => $team->discordNotificationSettings,
        ]);
    }

    /**
     * Update Telegram notification settings
     */
    public function updateTelegram(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('telegramNotificationSettings')->findOrFail($teamId);

        $validator = Validator::make($request->all(), [
            'telegram_enabled' => 'sometimes|boolean',
            'telegram_token' => 'sometimes|string|nullable',
            'telegram_chat_id' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team->telegramNotificationSettings->update($request->only([
            'telegram_enabled',
            'telegram_token',
            'telegram_chat_id',
        ]));

        return response()->json([
            'message' => 'Telegram notification settings updated successfully.',
            'settings' => $team->telegramNotificationSettings,
        ]);
    }

    /**
     * Update Webhook notification settings
     */
    public function updateWebhook(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('webhookNotificationSettings')->findOrFail($teamId);

        $validator = Validator::make($request->all(), [
            'webhook_enabled' => 'sometimes|boolean',
            'webhook_url' => 'sometimes|url|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team->webhookNotificationSettings->update($request->only([
            'webhook_enabled',
            'webhook_url',
        ]));

        return response()->json([
            'message' => 'Webhook notification settings updated successfully.',
            'settings' => $team->webhookNotificationSettings,
        ]);
    }

    /**
     * Update Pushover notification settings
     */
    public function updatePushover(Request $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('pushoverNotificationSettings')->findOrFail($teamId);

        $validator = Validator::make($request->all(), [
            'pushover_enabled' => 'sometimes|boolean',
            'pushover_user_key' => 'sometimes|string|nullable',
            'pushover_api_token' => 'sometimes|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $team->pushoverNotificationSettings->update($request->only([
            'pushover_enabled',
            'pushover_user_key',
            'pushover_api_token',
        ]));

        return response()->json([
            'message' => 'Pushover notification settings updated successfully.',
            'settings' => $team->pushoverNotificationSettings,
        ]);
    }
}
