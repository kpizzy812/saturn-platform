<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add user status field: active (default), suspended, banned, pending
            $table->string('status', 20)->default('active')->after('is_superadmin');

            // Add suspended_at timestamp for tracking when user was suspended
            $table->timestamp('suspended_at')->nullable()->after('status');

            // Add suspended_by to track which admin suspended the user
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();

            // Add suspension_reason for audit purposes
            $table->text('suspension_reason')->nullable()->after('suspended_by');

            // Add last_login_at for activity tracking
            $table->timestamp('last_login_at')->nullable()->after('updated_at');

            // Add index for status for faster queries
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'status',
                'suspended_at',
                'suspended_by',
                'suspension_reason',
                'last_login_at',
            ]);
        });
    }
};
