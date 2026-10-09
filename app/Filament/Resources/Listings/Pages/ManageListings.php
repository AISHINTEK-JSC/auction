<?php

namespace App\Filament\Resources\Listings\Pages;

use App\Actions\Listings\CreateListing;
use App\Filament\Resources\Listings\ListingResource;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManageListings extends ManageRecords
{
    protected static string $resource = ListingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // While on, new listings wait as "Chờ duyệt" and their auction is only
            // opened in the engine when a moderator approves them.
            Action::make('toggleApproval')
                ->label(fn () => CreateListing::approvalRequired() ? 'Duyệt tin trước khi hiển thị: BẬT' : 'Duyệt tin trước khi hiển thị: TẮT')
                ->icon(Heroicon::OutlinedShieldCheck)
                ->color(fn () => CreateListing::approvalRequired() ? 'success' : 'gray')
                ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                ->requiresConfirmation()
                ->modalDescription(fn () => CreateListing::approvalRequired()
                    ? 'Tắt: tin mới sẽ hiển thị và mở phiên đấu giá ngay khi đăng.'
                    : 'Bật: tin mới phải được kiểm duyệt viên duyệt rồi mới hiển thị và mở phiên đấu giá.')
                ->action(function (): void {
                    Setting::write(Setting::LISTING_APPROVAL, ! CreateListing::approvalRequired());

                    Notification::make()->title('Đã lưu')->success()->send();
                }),
        ];
    }
}
