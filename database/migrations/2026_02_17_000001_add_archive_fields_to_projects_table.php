<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('description');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->foreignId('archived_by')->nullable()->after('archived_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['is_archived', 'archived_at', 'archived_by']);
        });
    }
};
