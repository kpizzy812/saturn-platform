<?php

namespace App\Actions\Migration;

use App\Models\Environment;
use App\Models\StandaloneMariadb;
use App\Models\StandaloneMongodb;
use App\Models\StandaloneMysql;
use App\Models\StandalonePostgresql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rotate database credentials when cloning to production.
 *
 * Generates new passwords for databases on first clone to production,
 * updates the database model, and rewires any env vars that reference
 * the old password in the target environment.
 */
class RotateCredentialsAction
{
    use AsAction;

    /**
     * Rotate credentials for a newly cloned database in production.
     *
     * @return array{success: bool, rotated_fields?: array<string, string>, updated_env_vars?: int, error?: string}
     */
    public function handle(Model $database, Environment $targetEnv): array
    {
        $fieldMap = self::getCredentialFields($database);

        if ($fieldMap === null) {
            return [
                'success' => true,
                'rotated_fields' => [],
                'updated_env_vars' => 0,
            ];
        }

        try {
            $oldPasswords = [];
            $newPasswords = [];
            $rotatedFields = [];

            // Generate new passwords for each password field
            foreach ($fieldMap as $key => $field) {
                if (! str_contains($key, 'password')) {
                    continue;
                }

                $oldValue = $database->getAttribute($field);
                if (! $oldValue) {
                    continue;
                }

                $newValue = Str::password(length: 64, symbols: false);

                $oldPasswords[$field] = $oldValue;
                $newPasswords[$field] = $newValue;
                $rotatedFields[$field] = $newValue;
            }

            if (empty($newPasswords)) {
                return [
                    'success' => true,
                    'rotated_fields' => [],
                    'updated_env_vars' => 0,
                ];
            }

            // Update password fields on the database model
            $database->update($newPasswords);

            // Rewire env vars in the target environment that contain the old passwords
            $updatedVarCount = $this->updateReferencingEnvVars($oldPasswords, $newPasswords, $targetEnv);

            Log::info('Credentials rotated for production database', [
                'database_type' => class_basename($database),
                'database_id' => $database->getAttribute('id'),
                'rotated_fields' => array_keys($rotatedFields),
                'updated_env_vars' => $updatedVarCount,
            ]);

            return [
                'success' => true,
                'rotated_fields' => $rotatedFields,
                'updated_env_vars' => $updatedVarCount,
            ];

        } catch (\Throwable $e) {
            Log::error('Credential rotation failed', [
                'database_type' => class_basename($database),
                'database_id' => $database->getAttribute('id'),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Credential rotation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Update env vars in target environment that reference old passwords.
     */
    protected function updateReferencingEnvVars(array $oldPasswords, array $newPasswords, Environment $targetEnv): int
    {
        $updatedCount = 0;

        $resources = $this->getAllResources($targetEnv);

        foreach ($resources as $resource) {
            if (! $resource instanceof Model || ! method_exists($resource, 'environment_variables')) {
                continue;
            }

            $envVars = $resource->getAttribute('environment_variables') ?? collect();

            foreach ($envVars as $envVar) {
                $value = $envVar->value;
                $changed = false;

                foreach ($oldPasswords as $field => $oldValue) {
                    // Skip short passwords to avoid false matches in env var values
                    if (strlen($oldValue) < 8) {
                        continue;
                    }
                    if (str_contains($value, $oldValue)) {
                        $value = str_replace($oldValue, $newPasswords[$field], $value);
                        $changed = true;
                    }
                }

                if ($changed) {
                    $envVar->update(['value' => $value]);
                    $updatedCount++;
                }
            }
        }

        return $updatedCount;
    }

    /**
     * Get all resources (apps, services, databases) from an environment.
     */
    protected function getAllResources(Environment $targetEnv): array
    {
        $resources = [];

        $resources = array_merge($resources, $targetEnv->applications()->get()->all());
        $resources = array_merge($resources, $targetEnv->services()->get()->all());

        $dbRelations = [
            'postgresqls', 'mysqls', 'mariadbs', 'mongodbs',
            'redis', 'clickhouses', 'keydbs', 'dragonflies',
        ];

        foreach ($dbRelations as $relation) {
            if (method_exists($targetEnv, $relation)) {
                $resources = array_merge($resources, $targetEnv->$relation()->get()->all());
            }
        }

        return $resources;
    }

    /**
     * Get credential field mapping for a database model, or null if not supported.
     *
     * @return array<string, string>|null
     */
    public static function getCredentialFields(Model $resource): ?array
    {
        if ($resource instanceof StandalonePostgresql) {
            return [
                'password' => 'postgres_password',
                'user' => 'postgres_user',
            ];
        }

        if ($resource instanceof StandaloneMysql) {
            return [
                'password' => 'mysql_password',
                'root_password' => 'mysql_root_password',
                'user' => 'mysql_user',
            ];
        }

        if ($resource instanceof StandaloneMariadb) {
            return [
                'password' => 'mariadb_password',
                'root_password' => 'mariadb_root_password',
                'user' => 'mariadb_user',
            ];
        }

        if ($resource instanceof StandaloneMongodb) {
            return [
                'password' => 'mongo_initdb_root_password',
                'user' => 'mongo_initdb_root_username',
            ];
        }

        return null;
    }

    /**
     * Check if a resource type supports credential rotation.
     */
    public static function supportsRotation(Model $resource): bool
    {
        return self::getCredentialFields($resource) !== null;
    }
}
