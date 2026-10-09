<?php

namespace App\Filament\Resources\BlockedIps\Pages;

use App\Filament\Resources\BlockedIps\BlockedIpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedIps extends ManageRecords
{
    protected static string $resource = BlockedIpResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
