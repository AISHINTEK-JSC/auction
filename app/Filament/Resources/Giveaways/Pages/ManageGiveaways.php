<?php

namespace App\Filament\Resources\Giveaways\Pages;

use App\Filament\Resources\Giveaways\GiveawayResource;
use App\Models\Giveaway;
use App\Services\GiveawayService;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageGiveaways extends ManageRecords
{
    protected static string $resource = GiveawayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // A new giveaway is announced to every member, so they know to sign up.
            CreateAction::make()->after(function (Giveaway $record): void {
                $notified = app(GiveawayService::class)->announce($record, auth()->user());

                Notification::make()
                    ->title("Đã thông báo cho {$notified} thành viên")
                    ->success()
                    ->send();
            }),
        ];
    }
}
