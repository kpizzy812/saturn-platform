<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'email_deployments',
        'email_team',
        'email_billing',
        'email_security',
        'in_app_deployments',
        'in_app_team',
        'in_app_billing',
        'in_app_security',
        'digest_frequency',
        // Security-specific notification preferences
        'security_new_login',
        'security_failed_login',
        'security_api_access',
    ];

    protected $casts = [
        'email_deployments' => 'boolean',
        'email_team' => 'boolean',
        'email_billing' => 'boolean',
        'email_security' => 'boolean',
        'in_app_deployments' => 'boolean',
        'in_app_team' => 'boolean',
        'in_app_billing' => 'boolean',
        'in_app_security' => 'boolean',
        // Security-specific notification preferences
        'security_new_login' => 'boolean',
        'security_failed_login' => 'boolean',
        'security_api_access' => 'boolean',
    ];

    /**
     * Get the user that owns the preferences.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create preferences for a user.
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'email_deployments' => true,
                'email_team' => true,
                'email_billing' => true,
                'email_security' => true,
                'in_app_deployments' => true,
                'in_app_team' => true,
                'in_app_billing' => true,
                'in_app_security' => true,
                'digest_frequency' => 'instant',
                // Security-specific defaults
                'security_new_login' => true,
                'security_failed_login' => true,
                'security_api_access' => false,
            ]
        );
    }

    /**
     * Get security notification preferences.
     */
    public function getSecurityNotifications(): array
    {
        return [
            'newLogin' => $this->security_new_login ?? true,
            'failedLogin' => $this->security_failed_login ?? true,
            'apiAccess' => $this->security_api_access ?? false,
        ];
    }

    /**
     * Update security notification preferences.
     */
    public function updateSecurityNotifications(array $data): bool
    {
        $attributes = [];

        if (isset($data['newLogin'])) {
            $attributes['security_new_login'] = (bool) $data['newLogin'];
        }
        if (isset($data['failedLogin'])) {
            $attributes['security_failed_login'] = (bool) $data['failedLogin'];
        }
        if (isset($data['apiAccess'])) {
            $attributes['security_api_access'] = (bool) $data['apiAccess'];
        }

        return $this->update($attributes);
    }

    /**
     * Convert to frontend format.
     */
    public function toFrontendFormat(): array
    {
        return [
            'email' => [
                'deployments' => $this->email_deployments,
                'team' => $this->email_team,
                'billing' => $this->email_billing,
                'security' => $this->email_security,
            ],
            'inApp' => [
                'deployments' => $this->in_app_deployments,
                'team' => $this->in_app_team,
                'billing' => $this->in_app_billing,
                'security' => $this->in_app_security,
            ],
            'digest' => $this->digest_frequency,
        ];
    }

    /**
     * Update from frontend format.
     */
    public function updateFromFrontendFormat(array $data): bool
    {
        $attributes = [];

        if (isset($data['email'])) {
            if (isset($data['email']['deployments'])) {
                $attributes['email_deployments'] = (bool) $data['email']['deployments'];
            }
            if (isset($data['email']['team'])) {
                $attributes['email_team'] = (bool) $data['email']['team'];
            }
            if (isset($data['email']['billing'])) {
                $attributes['email_billing'] = (bool) $data['email']['billing'];
            }
            if (isset($data['email']['security'])) {
                $attributes['email_security'] = (bool) $data['email']['security'];
            }
        }

        if (isset($data['inApp'])) {
            if (isset($data['inApp']['deployments'])) {
                $attributes['in_app_deployments'] = (bool) $data['inApp']['deployments'];
            }
            if (isset($data['inApp']['team'])) {
                $attributes['in_app_team'] = (bool) $data['inApp']['team'];
            }
            if (isset($data['inApp']['billing'])) {
                $attributes['in_app_billing'] = (bool) $data['inApp']['billing'];
            }
            if (isset($data['inApp']['security'])) {
                $attributes['in_app_security'] = (bool) $data['inApp']['security'];
            }
        }

        if (isset($data['digest']) && in_array($data['digest'], ['instant', 'daily', 'weekly'])) {
            $attributes['digest_frequency'] = $data['digest'];
        }

        return $this->update($attributes);
    }
}
