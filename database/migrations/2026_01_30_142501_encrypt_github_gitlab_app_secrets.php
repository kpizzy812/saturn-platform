<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Encrypts existing GitHub and GitLab app secrets that were stored in plaintext.
     */
    public function up(): void
    {
        // Encrypt GitHub App secrets
        $this->encryptTableSecrets('github_apps', ['client_secret', 'webhook_secret']);

        // Encrypt GitLab App secrets
        $this->encryptTableSecrets('gitlab_apps', ['webhook_token', 'app_secret']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Decrypt GitHub App secrets
        $this->decryptTableSecrets('github_apps', ['client_secret', 'webhook_secret']);

        // Decrypt GitLab App secrets
        $this->decryptTableSecrets('gitlab_apps', ['webhook_token', 'app_secret']);
    }

    /**
     * Encrypt specified columns in a table.
     */
    private function encryptTableSecrets(string $table, array $columns): void
    {
        if (! DB::table($table)->exists()) {
            return;
        }

        $records = DB::table($table)->get();

        foreach ($records as $record) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $record->$column ?? null;

                if ($value && ! $this->isEncrypted($value)) {
                    try {
                        $updates[$column] = Crypt::encryptString($value);
                    } catch (\Exception $e) {
                        \Log::error("Error encrypting {$table}.{$column} for ID {$record->id}: ".$e->getMessage());
                    }
                }
            }

            if (! empty($updates)) {
                DB::table($table)->where('id', $record->id)->update($updates);
            }
        }
    }

    /**
     * Decrypt specified columns in a table.
     */
    private function decryptTableSecrets(string $table, array $columns): void
    {
        if (! DB::table($table)->exists()) {
            return;
        }

        $records = DB::table($table)->get();

        foreach ($records as $record) {
            $updates = [];

            foreach ($columns as $column) {
                $value = $record->$column ?? null;

                if ($value && $this->isEncrypted($value)) {
                    try {
                        $updates[$column] = Crypt::decryptString($value);
                    } catch (\Exception $e) {
                        \Log::error("Error decrypting {$table}.{$column} for ID {$record->id}: ".$e->getMessage());
                    }
                }
            }

            if (! empty($updates)) {
                DB::table($table)->where('id', $record->id)->update($updates);
            }
        }
    }

    /**
     * Check if a value appears to be encrypted (base64 encoded with Laravel encryption prefix).
     */
    private function isEncrypted(?string $value): bool
    {
        if (empty($value)) {
            return false;
        }

        // Laravel encrypted values are base64 encoded JSON containing 'iv', 'value', 'mac' keys
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv']) && isset($json['value']) && isset($json['mac']);
    }
};
