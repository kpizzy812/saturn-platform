<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Encrypt existing plaintext data in CodeReview and AiUsageLog models.
 *
 * After this migration, the 'encrypted' cast on these fields will work correctly.
 * Previously stored plaintext values are encrypted in-place.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // Encrypt CodeReview summary and error_message
            DB::table('code_reviews')
                ->whereNotNull('summary')
                ->orWhereNotNull('error_message')
                ->orderBy('id')
                ->chunk(100, function ($reviews) {
                    foreach ($reviews as $review) {
                        $updates = [];

                        if ($review->summary !== null && ! $this->isEncrypted($review->summary)) {
                            $updates['summary'] = Crypt::encryptString($review->summary);
                        }

                        if ($review->error_message !== null && ! $this->isEncrypted($review->error_message)) {
                            $updates['error_message'] = Crypt::encryptString($review->error_message);
                        }

                        if (! empty($updates)) {
                            DB::table('code_reviews')->where('id', $review->id)->update($updates);
                        }
                    }
                });

            // Encrypt AiUsageLog error_message
            DB::table('ai_usage_logs')
                ->whereNotNull('error_message')
                ->orderBy('id')
                ->chunk(100, function ($logs) {
                    foreach ($logs as $log) {
                        if ($log->error_message !== null && ! $this->isEncrypted($log->error_message)) {
                            DB::table('ai_usage_logs')
                                ->where('id', $log->id)
                                ->update(['error_message' => Crypt::encryptString($log->error_message)]);
                        }
                    }
                });
        });

        Log::info('Encrypted sensitive AI fields in CodeReview and AiUsageLog tables.');
    }

    public function down(): void
    {
        // Decrypt CodeReview fields back to plaintext
        DB::table('code_reviews')
            ->whereNotNull('summary')
            ->orWhereNotNull('error_message')
            ->orderBy('id')
            ->chunk(100, function ($reviews) {
                foreach ($reviews as $review) {
                    $updates = [];

                    if ($review->summary !== null && $this->isEncrypted($review->summary)) {
                        try {
                            $updates['summary'] = Crypt::decryptString($review->summary);
                        } catch (\Exception $e) {
                            // Already plaintext, skip
                        }
                    }

                    if ($review->error_message !== null && $this->isEncrypted($review->error_message)) {
                        try {
                            $updates['error_message'] = Crypt::decryptString($review->error_message);
                        } catch (\Exception $e) {
                            // Already plaintext, skip
                        }
                    }

                    if (! empty($updates)) {
                        DB::table('code_reviews')->where('id', $review->id)->update($updates);
                    }
                }
            });

        // Decrypt AiUsageLog fields
        DB::table('ai_usage_logs')
            ->whereNotNull('error_message')
            ->orderBy('id')
            ->chunk(100, function ($logs) {
                foreach ($logs as $log) {
                    if ($log->error_message !== null && $this->isEncrypted($log->error_message)) {
                        try {
                            DB::table('ai_usage_logs')
                                ->where('id', $log->id)
                                ->update(['error_message' => Crypt::decryptString($log->error_message)]);
                        } catch (\Exception $e) {
                            // Already plaintext, skip
                        }
                    }
                }
            });
    }

    /**
     * Check if a string looks like it's already encrypted by Laravel.
     */
    private function isEncrypted(string $value): bool
    {
        // Laravel encrypted strings are base64-encoded JSON with iv, value, mac keys
        $decoded = base64_decode($value, true);
        if ($decoded === false) {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json) && isset($json['iv'], $json['value'], $json['mac']);
    }
};
