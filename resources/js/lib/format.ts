const vnd = new Intl.NumberFormat('vi-VN');

const dateTime = new Intl.DateTimeFormat('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
});

export function formatVnd(amount: number | null | undefined): string {
    return amount === null || amount === undefined
        ? '—'
        : `${vnd.format(amount)} đ`;
}

/** The price line of a listing: "Thoả thuận" when the seller did not state one. */
export function formatPrice(listing: {
    negotiable: boolean;
    current_price: number;
    winning_price?: number | null;
}): string {
    return listing.negotiable
        ? 'Thoả thuận'
        : formatVnd(listing.winning_price ?? listing.current_price);
}

export function formatDateTime(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    // The engine sends "Y-m-d H:i:s" in local time; Safari needs the "T".
    const date = new Date(
        value.includes('T') ? value : value.replace(' ', 'T'),
    );

    return Number.isNaN(date.getTime()) ? value : dateTime.format(date);
}

/** "2 ngày 03:04:05" / "03:04:05" for a number of seconds. */
export function formatDuration(totalSeconds: number): string {
    const seconds = Math.max(0, Math.floor(totalSeconds));
    const days = Math.floor(seconds / 86400);
    const clock = [
        Math.floor((seconds % 86400) / 3600),
        Math.floor((seconds % 3600) / 60),
        seconds % 60,
    ]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');

    return days > 0 ? `${days} ngày ${clock}` : clock;
}
