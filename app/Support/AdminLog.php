<?php

namespace App\Support;

use App\Models\AdminAction;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes the admin panel's audit log: who did what, to what, and when.
 */
class AdminLog
{
    /** What each action key means, for the log page and its filter. */
    public const LABELS = [
        'listing.approved' => 'Duyệt tin',
        'listing.rejected' => 'Từ chối tin',
        'listing.hidden' => 'Ẩn tin',
        'listing.auction_cancelled' => 'Huỷ phiên đấu giá',
        'listing.bulk_approved' => 'Duyệt nhiều tin',
        'listing.bulk_hidden' => 'Ẩn nhiều tin',
        'report.bulk_dismissed' => 'Bỏ qua nhiều báo cáo',
        'comment.bulk_deleted' => 'Xoá nhiều bình luận',
        'user.bulk_blocked' => 'Khoá nhiều tài khoản',
        'user.bulk_unblocked' => 'Mở khoá nhiều tài khoản',
        'report.dismissed' => 'Bỏ qua báo cáo',
        'user.blocked' => 'Khoá tài khoản',
        'user.unblocked' => 'Mở khoá tài khoản',
        'user.role_changed' => 'Đổi vai trò',
        'user.bid_ban_lifted' => 'Gỡ cấm đặt giá',
        'user.updated' => 'Sửa thành viên',
        'unlock_request.dismissed' => 'Bỏ qua yêu cầu mở khoá',
        'comment.deleted' => 'Xoá bình luận',
        'giveaway.created' => 'Tạo quà tặng',
        'giveaway.updated' => 'Sửa quà tặng',
        'giveaway.deleted' => 'Xoá quà tặng',
        'giveaway.drawn' => 'Quay thưởng',
        'category.created' => 'Tạo danh mục',
        'category.updated' => 'Sửa danh mục',
        'category.deleted' => 'Xoá danh mục',
        'blocked_ip.created' => 'Chặn IP',
        'blocked_ip.updated' => 'Sửa IP bị chặn',
        'blocked_ip.deleted' => 'Bỏ chặn IP',
        'blocked_domain.created' => 'Chặn tên miền email',
        'blocked_domain.updated' => 'Sửa tên miền bị chặn',
        'blocked_domain.deleted' => 'Bỏ chặn tên miền email',
        'settings.updated' => 'Đổi cài đặt',
        'settings.password_policy' => 'Đổi chính sách mật khẩu',
        'settings.listing_approval' => 'Bật / tắt duyệt tin',
    ];

    /**
     * Record an action of the signed-in staff member. The summary is written
     * out in full now, so the line still reads correctly after its subject
     * has been renamed or deleted.
     */
    public static function record(string $action, ?Model $subject, string $summary): void
    {
        AdminAction::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'summary' => $summary,
            'created_at' => now(),
        ]);
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }
}
