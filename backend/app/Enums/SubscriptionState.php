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
    // Self-registered, not live yet: e-mail not verified, or waiting for the Super Admin's approval.
    case Pending = 'pending';

    /** The restaurant admin may change data. */
    public function allowsWrites(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace, self::Pending], true);
    }

    /** The public menu is shown to customers. */
    public function showsPublicMenu(): bool
    {
        return in_array($this, [self::Trial, self::Active, self::Grace, self::ReadOnly], true);
    }

    /** The admin shows a "subscription expired" banner. */
    public function showsBanner(): bool
    {
        return in_array($this, [self::Grace, self::ReadOnly, self::Unavailable, self::Pending], true);
    }
}
