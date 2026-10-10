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
    /** 'fixed' is a plain post with a price: no auction, no bids, no countdown. */
    sale_type: 'auction' | 'fixed';
    auction_type: number | null;
    status: number;
    status_label: string;
    current_price: number;
    /** "Giá thoả thuận": a fixed-price listing without a stated price. */
    negotiable: boolean;
    bid_count: number;
    view_count: number;
    starts_at: string | null;
    ends_at: string | null;
    winner: string | null;
    winning_price: number | null;
    /** False while the listing waits for approval: it has no detail page yet. */
    has_auction: boolean;
    leader_id: number | null;
    moderation: 'draft' | 'pending' | 'approved' | 'rejected' | 'hidden';
    moderation_label: string;
    moderation_note: string | null;
};

/** A card on "my auctions", with what the member may do to it. */
export type DashboardCard = ListingCard & {
    leading: boolean;
    can: {
        edit: boolean;
        cancel: boolean;
        relist: boolean;
        mark_sold: boolean;
        renew: boolean;
    };
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
    /** Average of received reviews, 0 when there are none. */
    rating: number;
    review_count: number;
};

export type Contact = {
    name: string;
    email: string;
    phone: string | null;
    address: string | null;
};

export type ReviewItem = {
    id: number;
    rating: number;
    comment: string | null;
    created_at: string | null;
    reviewer: string | null;
    listing: string | null;
};

export type ListingDetail = ListingCard & {
    description: string | null;
    condition: string;
    /** Each photo in two sizes: `url` for the page and the zoom, `thumb` for the strip below. */
    images: { url: string; thumb: string }[];
    category: { name: string; slug: string } | null;
    tags: { name: string; slug: string }[];
    auction_type_label: string;
    start_price: number;
    min_bid_price: number | null;
    /** Seller's minimum raise for ascending auctions; null when none was set. */
    bid_step: number | null;
    auto_extend: boolean;
    down_time: number | null;
    down_price: number | null;
    finish_price: number | null;
    created_at: string | null;
    is_owner: boolean;
    is_winner: boolean;
    seller: Seller;
    watching: boolean;
    watcher_count: number;
    can: {
        edit: boolean;
        settle: boolean;
        cancel: boolean;
        mark_sold: boolean;
        renew: boolean;
    };
    /** The other side's contact details; only for the seller and the winner of a finished auction. */
    contact: Contact | null;
    my_review: ReviewItem | null;
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
    mine: boolean;
    can_cancel: boolean;
};

export type Giveaway = {
    id: number;
    title: string;
    prize: string;
    image: string | null;
    winner_count: number;
    entry_count: number;
    ends_at: string;
    drawn_at: string | null;
    /** Still taking entries. */
    open: boolean;
    joined: boolean;
    won: boolean;
};

export type NotificationItem = {
    id: string;
    kind: string;
    title: string;
    body: string;
    read: boolean;
    created_at: string | null;
};

/** What the product part of the create and edit forms works on. */
export type ProductFormFields = {
    title: string;
    description: string;
    category_id: number | null;
    city_id: number | null;
    condition: string;
    images: File[];
    tags: string[];
};

export type EditableListing = Omit<ProductFormFields, 'images'> & {
    id: number;
    images: { id: number; url: string }[];
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
