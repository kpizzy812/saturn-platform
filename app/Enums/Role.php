<?php

namespace App\Enums;

enum Role: string
{
    case VIEWER = 'viewer';
    case MEMBER = 'member';
    case DEVELOPER = 'developer';
    case ADMIN = 'admin';
    case OWNER = 'owner';
    case SUPERADMIN = 'superadmin';

    public function rank(): int
    {
        return match ($this) {
            self::VIEWER => 1,
            self::MEMBER => 2,
            self::DEVELOPER => 3,
            self::ADMIN => 4,
            self::OWNER => 5,
            self::SUPERADMIN => 6,
        };
    }

    public function lt(Role|string $role): bool
    {
        if (is_string($role)) {
            $role = Role::from($role);
        }

        return $this->rank() < $role->rank();
    }

    public function gt(Role|string $role): bool
    {
        if (is_string($role)) {
            $role = Role::from($role);
        }

        return $this->rank() > $role->rank();
    }
}
