<?php

namespace App\Enums;

enum SubscriptionState: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Grace = 'grace';
    case ReadOnly = 'read_only';
    case Unavailable = 'unavailable';
    case Suspended = 'suspended';

    /** The restaurant admin may change data. */
    public function allowsWrites(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace], true);
    }

    /** The public menu is shown to customers. */
    public function showsPublicMenu(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace, self::ReadOnly], true);
    }

    /** The admin shows a "subscription expired" banner. */
    public function showsBanner(): bool
    {
        return in_array($this, [self::Grace, self::ReadOnly, self::Unavailable], true);
    }
}
