<?php

namespace App\Filament\Resources\Giveaways\Pages;

use App\Filament\Resources\Giveaways\GiveawayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGiveaways extends ManageRecords
{
    protected static string $resource = GiveawayResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
