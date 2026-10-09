// Shapes produced by App\Support\ListingPresenter, BidPresenter and ListingSyncService.

export const AuctionStatus = {
    Upcoming: 0,
    Running: 1,
    Completed: 2,
    CancelledBySeller: 3,
    CancelledByBuyer: 4,
    Expired: 5,
} as const;

export const AuctionType = {
    Up: 1,
    Down: 2,
} as const;

export const BidType = {
    Limit: 1,
    Market: 2,
} as const;

export type CategoryNode = {
    id: number;
    name: string;
    slug: string;
    children: { id: number; name: string; slug: string }[];
};

export type CityOption = {
    id: number;
    name: string;
};

export type ListingCard = {
    id: number;
    title: string;
    image: string | null;
    city: string | null;
    auction_type: number;
    status: number;
    status_label: string;
    current_price: number;
    bid_count: number;
    starts_at: string | null;
    ends_at: string | null;
    winner: string | null;
    winning_price: number | null;
};

export type Seller = {
    id: number;
    name: string;
    avatar: string | null;
    joined_at: string | null;
    has_phone: boolean;
    has_address: boolean;
    listing_count: number;
    sold_count: number;
};

export type ListingDetail = ListingCard & {
    description: string | null;
    condition: string;
    images: string[];
    category: { name: string; slug: string } | null;
    tags: string[];
    auction_type_label: string;
    start_price: number;
    min_bid_price: number | null;
    auto_extend: boolean;
    down_time: number | null;
    down_price: number | null;
    finish_price: number | null;
    created_at: string | null;
    is_owner: boolean;
    seller: Seller;
};

/** Live engine state; the detail page polls it. */
export type AuctionState = {
    status: number;
    status_label: string;
    current_price: number;
    bid_count: number;
    /** Seconds left while running, otherwise null. */
    remaining_time: number | null;
    highest_bidder_id: number | null;
    /** True when the engine could not be reached and this is the stored snapshot. */
    stale: boolean;
};

export type Bid = {
    id: number;
    bidder: string;
    price: number;
    current_price: number;
    type: number;
    time: string;
};

export type CommentItem = {
    id: number;
    body: string;
    created_at: string | null;
    user: { id: number; name: string; avatar: string | null };
    likes: number;
    liked: boolean;
    can_delete: boolean;
    replies?: CommentItem[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
