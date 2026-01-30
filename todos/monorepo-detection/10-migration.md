# Database Migration

## Add monorepo_group_id to applications

**Файл:** `database/migrations/xxxx_add_monorepo_group_to_applications.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->uuid('monorepo_group_id')->nullable()->after('uuid');
            $table->index('monorepo_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['monorepo_group_id']);
            $table->dropColumn('monorepo_group_id');
        });
    }
};
```

---

## Создание миграции

```bash
php artisan make:migration add_monorepo_group_to_applications
```

---

## Применение

```bash
# В Docker контейнере
docker exec saturn php artisan migrate

# Или через make
make migrate
```

---

## Использование в модели Application

**Добавить в:** `app/Models/Application.php`

```php
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;

// В $fillable
protected $fillable = [
    // ... existing fields
    'monorepo_group_id',
];

// В $casts
protected $casts = [
    // ... existing casts
    'monorepo_group_id' => 'string',
];

/**
 * Get sibling applications in the same monorepo group
 *
 * Note: This returns a query builder, not a HasMany relationship,
 * because we're querying by a non-foreign-key column.
 */
public function monorepoSiblings(): Builder
{
    if (!$this->monorepo_group_id) {
        return static::query()->whereRaw('1=0'); // Empty query
    }

    return static::query()
        ->where('monorepo_group_id', $this->monorepo_group_id)
        ->where('id', '!=', $this->id);
}

/**
 * Check if application is part of a monorepo group
 */
public function isPartOfMonorepo(): bool
{
    return $this->monorepo_group_id !== null;
}

/**
 * Get all applications in the monorepo group (including this one)
 */
public function getMonorepoGroup(): Collection
{
    if (!$this->monorepo_group_id) {
        return new Collection([$this]);
    }

    return static::where('monorepo_group_id', $this->monorepo_group_id)->get();
}

/**
 * Get count of apps in monorepo group
 */
public function getMonorepoGroupCount(): int
{
    if (!$this->monorepo_group_id) {
        return 1;
    }

    return static::where('monorepo_group_id', $this->monorepo_group_id)->count();
}

/**
 * Scope to filter by monorepo group
 */
public function scopeInMonorepoGroup(Builder $query, string $groupId): Builder
{
    return $query->where('monorepo_group_id', $groupId);
}

/**
 * Scope to get only monorepo apps
 */
public function scopeMonorepoApps(Builder $query): Builder
{
    return $query->whereNotNull('monorepo_group_id');
}

/**
 * Scope to get standalone apps (not in monorepo)
 */
public function scopeStandaloneApps(Builder $query): Builder
{
    return $query->whereNull('monorepo_group_id');
}
```

---

## Использование для групповых операций

### Deploy all apps in monorepo

```php
// В будущем - опция "Deploy all on push"
public function deployMonorepoGroup(): void
{
    foreach ($this->getMonorepoGroup() as $app) {
        queue_application_deployment(
            application: $app,
            deployment_uuid: Str::uuid(),
            force_rebuild: false
        );
    }
}
```

### Canvas grouping

```php
// Для визуальной группировки на canvas
public function getMonorepoGroupInfo(): ?array
{
    if (!$this->monorepo_group_id) {
        return null;
    }

    return [
        'group_id' => $this->monorepo_group_id,
        'total_apps' => Application::where('monorepo_group_id', $this->monorepo_group_id)->count(),
    ];
}
```
