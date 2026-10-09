<?php

namespace App\Filament\Concerns;

/**
 * For resources moderators must not see: members, the catalog, registration
 * blocks and giveaways.
 */
trait AdminOnly
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
