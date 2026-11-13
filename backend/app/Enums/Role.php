<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';

    /**
     * Roles this role may invite, change or remove.
     *
     * @return list<self>
     */
    public function manageableRoles(): array
    {
        return match ($this) {
            self::Owner => [self::Manager, self::Staff],
            self::Manager => [self::Staff],
            self::Staff => [],
        };
    }

    public function canManage(self $role): bool
    {
        return in_array($role, $this->manageableRoles(), true);
    }
}
