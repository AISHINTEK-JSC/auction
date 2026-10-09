<?php

namespace App\Filament\Resources\BlockedIps\Pages;

use App\Filament\Resources\BlockedIps\BlockedIpResource;
use App\Models\BlockedIp;
use App\Support\AdminLog;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedIps extends ManageRecords
{
    protected static string $resource = BlockedIpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->after(fn (BlockedIp $record) => AdminLog::record('blocked_ip.created', $record, 'Thêm IP bị chặn "'.$record->address.'"')),
        ];
    }
}
