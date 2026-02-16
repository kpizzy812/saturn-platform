<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $maxSortOrder = Permission::max('sort_order') ?? 0;

        Permission::updateOrCreate(
            ['key' => 'team.archives'],
            [
                'name' => 'Manage Archives',
                'description' => 'View and manage member archives including exports and transfers',
                'resource' => 'team',
                'action' => 'archives',
                'category' => 'team',
                'is_sensitive' => false,
                'sort_order' => $maxSortOrder + 1,
            ]
        );
    }

    public function down(): void
    {
        Permission::where('key', 'team.archives')->delete();
    }
};
