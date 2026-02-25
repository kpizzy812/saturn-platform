<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Notification\UpdateDiscordNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateEmailNotificationRequest;
use App\Http\Requests\Api\Notification\UpdatePushoverNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateSlackNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateTelegramNotificationRequest;
use App\Http\Requests\Api\Notification\UpdateWebhookNotificationRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function updateEmail(UpdateEmailNotificationRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('emailNotificationSettings')->findOrFail($teamId);

        $team->emailNotificationSettings->update($request->validated());

        return response()->json([
            'message' => 'Email notification settings updated successfully.',
            'settings' => $team->emailNotificationSettings,
        ]);
    }

    /**
     * Update Slack notification settings
     */
    public function updateSlack(UpdateSlackNotificationRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('slackNotificationSettings')->findOrFail($teamId);

        $team->slackNotificationSettings->update($request->validated());

        return response()->json([
            'message' => 'Slack notification settings updated successfully.',
            'settings' => $team->slackNotificationSettings,
        ]);
    }

    /**
     * Update Discord notification settings
     */
    public function updateDiscord(UpdateDiscordNotificationRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('discordNotificationSettings')->findOrFail($teamId);

        $team->discordNotificationSettings->update($request->validated());

        return response()->json([
            'message' => 'Discord notification settings updated successfully.',
            'settings' => $team->discordNotificationSettings,
        ]);
    }

    /**
     * Update Telegram notification settings
     */
    public function updateTelegram(UpdateTelegramNotificationRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('telegramNotificationSettings')->findOrFail($teamId);

        $team->telegramNotificationSettings->update($request->validated());

        return response()->json([
            'message' => 'Telegram notification settings updated successfully.',
            'settings' => $team->telegramNotificationSettings,
        ]);
    }

    /**
     * Update Webhook notification settings
     */
    public function updateWebhook(UpdateWebhookNotificationRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('webhookNotificationSettings')->findOrFail($teamId);

        $team->webhookNotificationSettings->update($request->validated());

        return response()->json([
            'message' => 'Webhook notification settings updated successfully.',
            'settings' => $team->webhookNotificationSettings,
        ]);
    }

    /**
     * Update Pushover notification settings
     */
    public function updatePushover(UpdatePushoverNotificationRequest $request): JsonResponse
    {
        $teamId = getTeamIdFromToken();
        if (is_null($teamId)) {
            return invalidTokenResponse();
        }

        $team = Team::with('pushoverNotificationSettings')->findOrFail($teamId);

        $team->pushoverNotificationSettings->update($request->validated());

        return response()->json([
            'message' => 'Pushover notification settings updated successfully.',
            'settings' => $team->pushoverNotificationSettings,
        ]);
    }
}
