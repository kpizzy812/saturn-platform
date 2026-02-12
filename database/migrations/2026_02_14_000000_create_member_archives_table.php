<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_archives', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            // Frozen member info at time of kick
            $table->string('member_name');
            $table->string('member_email');
            $table->string('member_role');
            $table->timestamp('member_joined_at')->nullable();

            // Who kicked
            $table->foreignId('kicked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kicked_by_name')->nullable();
            $table->text('kick_reason')->nullable();

            // Aggregated contribution stats from audit_logs
            $table->json('contribution_summary')->nullable();

            // Access snapshot: allowed_projects, permission_set, role at time of kick
            $table->json('access_snapshot')->nullable();

            // Array of TeamResourceTransfer IDs
            $table->json('transfer_ids')->nullable();

            $table->string('status', 30)->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('team_id');
            $table->index('user_id');
            $table->index('member_email');
            $table->index('kicked_by');
        });

        // Add notes column to team_resource_transfers (exists in model $fillable but not in migration)
        Schema::table('team_resource_transfers', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_archives');

        Schema::table('team_resource_transfers', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
