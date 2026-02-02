<?php

namespace App\Notifications\Channels;

use App\Models\Team;
use App\Models\UserNotification;
use Illuminate\Notifications\Notification;

/**
 * Channel for storing notifications in the database for in-app display.
 */
class InAppChannel
{
    /**
     * Notification type mappings from event names to UserNotification types.
     */
    protected array $typeMapping = [
        'deployment_success' => 'deployment_success',
        'deployment_failure' => 'deployment_failure',
        'deployment_approval_required' => 'deployment_approval',
        'backup_success' => 'backup_success',
        'backup_failure' => 'backup_failure',
        'server_unreachable' => 'server_alert',
        'server_reachable' => 'server_alert',
        'server_disk_usage' => 'server_alert',
        'server_patch' => 'server_alert',
        'status_change' => 'info',
        'scheduled_task_success' => 'info',
        'scheduled_task_failure' => 'info',
        'ssl_certificate_renewal' => 'security_alert',
        'docker_cleanup_success' => 'info',
        'docker_cleanup_failure' => 'server_alert',
        'general' => 'info',
        'test' => 'info',
    ];

    /**
     * Send the given notification.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof Team) {
            return;
        }

        $data = $this->extractNotificationData($notification);

        if (empty($data['title'])) {
            return;
        }

        UserNotification::create([
            'team_id' => $notifiable->id,
            'type' => $data['type'],
            'title' => $data['title'],
            'description' => $data['description'],
            'action_url' => $data['action_url'],
            'metadata' => $data['metadata'],
        ]);
    }

    /**
     * Extract notification data from the notification object.
     */
    protected function extractNotificationData(Notification $notification): array
    {
        // If notification has toInApp method, use it
        if (method_exists($notification, 'toInApp')) {
            return $notification->toInApp();
        }

        // Otherwise, extract from common properties
        $class = get_class($notification);
        $shortClass = class_basename($class);

        // Try to determine type from class name
        $type = $this->determineType($class);

        // Extract common properties
        $title = $this->extractTitle($notification, $shortClass);
        $description = $this->extractDescription($notification);
        $actionUrl = $this->extractActionUrl($notification);
        $metadata = $this->extractMetadata($notification);

        return [
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'action_url' => $actionUrl,
            'metadata' => $metadata,
        ];
    }

    /**
     * Determine notification type from class name.
     */
    protected function determineType(string $class): string
    {
        if (str_contains($class, 'DeploymentApprovalRequired')) {
            return 'deployment_approval';
        }
        if (str_contains($class, 'DeploymentSuccess') || str_contains($class, 'DeploymentApproved')) {
            return 'deployment_success';
        }
        if (str_contains($class, 'DeploymentFail') || str_contains($class, 'DeploymentRejected')) {
            return 'deployment_failure';
        }
        if (str_contains($class, 'BackupSuccess')) {
            return 'backup_success';
        }
        if (str_contains($class, 'BackupFail')) {
            return 'backup_failure';
        }
        if (str_contains($class, 'Server') || str_contains($class, 'Unreachable') || str_contains($class, 'Reachable')) {
            return 'server_alert';
        }
        if (str_contains($class, 'Security') || str_contains($class, 'Ssl')) {
            return 'security_alert';
        }

        return 'info';
    }

    /**
     * Extract title from notification.
     */
    protected function extractTitle(Notification $notification, string $shortClass): string
    {
        // Try common property names
        if (property_exists($notification, 'title')) {
            return $notification->title;
        }

        // Build title from class name and properties
        $title = $this->humanizeClassName($shortClass);

        if (property_exists($notification, 'application_name')) {
            $title .= ': '.$notification->application_name;
        } elseif (property_exists($notification, 'database_name')) {
            $title .= ': '.$notification->database_name;
        } elseif (property_exists($notification, 'server') && isset($notification->server->name)) {
            $title .= ': '.$notification->server->name;
        }

        return $title;
    }

    /**
     * Extract description from notification.
     */
    protected function extractDescription(Notification $notification): ?string
    {
        if (property_exists($notification, 'description')) {
            return $notification->description;
        }

        if (property_exists($notification, 'message')) {
            return $notification->message;
        }

        // Try to build description from environment info
        $parts = [];
        if (property_exists($notification, 'environment_name')) {
            $parts[] = 'Environment: '.$notification->environment_name;
        }

        return ! empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Extract action URL from notification.
     */
    protected function extractActionUrl(Notification $notification): ?string
    {
        if (property_exists($notification, 'action_url')) {
            return $notification->action_url;
        }

        if (property_exists($notification, 'deployment_url')) {
            return $notification->deployment_url;
        }

        return null;
    }

    /**
     * Extract metadata from notification.
     */
    protected function extractMetadata(Notification $notification): ?array
    {
        $metadata = [];

        if (property_exists($notification, 'deployment_uuid')) {
            $metadata['deployment_uuid'] = $notification->deployment_uuid;
        }
        if (property_exists($notification, 'application') && isset($notification->application->uuid)) {
            $metadata['application_uuid'] = $notification->application->uuid;
        }
        if (property_exists($notification, 'server') && isset($notification->server->uuid)) {
            $metadata['server_uuid'] = $notification->server->uuid;
        }

        return ! empty($metadata) ? $metadata : null;
    }

    /**
     * Convert class name to human readable format.
     */
    protected function humanizeClassName(string $class): string
    {
        // Remove common suffixes
        $class = preg_replace('/(Notification|Success|Failed)$/', '', $class);

        // Convert CamelCase to spaces
        $title = preg_replace('/([a-z])([A-Z])/', '$1 $2', $class);

        return trim($title);
    }
}
