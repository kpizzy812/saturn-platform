<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_transfers', function (Blueprint $table) {
            $table->boolean('requires_approval')->default(false)->after('logs');
            $table->foreignId('approved_by')->nullable()->after('requires_approval')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');

            $table->index(['requires_approval', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('resource_transfers', function (Blueprint $table) {
            $table->dropIndex(['requires_approval', 'status']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['requires_approval', 'approved_by', 'approved_at', 'rejection_reason']);
        });
    }
};
