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
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('status');
            $table->string('approval_status', 20)->nullable()->after('requires_approval'); // 'pending', 'approved', 'rejected'
            $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_note')->nullable()->after('approved_at');
            $table->index('approval_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_deployment_queues', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn([
                'requires_approval',
                'approval_status',
                'approved_by',
                'approved_at',
                'approval_note',
            ]);
        });
    }
};
