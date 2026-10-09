<?php

namespace App\Filament\Resources\BlockedEmailDomains\Pages;

use App\Filament\Resources\BlockedEmailDomains\BlockedEmailDomainResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedEmailDomains extends ManageRecords
{
    protected static string $resource = BlockedEmailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
