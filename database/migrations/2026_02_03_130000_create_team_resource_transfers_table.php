<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table tracks resource transfers between teams when managing users.
     * Used when deleting users to preserve their resources by transferring them.
     */
    public function up(): void
    {
        Schema::create('team_resource_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();

            // Polymorphic relation to the resource being transferred
            // Types: App\Models\Project, App\Models\Team, App\Models\Server, etc.
            $table->string('transferable_type');
            $table->unsignedBigInteger('transferable_id');

            // Transfer direction - teams
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();

            // Transfer direction - users (for ownership transfers)
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Who initiated the transfer (admin performing the action)
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();

            // Transfer type categorization
            $table->string('transfer_type', 50)->default('project_transfer');
            // Types:
            // - project_transfer: Moving project to another team
            // - team_ownership: Changing team owner
            // - team_merge: Merging two teams
            // - user_deletion: Transfer as part of user deletion
            // - archive: Moving to archive team

            // Human-readable reason for the transfer
            $table->text('reason')->nullable();

            // Transfer status
            $table->string('status', 20)->default('pending');
            // Status: pending, in_progress, completed, failed, rolled_back

            // Snapshot of resource state before transfer (JSON for audit/rollback)
            // Contains: name, settings, related resources count, etc.
            $table->json('resource_snapshot')->nullable();

            // List of related resources that were also transferred
            // e.g., when transferring project: environments, applications, etc.
            $table->json('related_transfers')->nullable();

            // Error message if transfer failed
            $table->text('error_message')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Indexes for common queries
            $table->index(['transferable_type', 'transferable_id'], 'trt_transferable_index');
            $table->index('status');
            $table->index('transfer_type');
            $table->index('from_user_id');
            $table->index('to_team_id');
            $table->index('initiated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_resource_transfers');
    }
};
