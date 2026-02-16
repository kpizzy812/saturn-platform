<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Visus\Cuid2\Cuid2;

/**
 * @property int $id
 * @property string $uuid
 * @property string $image
 * @property-read string $sanitized_name
 */
abstract class BaseModel extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $model) {
            // Generate a UUID if one isn't set
            if (! $model->uuid) {
                $model->uuid = (string) new Cuid2;
            }
        });
    }

    public function sanitizedName(): Attribute
    {
        return new Attribute(
            get: fn () => sanitize_string($this->getRawOriginal('name')),
        );
    }

    public function image(): Attribute
    {
        return new Attribute(
            get: fn () => sanitize_string($this->getRawOriginal('image')),
        );
    }
}
