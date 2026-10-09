<?php

namespace App\Filament\Resources\BlockedEmailDomains\Pages;

use App\Filament\Resources\BlockedEmailDomains\BlockedEmailDomainResource;
use App\Models\BlockedEmailDomain;
use App\Support\AdminLog;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBlockedEmailDomains extends ManageRecords
{
    protected static string $resource = BlockedEmailDomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->after(fn (BlockedEmailDomain $record) => AdminLog::record('blocked_domain.created', $record, 'Thêm tên miền email bị chặn "'.$record->domain.'"')),
        ];
    }
}
