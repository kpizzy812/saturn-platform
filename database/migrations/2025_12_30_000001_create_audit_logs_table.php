<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action');
                $table->string('resource_type')->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->string('resource_name')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }

        // Add indexes safely (check if they exist first)
        $this->addIndexIfNotExists('audit_logs', 'action', 'audit_logs_action_index');
        $this->addIndexIfNotExists('audit_logs', 'created_at', 'audit_logs_created_at_index');
        $this->addIndexIfNotExists('audit_logs', ['resource_type', 'resource_id'], 'audit_logs_resource_type_resource_id_index');
    }

    /**
     * Add index if it doesn't exist
     */
    private function addIndexIfNotExists(string $table, string|array $columns, string $indexName): void
    {
        $indexExists = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = ?", [$indexName]);

        if (empty($indexExists)) {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
