<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::User => 'User',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::SuperAdmin => 'bg-violet-100 text-violet-700 ring-violet-600/20',
            self::Admin => 'bg-sky-100 text-sky-700 ring-sky-600/20',
            self::User => 'bg-slate-100 text-slate-700 ring-slate-600/20',
        };
    }

    /** Roles that may reach the admin area. */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }
}
